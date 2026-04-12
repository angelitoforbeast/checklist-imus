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

        $doneCount  = $submissionsByTask->where('status', 'completed')->count();
        $totalTasks = $tasks->count();

        // Load admin comments for today, grouped by task_id
        $commentsByTask = \App\Models\ChecklistTaskComment::with('user')
            ->where('date', $today)
            ->orderBy('created_at')
            ->get()
            ->groupBy('checklist_task_id');

        return view('checklist.index', compact(
            'tasks', 'submissionsByTask',
            'today', 'doneCount', 'totalTasks', 'commentsByTask'
        ));
    }

    public function report(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $roleFilter = $request->query('role', '');

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

        $submissionsByTask = ChecklistSubmission::with(['user', 'files', 'logs.user', 'latestAnalysis.user', 'latestApproval.user'])
            ->withCount(['analysisLogs', 'approvalLogs'])
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

        $doneCount  = $submissionsByTask->where('status', 'completed')->count();
        $totalTasks = $tasks->count();

        $prevDate = $dateObj->copy()->subDay()->toDateString();
        $nextDate = $dateObj->copy()->addDay()->toDateString();

        // Role filter: filter tasks by assigned users' role
        $roles = \App\Models\Role::orderBy('name')->get();
        if ($roleFilter) {
            $roleUserIds = \App\Models\User::where('role_id', $roleFilter)->pluck('id')->toArray();
            $tasks = $tasks->filter(function ($task) use ($roleUserIds) {
                $assignedIds = $task->assignedUsers->pluck('id')->toArray();
                // Show task if it has no assignment (everyone) or if any assigned user is in the filtered role
                return empty($assignedIds) || !empty(array_intersect($assignedIds, $roleUserIds));
            })->values();
            // Recalculate counts after filter
            $doneCount = $tasks->filter(fn($t) => $submissionsByTask->has($t->id) && $submissionsByTask->get($t->id)->status === 'completed')->count();
            $totalTasks = $tasks->count();
        }

        // Load admin comments for this date, grouped by task_id
        $commentsByTask = \App\Models\ChecklistTaskComment::with('user')
            ->where('date', $dateObj->toDateString())
            ->orderBy('created_at')
            ->get()
            ->groupBy('checklist_task_id');

        return view('checklist.report', compact(
            'tasks', 'submissionsByTask',
            'doneCount', 'totalTasks',
            'dateObj', 'prevDate', 'nextDate', 'isToday',
            'roles', 'roleFilter', 'commentsByTask'
        ));
    }

    public function conversations(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $roleFilter = $request->query('role', '');

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

        $submissionsByTask = ChecklistSubmission::with(['user', 'files', 'logs.user'])
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

        $doneCount  = $submissionsByTask->where('status', 'completed')->count();
        $totalTasks = $tasks->count();

        $prevDate = $dateObj->copy()->subDay()->toDateString();
        $nextDate = $dateObj->copy()->addDay()->toDateString();

        $roles = \App\Models\Role::orderBy('name')->get();
        if ($roleFilter) {
            $roleUserIds = \App\Models\User::where('role_id', $roleFilter)->pluck('id')->toArray();
            $tasks = $tasks->filter(function ($task) use ($roleUserIds) {
                $assignedIds = $task->assignedUsers->pluck('id')->toArray();
                return empty($assignedIds) || !empty(array_intersect($assignedIds, $roleUserIds));
            })->values();
            $doneCount = $tasks->filter(fn($t) => $submissionsByTask->has($t->id) && $submissionsByTask->get($t->id)->status === 'completed')->count();
            $totalTasks = $tasks->count();
        }

        $commentsByTask = \App\Models\ChecklistTaskComment::with('user')
            ->where('date', $dateObj->toDateString())
            ->orderBy('created_at')
            ->get()
            ->groupBy('checklist_task_id');

        return view('checklist.conversations', compact(
            'tasks', 'submissionsByTask',
            'doneCount', 'totalTasks',
            'dateObj', 'prevDate', 'nextDate', 'isToday',
            'roles', 'roleFilter', 'commentsByTask'
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
        } elseif ($task->type === 'photo_note') {
            // Photo required, note optional
            $rules['files']   = $hasExistingFiles ? 'nullable|array|max:10' : 'required|array|min:1|max:10';
            $rules['files.*'] = "file|max:10240|mimes:{$imageMimes}";
            $rules['notes']   = 'nullable|string|max:2000';
        } elseif ($task->type === 'both') {
            $rules['notes']   = 'required|string|max:2000';
            $rules['files']   = $hasExistingFiles ? 'nullable|array|max:10' : 'required|array|min:1|max:10';
            $rules['files.*'] = "file|max:10240|mimes:{$imageMimes}";
        } elseif ($task->type === 'any') {
            $rules['files']   = 'nullable|array|max:10';
            $rules['files.*'] = "file|max:10240|mimes:{$anyMimes}";
        }

        $request->validate($rules);

        // Extra check: for photo-required types, ensure files are actually present
        if (in_array($task->type, ['photo', 'photo_note', 'both']) && !$hasExistingFiles && !$request->hasFile('files')) {
            return back()->withErrors(['files' => 'At least one photo is required.'])->withInput();
        }

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

    /**
     * Revert a completed submission back to pending.
     * Keeps all data (photos, notes, analysis) but sets status to 'pending'.
     * User must re-submit to mark it completed again.
     * Admin only — accessible from the report page.
     */
    public function revertSubmission(ChecklistSubmission $submission)
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403);
        }

        // Set status to pending (keep all data)
        $submission->status = 'pending';
        $submission->save();

        // Log the revert action
        $submission->logs()->create([
            'user_id' => Auth::id(),
            'action' => 'reverted',
            'notes_snapshot' => $submission->notes,
            'file_count' => $submission->files->count(),
        ]);

        return back()->with('success', 'Task reverted to pending. User needs to re-submit.');
    }

    /**
     * Admin sends a comment/message on a task (visible in user's Messenger chat view).
     */
    public function sendComment(Request $request, ChecklistTask $task)
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'message' => 'required|string|max:2000',
            'date'    => 'required|date',
        ]);

        $comment = \App\Models\ChecklistTaskComment::create([
            'checklist_task_id' => $task->id,
            'user_id'           => Auth::id(),
            'date'              => $request->date,
            'message'           => $request->message,
        ]);

        return back()->with('success', 'Comment sent.');
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
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'type'            => 'required|in:photo,note,any,both,photo_note',
            'ai_prompt'       => 'nullable|string|max:2000',
            'approval_prompt' => 'nullable|string|max:2000',
            'task_time'       => 'nullable|date_format:H:i',
            'reference_image' => 'nullable|image|max:5120',
        ]);

        $imagePath = null;
        if ($request->hasFile('reference_image')) {
            $imagePath = $request->file('reference_image')->store('task-references', 'public');
        }

        $task = ChecklistTask::create([
            ...$validated,
            'reference_image' => $imagePath,
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
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'type'            => 'required|in:photo,note,any,both,photo_note',
            'is_active'       => 'boolean',
            'ai_prompt'       => 'nullable|string|max:2000',
            'approval_prompt' => 'nullable|string|max:2000',
            'task_time'       => 'nullable|date_format:H:i',
            'reference_image' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('reference_image')) {
            if ($task->reference_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($task->reference_image);
            }
            $validated['reference_image'] = $request->file('reference_image')->store('task-references', 'public');
        }
        if ($request->boolean('remove_reference_image')) {
            if ($task->reference_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($task->reference_image);
            }
            $validated['reference_image'] = null;
        }

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
        $submission->load(['files']);
        $task     = $submission->task ?? ChecklistTask::withTrashed()->find($submission->checklist_task_id);
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
                'log_type'        => 'analysis',
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

    public function approvalCheck(Request $request, ChecklistSubmission $submission)
    {
        $submission->load(['files']);
        $task     = $submission->task ?? ChecklistTask::withTrashed()->find($submission->checklist_task_id);
        $imgFiles = $submission->files->filter(fn($f) => $f->isImage());

        if (!$task) {
            return response()->json(['error' => 'Task not found.'], 404);
        }

        $prompt  = "You are a quality control reviewer for a business daily checklist submission.\n\n";
        $prompt .= "Task: {$task->title}\n";
        if ($task->description) $prompt .= "Description: {$task->description}\n";
        if ($submission->notes) $prompt .= "Staff Notes: {$submission->notes}\n";
        if ($imgFiles->isEmpty()) $prompt .= "\n(No images were submitted.)\n";

        $criteria = $task->approval_prompt
            ?: 'Evaluate whether the submission properly completes the task based on the title, description, and submitted content (notes and/or images). Assess overall quality and completeness.';

        $prompt .= "\nApproval Criteria: {$criteria}\n";
        $prompt .= "\nIMPORTANT: Your response MUST start with exactly \"APPROVED\" or \"NOT APPROVED\" on the first line, followed by a blank line, then your explanation in 2-3 sentences.";

        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($imgFiles->take(5) as $f) {
            $content[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => url(Storage::url($f->file_path)), 'detail' => 'auto'],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'      => 'gpt-4o',
            'max_tokens' => 512,
            'messages'   => [['role' => 'user', 'content' => $content]],
        ]);

        if ($response->successful()) {
            $text      = $response->json('choices.0.message.content');
            $firstLine = strtoupper(trim(explode("\n", trim($text))[0]));
            $verdict   = str_starts_with($firstLine, 'NOT APPROVED') ? 'not_approved'
                       : (str_starts_with($firstLine, 'APPROVED')    ? 'approved' : 'unknown');

            ChecklistAnalysisLog::create([
                'submission_id'   => $submission->id,
                'user_id'         => Auth::id(),
                'log_type'        => 'approval',
                'prompt_used'     => $prompt,
                'analysis_result' => $text,
                'verdict'         => $verdict,
            ]);

            return response()->json([
                'verdict'     => $verdict,
                'analysis'    => $text,
                'prompt_used' => $prompt,
                'checked_by'  => Auth::user()?->name,
                'checked_at'  => now()->format('M j, g:i A'),
            ]);
        }

        return response()->json([
            'error' => 'Approval check failed (' . $response->status() . '). Check your API key.',
        ], 500);
    }

    public function getApprovalLogs(ChecklistSubmission $submission)
    {
        $logs = $submission->approvalLogs()->with('user')->get()->map(fn($log) => [
            'id'          => $log->id,
            'verdict'     => $log->verdict,
            'analysis'    => $log->analysis_result,
            'prompt_used' => $log->prompt_used,
            'user'        => $log->user?->name ?? 'Unknown',
            'created_at'  => $log->created_at->format('M j, Y g:i A'),
        ]);

        return response()->json(['logs' => $logs]);
    }
    /**
     * AJAX: Upload a single photo instantly (auto-send).
     */
    public function uploadPhoto(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();
        $assignedIds = $task->assignedUsers()->pluck('users.id')->toArray();
        if (!Auth::user()->isAdmin() && !empty($assignedIds) && !in_array(Auth::id(), $assignedIds)) {
            return response()->json(['error' => 'Not assigned.'], 403);
        }

        $request->validate([
            'photo' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp',
        ]);

        // Find or create today's submission
        $submission = ChecklistSubmission::firstOrCreate(
            ['checklist_task_id' => $task->id, 'date' => $today],
            ['user_id' => Auth::id(), 'status' => 'completed']
        );

        // If reverted, mark as completed again (resubmit)
        if ($submission->status === 'pending') {
            $submission->status = 'completed';
            $submission->save();
        }

        $file = $request->file('photo');
        $nextOrder = $submission->files()->max('sort_order') + 1;

        $fileRecord = $submission->files()->create([
            'file_path'          => $file->store("checklist/{$today}", 'public'),
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime'          => $file->getMimeType(),
            'sort_order'         => $nextOrder,
        ]);

        ChecklistSubmissionLog::create([
            'checklist_submission_id' => $submission->id,
            'user_id'                 => Auth::id(),
            'action'                  => 'photo_uploaded',
            'notes_snapshot'          => null,
            'file_count'              => 1,
            'created_at'              => now(),
        ]);

        return response()->json([
            'success'    => true,
            'file_id'    => $fileRecord->id,
            'url'        => Storage::url($fileRecord->file_path),
            'name'       => $fileRecord->file_original_name,
            'uploaded_by' => Auth::user()->name,
            'uploaded_at' => now()->format('g:i A'),
        ]);
    }

    /**
     * AJAX: Send a text note/remark for a task.
     */
    public function sendNote(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();
        $assignedIds = $task->assignedUsers()->pluck('users.id')->toArray();
        if (!Auth::user()->isAdmin() && !empty($assignedIds) && !in_array(Auth::id(), $assignedIds)) {
            return response()->json(['error' => 'Not assigned.'], 403);
        }

        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $submission = ChecklistSubmission::firstOrCreate(
            ['checklist_task_id' => $task->id, 'date' => $today],
            ['user_id' => Auth::id(), 'status' => 'completed']
        );

        // If reverted, mark as completed again (resubmit)
        if ($submission->status === 'pending') {
            $submission->status = 'completed';
            $submission->save();
        }

        // Append note (or replace)
        $submission->update(['notes' => $request->notes]);

        ChecklistSubmissionLog::create([
            'checklist_submission_id' => $submission->id,
            'user_id'                 => Auth::id(),
            'action'                  => 'note_sent',
            'notes_snapshot'          => \Str::limit($request->notes, 200),
            'file_count'              => 0,
            'created_at'              => now(),
        ]);

        return response()->json([
            'success'  => true,
            'notes'    => $request->notes,
            'sent_by'  => Auth::user()->name,
            'sent_at'  => now()->format('g:i A'),
        ]);
    }
}
