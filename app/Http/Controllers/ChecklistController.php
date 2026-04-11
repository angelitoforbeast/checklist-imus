<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTask;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionFile;
use App\Models\ChecklistSubmissionLog;
use App\Models\ChecklistAnalysisLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ChecklistController extends Controller
{
    /**
     * Main checklist page - staff submits tasks for today
     */
    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $tasks = ChecklistTask::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $submissions = ChecklistSubmission::where('date', $date)
            ->with(['user', 'files', 'logs.user'])
            ->get()
            ->keyBy('checklist_task_id');

        return view('checklist.index', compact('tasks', 'submissions', 'date'));
    }

    /**
     * Submit a task (create or update submission)
     */
    public function submit(Request $request, ChecklistTask $task)
    {
        $request->validate([
            'notes' => 'nullable|string|max:5000',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $date = $request->get('date', Carbon::today()->toDateString());
        $user = Auth::user();

        $submission = ChecklistSubmission::firstOrNew([
            'checklist_task_id' => $task->id,
            'date' => $date,
        ]);

        $isNew = !$submission->exists;
        $submission->user_id = $submission->user_id ?? $user->id;
        $submission->notes = $request->input('notes');
        $submission->save();

        // Handle file uploads
        $fileCount = $submission->files()->count();
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('checklist/' . $date, 'public');
                $submission->files()->create([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
                $fileCount++;
            }
        }

        // Log the action
        ChecklistSubmissionLog::create([
            'checklist_submission_id' => $submission->id,
            'user_id' => $user->id,
            'action' => $isNew ? 'submitted' : 'updated',
            'notes_snapshot' => $submission->notes,
            'file_count' => $fileCount,
        ]);

        return back()->with('success', 'Task submitted successfully.');
    }

    /**
     * Delete a submission
     */
    public function deleteSubmission(ChecklistSubmission $submission)
    {
        foreach ($submission->files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }
        $submission->delete();

        return back()->with('success', 'Submission deleted.');
    }

    /**
     * Delete a single file from a submission
     */
    public function deleteFile(ChecklistSubmissionFile $file)
    {
        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        return back()->with('success', 'File deleted.');
    }

    /**
     * Daily report page - manager view
     */
    public function report(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $tasks = ChecklistTask::withTrashed()
            ->orderBy('sort_order')
            ->get();

        $submissions = ChecklistSubmission::where('date', $date)
            ->with(['user', 'files', 'logs.user', 'analysisLogs' => function ($q) {
                $q->latest();
            }])
            ->get()
            ->keyBy('checklist_task_id');

        $totalTasks = ChecklistTask::where('is_active', true)->count();
        $completedTasks = $submissions->count();
        $progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return view('checklist.report', compact('tasks', 'submissions', 'date', 'totalTasks', 'completedTasks', 'progressPercent'));
    }

    /**
     * Manage tasks page - admin only
     */
    public function manage()
    {
        $tasks = ChecklistTask::orderBy('sort_order')->get();
        return view('checklist.manage', compact('tasks'));
    }

    /**
     * Store a new task
     */
    public function storeTask(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:photo,note,any,both',
            'ai_prompt' => 'nullable|string|max:2000',
        ]);

        $maxOrder = ChecklistTask::max('sort_order') ?? 0;

        ChecklistTask::create([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'ai_prompt' => $request->ai_prompt,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Task created.');
    }

    /**
     * Update an existing task
     */
    public function updateTask(Request $request, ChecklistTask $task)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'type' => 'required|in:photo,note,any,both',
            'is_active' => 'nullable|boolean',
            'ai_prompt' => 'nullable|string|max:2000',
        ]);

        $task->update([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'is_active' => $request->boolean('is_active'),
            'ai_prompt' => $request->ai_prompt,
        ]);

        return back()->with('success', 'Task updated.');
    }

    /**
     * Delete a task (soft delete)
     */
    public function destroyTask(ChecklistTask $task)
    {
        $task->delete();
        return back()->with('success', 'Task deleted.');
    }

    /**
     * Reorder tasks via drag-and-drop
     */
    public function reorderTasks(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:checklist_tasks,id',
        ]);

        foreach ($request->order as $index => $taskId) {
            ChecklistTask::where('id', $taskId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * AI Analyze a submission
     */
    public function analyzeSubmission(Request $request, ChecklistSubmission $submission)
    {
        $task = $submission->task;
        $submission->load('files');

        $defaultPrompt = "You are a quality inspector. Analyze the submitted checklist item. Check if the task was completed properly based on the images and notes provided. Be concise but thorough.";
        $prompt = $task->ai_prompt ?: $defaultPrompt;

        $fullPrompt = "Task: {$task->title}\n";
        if ($task->description) {
            $fullPrompt .= "Description: {$task->description}\n";
        }
        if ($submission->notes) {
            $fullPrompt .= "Notes from staff: {$submission->notes}\n";
        }
        $fullPrompt .= "\nInstructions: {$prompt}";

        $content = [
            ['type' => 'text', 'text' => $fullPrompt],
        ];

        foreach ($submission->files as $file) {
            $url = url('storage/' . $file->file_path);
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $url],
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.key'),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'user', 'content' => $content],
                ],
                'max_tokens' => 1000,
            ]);

            $result = $response->json('choices.0.message.content', 'Analysis failed.');
        } catch (\Exception $e) {
            $result = 'Error: ' . $e->getMessage();
        }

        ChecklistAnalysisLog::create([
            'submission_id' => $submission->id,
            'user_id' => Auth::id(),
            'prompt_used' => $fullPrompt,
            'analysis_result' => $result,
        ]);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    /**
     * Get analysis logs for a submission
     */
    public function getAnalysisLogs(ChecklistSubmission $submission)
    {
        $logs = $submission->analysisLogs()
            ->with('user')
            ->latest()
            ->get();

        return response()->json($logs);
    }
}
