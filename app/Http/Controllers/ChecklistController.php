<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTask;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionFile;
use App\Models\ChecklistSubmissionLog;
use App\Models\ChecklistAnalysisLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ChecklistController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $user  = Auth::user();

        $tasksQuery = ChecklistTask::with('assignedUsers')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        $allTasks = $tasksQuery->get();

        // Non-admin users only see tasks assigned to them (or tasks with no assignment = everyone)
        if (!$user->isAdmin()) {
            $tasks = $allTasks->filter(function ($task) use ($user) {
                $assignedIds = $task->assignedUsers->pluck('id')->toArray();
                return empty($assignedIds) || in_array($user->id, $assignedIds);
            })->values();
        } else {
            $tasks = $allTasks;
        }

        // One submission per task per day — keyed by task_id
        $submissionsByTask = ChecklistSubmission::with(['user', 'files', 'logs.user'])
            ->where('date', $today)
            ->whereIn('checklist_task_id', $tasks->pluck('id'))
            ->get()
            ->keyBy('checklist_task_id');

        $doneCount  = $submissionsByTask->count();
        $totalTasks = $tasks->count();

        return view('checklist.index', compact(
            'tasks', 'submissionsByTask',
            'today', 'doneCount', 'totalTasks'
        ));
    }

    public function report(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        try {
            $dateObj = \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            $dateObj = now();
        }

        $isToday = $dateObj->isToday();

        if ($isToday) {
            $tasks = ChecklistTask::with('assignedUsers')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } else {
            $endOfDay = $dateObj->copy()->endOfDay();

            $tasks = ChecklistTask::withTrashed()
                ->with('assignedUsers')
                ->whereDate('created_at', '<=', $dateObj->toDateString())
                ->where(function ($q) use ($endOfDay) {
                    $q->whereNull('deleted_at')
                      ->orWhere('deleted_at', '>', $endOfDay);
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        $submissionsByTask = ChecklistSubmission::with(['user', 'files', 'logs.user', 'latestAnalysis.user'])
            ->withCount('analysisLogs')
            ->where('date', $dateObj->toDateString())
            ->get()
            ->keyBy('checklist_task_id');

        if (!$isToday) {
            $missingIds = $submissionsByTask->keys()->diff($tasks->pluck('id'));
            if ($missingIds->isNotEmpty()) {
                $extra = ChecklistTask::withTrashed()
                    ->with('assignedUsers')
                    ->whereIn('id', $missingIds)
                    ->get();
                $tasks = $tasks->merge($extra)->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
            }
        }

        $doneCount  = $submissionsByTask->count();
        $totalTasks = $tasks->count();

        $prevDate = $dateObj->copy()->subDay()->toDateString();
        $nextDate = $dateObj->copy()->addDay()->toDateString();

        return view('checklist.report', compact(
            'tasks', 'submissionsByTask',
            'doneCount', 'totalTasks',
            'dateObj', 'prevDate', 'nextDate', 'isToday'
        ));
    }

    public function manage()
    {
        $allTasks = ChecklistTask::with('assignedUsers')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $allUsers = User::orderBy('name')->get();

        return view('checklist.manage', compact('allTasks', 'allUsers'));
    }

    public function submit(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();

        $assignedIds = $task->assignedUsers()->pluck('users.id')->toArray();
        if (!empty($assignedIds) && !in_array(Auth::id(), $assignedIds)) {
            return back()->with('error', 'You are not assigned to this task.');
        }

        $imageMimes = 'jpg,jpeg,png,gif,webp';
        $anyMimes   = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv';

        $existing = ChecklistSubmission::with('files')->where([
            'checklist_task_id' => $task->id,
            'date'              => $today,
        ])->first();

        $isNew            = $existing === null;
        $hasExistingFiles = $existing && ($existing->files->count() > 0 || $existing->file_path);

        $rules = ['notes' => 'nullable|string|max:2000'];

        if ($task->type === 'photo') {
            $rules['files']   = $hasExistingFiles ? 'nullable|array|max:10' : 'required|array|min:1|max:10';
            $rules['files.*'] = "file|max:10240|mimes:{$imageMimes}";
        } elseif ($task->type === 'both') {
            $rules['notes']   = 'required|string|max:2000';
            $rules['files']   = $hasExistingFiles ? 'nullable|array|max:10' : 'required|array|min:1|max:10';
            $rules['files.*'] = "file|max:10240|mimes:{$imageMimes}";
        } elseif ($task->type === 'any') {
            $rules['files']   = 'nullable|array|max:10';
            $rules['files.*'] = "file|max:10240|mimes:{$anyMimes}";
        }

        $request->validate($rules);

        $submission = ChecklistSubmission::updateOrCreate(
            ['checklist_task_id' => $task->id, 'date' => $today],
            ['notes' => $request->notes, 'user_id' => $isNew ? Auth::id() : $existing->user_id]
        );

        $fileCount = $request->hasFile('files') ? count($request->file('files')) : ($submission->files()->count());
        ChecklistSubmissionLog::create([
            'checklist_submission_id' => $submission->id,
            'user_id'                 => Auth::id(),
            'action'                  => $isNew ? 'submitted' : 'updated',
            'notes_snapshot'          => $request->notes ? \Str::limit($request->notes, 200) : null,
            'file_count'              => $fileCount,
            'created_at'              => now(),
        ]);

        if ($request->hasFile('files')) {
            $nextOrder = $submission->files()->max('sort_order') + 1;
            foreach ($request->file('files') as $i => $file) {
                $submission->files()->create([
                    'file_path'          => $file->store("checklist/{$today}", 'public'),
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime'          => $file->getMimeType(),
                    'sort_order'         => $nextOrder + $i,
                ]);
            }
        }

        return back()->with('success', "'{$task->title}' submitted!");
    }

    public function deleteSubmission(ChecklistSubmission $submission)
    {
        if ($submission->user_id !== Auth::id() && !Auth::user()?->isAdmin()) {
            abort(403);
        }

        foreach ($submission->files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }
        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        $submission->delete();
        return back()->with('success', 'Submission removed.');
    }

    public function deleteFile(ChecklistSubmissionFile $file)
    {
        $submission = $file->submission;

        if ($submission->user_id !== Auth::id() && !Auth::user()?->isAdmin()) {
            abort(403);
        }

        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        return back()->with('success', 'File removed.');
    }

    public function storeTask(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type'        => 'required|in:photo,note,any,both',
            'ai_prompt'   => 'nullable|string|max:2000',
            'task_time'   => 'nullable|date_format:H:i',
        ]);

        $task = ChecklistTask::create([
            ...$validated,
            'sort_order' => (ChecklistTask::max('sort_order') ?? 0) + 1,
            'is_active'  => true,
        ]);

        $userIds = array_filter((array) $request->input('assigned_users', []));
        $task->assignedUsers()->sync($userIds);

        return back()->with('success', 'Task added!');
    }

    public function updateTask(Request $request, ChecklistTask $task)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type'        => 'required|in:photo,note,any,both',
            'is_active'   => 'boolean',
            'ai_prompt'   => 'nullable|string|max:2000',
            'task_time'   => 'nullable|date_format:H:i',
        ]);

        $task->update($validated);

        $userIds = array_filter((array) $request->input('assigned_users', []));
        $task->assignedUsers()->sync($userIds);

        return back()->with('success', 'Task updated!');
    }

    public function destroyTask(ChecklistTask $task)
    {
        $task->delete();
        return back()->with('success', 'Task deleted.');
    }

    public function reorderTasks(Request $request)
    {
        foreach ($request->input('order', []) as $index => $id) {
            ChecklistTask::where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['ok' => true]);
    }

    public function analyzeSubmission(Request $request, ChecklistSubmission $submission)
    {
        $submission->load(['task', 'files']);
        $task     = $submission->task;
        $imgFiles = $submission->files->filter(fn($f) => $f->isImage());

        if (!$task) {
            return response()->json(['error' => 'Task not found for this submission.'], 404);
        }

        $prompt  = "You are reviewing a daily operational task submission for a business.\n\n";
        $prompt .= "Task: {$task->title}\n";
        if ($task->description) {
            $prompt .= "Task Description: {$task->description}\n";
        }
        if ($submission->notes) {
            $prompt .= "Staff Notes: {$submission->notes}\n";
        }
        if ($imgFiles->isEmpty()) {
            $prompt .= "\n(No images were submitted for this task.)\n";
        }

        if ($task->ai_prompt) {
            $prompt .= "\nAnalysis Focus: {$task->ai_prompt}";
            $prompt .= "\n\nUsing the above focus, provide a concise analysis in 2-4 sentences based on the submission.";
        } else {
            $prompt .= "\nBased on the above, provide a concise analysis in 2-4 sentences: Was the task completed properly? What do the images show (if any)? Any observations, concerns, or recommendations?";
        }

        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($imgFiles->take(5) as $f) {
            $content[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => url(Storage::url($f->file_path)),
                    'detail' => 'auto',
                ],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'      => 'gpt-4o',
            'max_tokens' => 512,
            'messages'   => [
                ['role' => 'user', 'content' => $content],
            ],
        ]);

        if ($response->successful()) {
            $analysisText = $response->json('choices.0.message.content');

            ChecklistAnalysisLog::create([
                'submission_id'   => $submission->id,
                'user_id'         => Auth::id(),
                'prompt_used'     => $prompt,
                'analysis_result' => $analysisText,
            ]);

            return response()->json([
                'analysis'    => $analysisText,
                'prompt_used' => $prompt,
                'analyzed_by' => Auth::user()?->name,
                'analyzed_at' => now()->format('M j, h:i A'),
            ]);
        }

        return response()->json([
            'error' => 'AI analysis failed (' . $response->status() . '). Check your API key.',
        ], 500);
    }

    public function getAnalysisLogs(ChecklistSubmission $submission)
    {
        $logs = $submission->analysisLogs()->with('user')->get()->map(fn($log) => [
            'id'          => $log->id,
            'analysis'    => $log->analysis_result,
            'prompt_used' => $log->prompt_used,
            'user'        => $log->user?->name ?? 'Unknown',
            'created_at'  => $log->created_at->format('M j, Y g:i A'),
        ]);

        return response()->json(['logs' => $logs]);
    }
}
