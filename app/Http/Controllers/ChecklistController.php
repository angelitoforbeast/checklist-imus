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
    /**
     * Helper: filter tasks visible to the current user.
     */
    private function filterTasksForUser($tasks, $user)
    {
        if ($user->isAdmin()) return $tasks;

        return $tasks->filter(function ($task) use ($user) {
            $assignedIds = $task->assignedUsers->pluck('id')->toArray();
            return empty($assignedIds) || in_array($user->id, $assignedIds);
        })->values();
    }

    /**
     * Helper: filter out tasks not scheduled for the given date.
     */
    private function filterBySchedule($tasks, string $date)
    {
        return $tasks->filter(fn($t) => $t->isScheduledFor($date))->values();
    }

    /**
     * Helper: filter out completed "once" tasks from previous days.
     */
    private function filterCompletedOnceTasks($tasks, string $today)
    {
        $onceTaskIds = $tasks->where('frequency', 'once')->pluck('id');
        if ($onceTaskIds->isEmpty()) return $tasks;

        $completedOnceIds = ChecklistSubmission::whereIn('checklist_task_id', $onceTaskIds)
            ->where('status', 'completed')
            ->where('date', '<', $today)
            ->pluck('checklist_task_id')
            ->unique();

        if ($completedOnceIds->isEmpty()) return $tasks;

        return $tasks->reject(fn($t) => $completedOnceIds->contains($t->id))->values();
    }

    /**
     * Helper: load submissions for a date.
     * For GROUP mode: keyed by task_id (one submission per task).
     * For INDIVIDUAL mode: grouped by task_id (multiple submissions per task).
     * Returns both collections for the views to use.
     */
    private function loadSubmissions($taskIds, string $date, $user = null)
    {
        $query = ChecklistSubmission::with(['user', 'files', 'logs.user'])
            ->where('date', $date)
            ->whereIn('checklist_task_id', $taskIds);

        $allSubmissions = $query->get();

        // For backward compatibility: keyed by task_id (first/only submission per task)
        // For group mode tasks, there's only one submission per task
        // For individual mode tasks, we need all submissions grouped
        $byTask = $allSubmissions->groupBy('checklist_task_id');

        // Legacy format: one submission per task (for group mode)
        $singleByTask = collect();
        foreach ($byTask as $taskId => $subs) {
            $singleByTask[$taskId] = $subs->first();
        }

        return [$singleByTask, $byTask];
    }

    /**
     * Helper: determine if a task is "done" considering submission mode.
     * GROUP: done if any submission exists with status=completed
     * INDIVIDUAL: done if ALL assigned users have a completed submission
     */
    private function isTaskDone(ChecklistTask $task, $submissionsForTask): bool
    {
        if (!$submissionsForTask || $submissionsForTask->isEmpty()) return false;

        // Announcements are always individual - each assigned user must acknowledge
        if ($task->submission_mode === 'individual' || $task->type === 'announcement') {
            $assignedIds = $task->assignedUsers->pluck('id')->toArray();
            if (empty($assignedIds)) {
                // No specific assignment = treat as group
                return $submissionsForTask->where('status', 'completed')->isNotEmpty();
            }
            // Check each assigned user has a completed submission
            foreach ($assignedIds as $userId) {
                $userSub = $submissionsForTask->firstWhere('user_id', $userId);
                if (!$userSub || $userSub->status !== 'completed') return false;
            }
            return true;
        }

        // Group mode
        return $submissionsForTask->where('status', 'completed')->isNotEmpty();
    }

    // =========================================================================
    // USER VIEW
    // =========================================================================

    public function index()
    {
        $today = now()->toDateString();
        $user  = Auth::user();

        $allTasks = ChecklistTask::with(['assignedUsers', 'referenceFiles'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $tasks = $this->filterTasksForUser($allTasks, $user);
        $tasks = $this->filterBySchedule($tasks, $today);
        $tasks = $this->filterCompletedOnceTasks($tasks, $today);

        // Load all submissions for today
        [$submissionsByTask, $allSubmissionsByTask] = $this->loadSubmissions($tasks->pluck('id'), $today, $user);

        // For individual mode / announcement tasks, the current user's submission
        $userSubmissionsByTask = collect();
        foreach ($tasks as $task) {
            if ($task->submission_mode === 'individual' || $task->type === 'announcement') {
                $taskSubs = $allSubmissionsByTask->get($task->id, collect());
                $userSub = $taskSubs->firstWhere('user_id', $user->id);
                if ($userSub) {
                    $userSubmissionsByTask[$task->id] = $userSub;
                }
            } else {
                // Group mode: use the single submission
                if ($submissionsByTask->has($task->id)) {
                    $userSubmissionsByTask[$task->id] = $submissionsByTask[$task->id];
                }
            }
        }

        // Calculate done count based on submission mode
        $doneCount = 0;
        foreach ($tasks as $task) {
            $taskSubs = $allSubmissionsByTask->get($task->id, collect());
            if ($task->submission_mode === 'individual' || $task->type === 'announcement') {
                $userSub = $taskSubs->firstWhere('user_id', $user->id);
                if ($userSub && $userSub->status === 'completed') $doneCount++;
            } else {
                if ($taskSubs->where('status', 'completed')->isNotEmpty()) $doneCount++;
            }
        }
        $totalTasks = $tasks->count();

        // Load admin comments for today
        $commentsByTask = \App\Models\ChecklistTaskComment::with('user')
            ->where('date', $today)
            ->orderBy('created_at')
            ->get()
            ->groupBy('checklist_task_id');

        return view('checklist.index', compact(
            'tasks', 'userSubmissionsByTask', 'submissionsByTask',
            'today', 'doneCount', 'totalTasks', 'commentsByTask'
        ));
    }

    /**
     * Lightweight polling endpoint for user view.
     */
    public function pollStatus()
    {
        $today = now()->toDateString();
        $user  = Auth::user();

        $allTasks = ChecklistTask::with('assignedUsers')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $tasks = $this->filterTasksForUser($allTasks, $user);
        $tasks = $this->filterBySchedule($tasks, $today);
        $tasks = $this->filterCompletedOnceTasks($tasks, $today);

        [$submissionsByTask, $allSubmissionsByTask] = $this->loadSubmissions($tasks->pluck('id'), $today, $user);

        $comments = \App\Models\ChecklistTaskComment::where('date', $today)
            ->get()
            ->groupBy('checklist_task_id');

        $result = [];
        $doneCount = 0;
        foreach ($tasks as $task) {
            $taskSubs = $allSubmissionsByTask->get($task->id, collect());

            if ($task->submission_mode === 'individual' || $task->type === 'announcement') {
                $userSub = $taskSubs->firstWhere('user_id', $user->id);
                $status = $userSub ? $userSub->status : 'not_started';
                $started = $userSub && $userSub->started_at ? true : false;
                $fileCount = $userSub ? $userSub->files->count() : 0;
                if ($status === 'completed') $doneCount++;
            } else {
                $sub = $submissionsByTask->get($task->id);
                $status = $sub ? $sub->status : 'not_started';
                $started = $sub && $sub->started_at ? true : false;
                $fileCount = $sub ? $sub->files->count() : 0;
                if ($status === 'completed') $doneCount++;
            }

            $result[] = [
                'task_id'       => $task->id,
                'status'        => $status,
                'started'       => $started,
                'file_count'    => $fileCount,
                'comment_count' => isset($comments[$task->id]) ? $comments[$task->id]->count() : 0,
            ];
        }

        return response()->json([
            'tasks'      => $result,
            'done_count' => $doneCount,
            'total'      => count($result),
        ]);
    }

    // =========================================================================
    // POLL CONVERSATION (real-time updates inside focus mode)
    // =========================================================================

    public function pollConversation(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();
        $user = Auth::user();

        // Get the submission for this task
        $lookupKey = ['checklist_task_id' => $task->id, 'date' => $today];
        if ($task->submission_mode === 'individual' || $task->type === 'announcement') {
            $lookupKey['user_id'] = $user->id;
        }

        $sub = ChecklistSubmission::with(['files', 'logs.user', 'user'])->where($lookupKey)->first();

        $messages = [];

        if ($sub) {
            // Started event
            if ($sub->started_at) {
                $messages[] = [
                    'type' => 'event',
                    'text' => ($sub->user->name ?? 'Someone') . ' started this task',
                    'time' => $sub->started_at->format('g:i A'),
                    'ts' => $sub->started_at->timestamp,
                ];
            }

            // Photos
            foreach ($sub->files as $f) {
                $messages[] = [
                    'type' => 'photo',
                    'url' => \Storage::url($f->file_path),
                    'name' => $f->file_original_name,
                    'time' => $f->created_at->format('g:i A'),
                    'ts' => $f->created_at->timestamp,
                    'by' => $sub->user->name ?? 'Unknown',
                    'isVideo' => $f->isVideo(),
                ];
            }

            // Notes
            foreach ($sub->logs->where('action', 'note_sent') as $log) {
                if ($log->notes_snapshot) {
                    $messages[] = [
                        'type' => 'note',
                        'text' => $log->notes_snapshot,
                        'time' => $log->created_at->format('g:i A'),
                        'ts' => $log->created_at->timestamp,
                        'by' => $log->user->name ?? $sub->user->name ?? 'Unknown',
                    ];
                }
            }

            // Reverted events
            foreach ($sub->logs->where('action', 'reverted') as $log) {
                $messages[] = [
                    'type' => 'revert',
                    'text' => ($log->user->name ?? 'Admin') . ' reverted this task to pending',
                    'time' => $log->created_at->format('g:i A'),
                    'ts' => $log->created_at->timestamp,
                    'by' => $log->user->name ?? 'Admin',
                ];
            }

            // Submitted events
            foreach ($sub->logs->whereIn('action', ['submitted', 'updated']) as $log) {
                $messages[] = [
                    'type' => 'event',
                    'text' => ($log->user->name ?? 'Someone') . ' ' . ($log->action === 'submitted' ? 'marked as done' : 're-submitted'),
                    'time' => $log->created_at->format('g:i A'),
                    'ts' => $log->created_at->timestamp,
                ];
            }
        }

        // Admin comments
        $comments = \App\Models\ChecklistTaskComment::where('checklist_task_id', $task->id)
            ->where('date', $today)
            ->with('user')
            ->get();

        foreach ($comments as $c) {
            $messages[] = [
                'type' => 'admin_comment',
                'text' => $c->message,
                'time' => $c->created_at->format('g:i A'),
                'ts' => $c->created_at->timestamp,
                'by' => $c->user->name ?? 'Admin',
                'initial' => strtoupper(substr($c->user->name ?? 'A', 0, 1)),
            ];
        }

        // Sort by timestamp
        usort($messages, fn($a, $b) => $a['ts'] - $b['ts']);

        return response()->json([
            'messages' => $messages,
            'status' => $sub ? $sub->status : 'not_started',
            'started' => $sub && $sub->started_at ? true : false,
        ]);
    }

    // =========================================================================
    // REPORT
    // =========================================================================

    public function report(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $roleFilter = $request->query('role', '');

        try {
            $dateObj = Carbon::parse($date);
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

        // Include soft-deleted tasks that have submissions for this date (even for today)
        $missingIds = $submissionsByTask->keys()->diff($tasks->pluck('id'));
        if ($missingIds->isNotEmpty()) {
            $extra = ChecklistTask::withTrashed()
                ->with('assignedUsers')
                ->whereIn('id', $missingIds)
                ->get();
            $tasks = $tasks->merge($extra)->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
        }

        $doneCount  = $submissionsByTask->where('status', 'completed')->count();
        $totalTasks = $tasks->count();

        $prevDate = $dateObj->copy()->subDay()->toDateString();
        $nextDate = $dateObj->copy()->addDay()->toDateString();

        $roles = \App\Models\Role::orderBy('name')->get();
        if ($roleFilter) {
            $roleUserIds = User::where('role_id', $roleFilter)->pluck('id')->toArray();
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

        return view('checklist.report', compact(
            'tasks', 'submissionsByTask',
            'doneCount', 'totalTasks',
            'dateObj', 'prevDate', 'nextDate', 'isToday',
            'roles', 'roleFilter', 'commentsByTask'
        ));
    }

    // =========================================================================
    // CONVERSATIONS (ADMIN)
    // =========================================================================

    public function conversations(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $roleFilter = $request->query('role', '');

        try {
            $dateObj = Carbon::parse($date);
        } catch (\Exception $e) {
            $dateObj = now();
        }

        $isToday = $dateObj->isToday();

        if ($isToday) {
            $tasks = ChecklistTask::with(['assignedUsers', 'referenceFiles'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } else {
            $endOfDay = $dateObj->copy()->endOfDay();
            $tasks = ChecklistTask::withTrashed()
                ->with(['assignedUsers', 'referenceFiles'])
                ->whereDate('created_at', '<=', $dateObj->toDateString())
                ->where(function ($q) use ($endOfDay) {
                    $q->whereNull('deleted_at')
                      ->orWhere('deleted_at', '>', $endOfDay);
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        // Load ALL submissions for the date (for individual mode we need per-user)
        $allSubmissions = ChecklistSubmission::with(['user', 'files', 'logs.user'])
            ->where('date', $dateObj->toDateString())
            ->get();

        $allSubmissionsByTask = $allSubmissions->groupBy('checklist_task_id');

        // Legacy single-submission-per-task (for group mode backward compat)
        $submissionsByTask = collect();
        foreach ($allSubmissionsByTask as $taskId => $subs) {
            $submissionsByTask[$taskId] = $subs->first();
        }

        // Include soft-deleted tasks that have submissions for this date (even for today)
        $missingIds = $submissionsByTask->keys()->diff($tasks->pluck('id'));
        if ($missingIds->isNotEmpty()) {
            $extra = ChecklistTask::withTrashed()
                ->with(['assignedUsers', 'referenceFiles'])
                ->whereIn('id', $missingIds)
                ->get();
            $tasks = $tasks->merge($extra)->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
        }

        // Calculate doneCount using isTaskDone for proper individual/announcement handling
        $doneCount = 0;
        foreach ($tasks as $task) {
            $taskSubs = $allSubmissionsByTask->get($task->id, collect());
            if ($this->isTaskDone($task, $taskSubs)) $doneCount++;
        }
        $totalTasks = $tasks->count();

        $prevDate = $dateObj->copy()->subDay()->toDateString();
        $nextDate = $dateObj->copy()->addDay()->toDateString();

        $roles = \App\Models\Role::orderBy('name')->get();
        if ($roleFilter) {
            $roleUserIds = User::where('role_id', $roleFilter)->pluck('id')->toArray();
            $tasks = $tasks->filter(function ($task) use ($roleUserIds) {
                $assignedIds = $task->assignedUsers->pluck('id')->toArray();
                return empty($assignedIds) || !empty(array_intersect($assignedIds, $roleUserIds));
            })->values();
            $doneCount = $tasks->filter(fn($t) => $this->isTaskDone($t, $allSubmissionsByTask->get($t->id, collect())))->count();
            $totalTasks = $tasks->count();
        }

        $commentsByTask = \App\Models\ChecklistTaskComment::with('user')
            ->where('date', $dateObj->toDateString())
            ->orderBy('created_at')
            ->get()
            ->groupBy('checklist_task_id');

        // Sort tasks: latest submitted first
        $tasks = $tasks->sort(function ($a, $b) use ($submissionsByTask) {
            $subA = $submissionsByTask->get($a->id);
            $subB = $submissionsByTask->get($b->id);
            $hasA = $subA !== null;
            $hasB = $subB !== null;
            if ($hasA && !$hasB) return -1;
            if (!$hasA && $hasB) return 1;
            if ($hasA && $hasB) {
                return $subB->updated_at->timestamp - $subA->updated_at->timestamp;
            }
            return ($a->sort_order ?? 0) - ($b->sort_order ?? 0);
        })->values();

        return view('checklist.conversations', compact(
            'tasks', 'submissionsByTask', 'allSubmissionsByTask',
            'doneCount', 'totalTasks',
            'dateObj', 'prevDate', 'nextDate', 'isToday',
            'roles', 'roleFilter', 'commentsByTask'
        ));
    }

    // =========================================================================
    // MANAGE TASKS
    // =========================================================================

    public function manage()
    {
        $allTasks = ChecklistTask::with(['assignedUsers', 'referenceFiles'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $allUsers = User::orderBy('name')->get();

        return view('checklist.manage', compact('allTasks', 'allUsers'));
    }

    // =========================================================================
    // TASK START (new: conversation-first flow)
    // =========================================================================

    /**
     * AJAX: User starts a task — creates submission with started_at timestamp.
     */
    public function startTask(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();
        $user = Auth::user();

        $assignedIds = $task->assignedUsers()->pluck('users.id')->toArray();
        if (!$user->isAdmin() && !empty($assignedIds) && !in_array($user->id, $assignedIds)) {
            return response()->json(['error' => 'Not assigned.'], 403);
        }

        // For individual mode: each user gets their own submission
        // For group mode: one submission per task
        $lookupKey = ['checklist_task_id' => $task->id, 'date' => $today];
        if ($task->submission_mode === 'individual') {
            $lookupKey['user_id'] = $user->id;
        }

        $submission = ChecklistSubmission::firstOrCreate(
            $lookupKey,
            ['user_id' => $user->id, 'status' => 'pending', 'started_at' => now()]
        );

        // If already exists but not started yet, set started_at
        if (!$submission->started_at) {
            $submission->started_at = now();
            $submission->save();
        }

        // Log the start event
        ChecklistSubmissionLog::create([
            'checklist_submission_id' => $submission->id,
            'user_id'                 => $user->id,
            'action'                  => 'started',
            'notes_snapshot'          => null,
            'file_count'              => 0,
            'created_at'              => now(),
        ]);

        return response()->json([
            'success'    => true,
            'started_at' => $submission->started_at->format('g:i A'),
            'user'       => $user->name,
        ]);
    }

    // =========================================================================
    // SUBMIT (updated for individual mode)
    // =========================================================================

    public function submit(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();
        $user = Auth::user();

        $assignedIds = $task->assignedUsers()->pluck('users.id')->toArray();
        if (!$user->isAdmin() && !empty($assignedIds) && !in_array($user->id, $assignedIds)) {
            return back()->with('error', 'You are not assigned to this task.');
        }

        // Handle announcement type — just acknowledge
        if ($task->type === 'announcement') {
            $lookupKey = ['checklist_task_id' => $task->id, 'date' => $today];
            // Announcements are always individual
            $lookupKey['user_id'] = $user->id;

            $submission = ChecklistSubmission::firstOrCreate(
                $lookupKey,
                ['user_id' => $user->id, 'status' => 'completed']
            );

            if ($submission->status !== 'completed') {
                $submission->status = 'completed';
                $submission->save();
            }

            ChecklistSubmissionLog::create([
                'checklist_submission_id' => $submission->id,
                'user_id'                 => $user->id,
                'action'                  => 'acknowledged',
                'notes_snapshot'          => null,
                'file_count'              => 0,
                'created_at'              => now(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => "'{$task->title}' acknowledged!"]);
            }

            return back()->with('success', "'{$task->title}' acknowledged!");
        }

        $imageMimes = 'jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm,3gp';
        $anyMimes   = 'jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm,3gp,pdf,doc,docx,xls,xlsx,csv';

        // Determine lookup key based on submission mode
        $lookupKey = ['checklist_task_id' => $task->id, 'date' => $today];
        if ($task->submission_mode === 'individual') {
            $lookupKey['user_id'] = $user->id;
        }

        $existing = ChecklistSubmission::with('files')->where($lookupKey)->first();

        $isNew            = $existing === null;
        $hasExistingFiles = $existing && ($existing->files->count() > 0 || $existing->file_path);

        $rules = ['notes' => 'nullable|string|max:2000'];

        if ($task->type === 'photo') {
            $rules['files']   = $hasExistingFiles ? 'nullable|array|max:10' : 'required|array|min:1|max:10';
            $rules['files.*'] = "file|mimes:{$imageMimes}";
        } elseif ($task->type === 'photo_note') {
            $rules['files']   = $hasExistingFiles ? 'nullable|array|max:10' : 'required|array|min:1|max:10';
            $rules['files.*'] = "file|mimes:{$imageMimes}";
            $rules['notes']   = 'nullable|string|max:2000';
        } elseif ($task->type === 'both') {
            $rules['notes']   = 'required|string|max:2000';
            $rules['files']   = $hasExistingFiles ? 'nullable|array|max:10' : 'required|array|min:1|max:10';
            $rules['files.*'] = "file|mimes:{$imageMimes}";
        } elseif ($task->type === 'any') {
            $rules['files']   = 'nullable|array|max:10';
            $rules['files.*'] = "file|mimes:{$anyMimes}";
        }

        // For AJAX "mark as done" calls, relax rules since files are already uploaded
        if ($request->ajax() || $request->wantsJson()) {
            // Only validate notes if provided
            $rules = ['notes' => 'nullable|string|max:2000'];
            // For 'both' type, check notes exist in submission logs if not in request
            if ($task->type === 'both' && !$request->notes && $existing) {
                $hasNotes = $existing->logs()->where('action', 'note_sent')->exists();
                if (!$hasNotes) {
                    return response()->json(['success' => false, 'error' => 'Notes are required for this task.'], 422);
                }
            }
            // Check files exist for photo-required tasks
            if (in_array($task->type, ['photo', 'photo_note', 'both']) && !$hasExistingFiles && !$request->hasFile('files')) {
                return response()->json(['success' => false, 'error' => 'At least one photo is required.'], 422);
            }
        } else {
            $request->validate($rules);

            if (in_array($task->type, ['photo', 'photo_note', 'both']) && !$hasExistingFiles && !$request->hasFile('files')) {
                return back()->withErrors(['files' => 'At least one photo is required.'])->withInput();
            }
        }

        $submission = ChecklistSubmission::updateOrCreate(
            $lookupKey,
            ['notes' => $request->notes, 'user_id' => $isNew ? $user->id : $existing->user_id, 'status' => 'completed']
        );

        // Set started_at if not already set
        if (!$submission->started_at) {
            $submission->started_at = now();
            $submission->save();
        }

        $fileCount = $request->hasFile('files') ? count($request->file('files')) : ($submission->files()->count());
        ChecklistSubmissionLog::create([
            'checklist_submission_id' => $submission->id,
            'user_id'                 => $user->id,
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "'{$task->title}' submitted!"]);
        }

        return back()->with('success', "'{$task->title}' submitted!");
    }

    // =========================================================================
    // DELETE SUBMISSION
    // =========================================================================

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

    // =========================================================================
    // REVERT SUBMISSION
    // =========================================================================

    public function revertSubmission(ChecklistSubmission $submission)
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403);
        }

        $submission->status = 'pending';
        $submission->save();

        $submission->logs()->create([
            'user_id' => Auth::id(),
            'action' => 'reverted',
            'notes_snapshot' => $submission->notes,
            'file_count' => $submission->files->count(),
        ]);

        return back()->with('success', 'Task reverted to pending. User needs to re-submit.');
    }

    // =========================================================================
    // ADMIN COMMENT
    // =========================================================================

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

        return response()->json(['success' => true, 'comment_id' => $comment->id]);
    }

    // =========================================================================
    // DELETE FILE
    // =========================================================================

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

    // =========================================================================
    // STORE TASK
    // =========================================================================

    public function storeTask(Request $request)
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string|max:5000',
            'instructions'      => 'nullable|string|max:5000',
            'type'              => 'required|in:photo,note,any,both,photo_note,announcement',
            'required_photos'   => 'nullable|integer|min:1|max:50',
            'ai_prompt'         => 'nullable|string|max:2000',
            'approval_prompt'   => 'nullable|string|max:2000',
            'task_time'         => 'nullable|date_format:H:i',
            'frequency'         => 'nullable|in:daily,once,weekly,monthly,custom',
            'submission_mode'   => 'nullable|in:group,individual',
            'schedule_days'     => 'nullable|array',
            'schedule_days.*'   => 'integer|min:0|max:31',
            'schedule_dates'    => 'nullable|array',
            'schedule_dates.*'  => 'date',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'reference_image'   => 'nullable|image',
            'reference_files'   => 'nullable|array',
            'reference_files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm,3gp',
        ]);

        // Announcements are always individual
        if (($validated['type'] ?? '') === 'announcement') {
            $validated['submission_mode'] = 'individual';
        }

        // Default required_photos to 1 for photo-related types
        if (in_array($validated['type'] ?? '', ['photo', 'photo_note', 'both'])) {
            $validated['required_photos'] = max(1, (int)($validated['required_photos'] ?? 1));
        } else {
            $validated['required_photos'] = 1;
        }

        $imagePath = null;
        if ($request->hasFile('reference_image')) {
            $imagePath = $request->file('reference_image')->store('task-references', 'public');
        }

        $task = ChecklistTask::create([
            ...$validated,
            'reference_image'  => $imagePath,
            'frequency'        => $validated['frequency'] ?? 'daily',
            'submission_mode'  => $validated['submission_mode'] ?? 'group',
            'schedule_days'    => $validated['schedule_days'] ?? null,
            'schedule_dates'   => $validated['schedule_dates'] ?? null,
            'sort_order'       => (ChecklistTask::max('sort_order') ?? 0) + 1,
            'is_active'        => true,
        ]);

        if ($request->hasFile('reference_files')) {
            foreach ($request->file('reference_files') as $i => $file) {
                $task->referenceFiles()->create([
                    'file_path'          => $file->store('task-references', 'public'),
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime'          => $file->getMimeType(),
                    'sort_order'         => $i,
                ]);
            }
        }

        $userIds = array_filter((array) $request->input('assigned_users', []));
        $task->assignedUsers()->sync($userIds);

        return back()->with('success', 'Task added!');
    }

    // =========================================================================
    // UPDATE TASK
    // =========================================================================

    public function updateTask(Request $request, ChecklistTask $task)
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string|max:5000',
            'instructions'      => 'nullable|string|max:5000',
            'type'              => 'required|in:photo,note,any,both,photo_note,announcement',
            'required_photos'   => 'nullable|integer|min:1|max:50',
            'is_active'         => 'boolean',
            'ai_prompt'         => 'nullable|string|max:2000',
            'approval_prompt'   => 'nullable|string|max:2000',
            'task_time'         => 'nullable|date_format:H:i',
            'frequency'         => 'nullable|in:daily,once,weekly,monthly,custom',
            'submission_mode'   => 'nullable|in:group,individual',
            'schedule_days'     => 'nullable|array',
            'schedule_days.*'   => 'integer|min:0|max:31',
            'schedule_dates'    => 'nullable|array',
            'schedule_dates.*'  => 'date',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'reference_image'   => 'nullable|image',
            'reference_files'   => 'nullable|array',
            'reference_files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm,3gp',
        ]);

        if (($validated['type'] ?? '') === 'announcement') {
            $validated['submission_mode'] = 'individual';
        }

        // Default required_photos to 1 for photo-related types
        if (in_array($validated['type'] ?? '', ['photo', 'photo_note', 'both'])) {
            $validated['required_photos'] = max(1, (int)($validated['required_photos'] ?? 1));
        } else {
            $validated['required_photos'] = 1;
        }

        if ($request->hasFile('reference_image')) {
            if ($task->reference_image) {
                Storage::disk('public')->delete($task->reference_image);
            }
            $validated['reference_image'] = $request->file('reference_image')->store('task-references', 'public');
        }
        if ($request->boolean('remove_reference_image')) {
            if ($task->reference_image) {
                Storage::disk('public')->delete($task->reference_image);
            }
            $validated['reference_image'] = null;
        }

        if ($request->input('delete_reference_files')) {
            $deleteIds = array_filter((array) $request->input('delete_reference_files'));
            foreach ($task->referenceFiles()->whereIn('id', $deleteIds)->get() as $rf) {
                Storage::disk('public')->delete($rf->file_path);
                $rf->delete();
            }
        }

        if ($request->hasFile('reference_files')) {
            $nextOrder = ($task->referenceFiles()->max('sort_order') ?? -1) + 1;
            foreach ($request->file('reference_files') as $i => $file) {
                $task->referenceFiles()->create([
                    'file_path'          => $file->store('task-references', 'public'),
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime'          => $file->getMimeType(),
                    'sort_order'         => $nextOrder + $i,
                ]);
            }
        }

        $task->update($validated);

        $userIds = array_filter((array) $request->input('assigned_users', []));
        $task->assignedUsers()->sync($userIds);

        return back()->with('success', 'Task updated!');
    }

    // =========================================================================
    // DESTROY TASK
    // =========================================================================

    public function destroyTask(ChecklistTask $task)
    {
        $task->delete();
        return back()->with('success', 'Task deleted.');
    }

    // =========================================================================
    // REORDER TASKS
    // =========================================================================

    public function reorderTasks(Request $request)
    {
        foreach ($request->input('order', []) as $index => $id) {
            ChecklistTask::where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // AI ANALYSIS
    // =========================================================================

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

        $prompt  = "You are a strict quality inspector reviewing a task submission.\n\n";
        $prompt .= "Task: {$task->title}\n";
        if ($task->description) {
            $prompt .= "Description: {$task->description}\n";
        }
        if ($submission->notes) {
            $prompt .= "Staff Notes: {$submission->notes}\n";
        }

        if ($task->approval_prompt) {
            $prompt .= "\nApproval Criteria: {$task->approval_prompt}";
        }

        $prompt .= "\n\nBased on the submission (images + notes), respond with EXACTLY one of:\n";
        $prompt .= "APPROVED - if the task meets all criteria\n";
        $prompt .= "REJECTED - if the task does NOT meet criteria\n\n";
        $prompt .= "Then provide a brief 1-2 sentence explanation.";

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
            'max_tokens' => 256,
            'messages'   => [
                ['role' => 'user', 'content' => $content],
            ],
        ]);

        if ($response->successful()) {
            $resultText = $response->json('choices.0.message.content');
            $approved   = str_starts_with(strtoupper(trim($resultText)), 'APPROVED');

            ChecklistAnalysisLog::create([
                'submission_id'   => $submission->id,
                'user_id'         => Auth::id(),
                'log_type'        => 'approval',
                'prompt_used'     => $prompt,
                'analysis_result' => $resultText,
            ]);

            return response()->json([
                'result'     => $resultText,
                'approved'   => $approved,
                'checked_by' => Auth::user()?->name,
                'checked_at' => now()->format('M j, h:i A'),
            ]);
        }

        return response()->json([
            'error' => 'Approval check failed (' . $response->status() . ').',
        ], 500);
    }

    public function getApprovalLogs(ChecklistSubmission $submission)
    {
        $logs = $submission->approvalLogs()->with('user')->get()->map(fn($log) => [
            'id'          => $log->id,
            'result'      => $log->analysis_result,
            'prompt_used' => $log->prompt_used,
            'user'        => $log->user?->name ?? 'Unknown',
            'created_at'  => $log->created_at->format('M j, Y g:i A'),
        ]);

        return response()->json(['logs' => $logs]);
    }

    // =========================================================================
    // UPLOAD PHOTO (AJAX - conversation-style)
    // =========================================================================

    public function uploadPhoto(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();
        $user = Auth::user();

        $assignedIds = $task->assignedUsers()->pluck('users.id')->toArray();
        if (!$user->isAdmin() && !empty($assignedIds) && !in_array($user->id, $assignedIds)) {
            return response()->json(['error' => 'Not assigned.'], 403);
        }

        $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm,3gp',
        ]);

        // Determine lookup key based on submission mode
        $lookupKey = ['checklist_task_id' => $task->id, 'date' => $today];
        if ($task->submission_mode === 'individual') {
            $lookupKey['user_id'] = $user->id;
        }

        $submission = ChecklistSubmission::firstOrCreate(
            $lookupKey,
            ['user_id' => $user->id, 'status' => 'completed']
        );

        // If reverted, mark as completed again
        if ($submission->status === 'pending') {
            $submission->status = 'completed';
            $submission->save();
        }

        // Set started_at if not already set
        if (!$submission->started_at) {
            $submission->started_at = now();
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
            'user_id'                 => $user->id,
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
            'uploaded_by' => $user->name,
            'uploaded_at' => now()->format('g:i A'),
        ]);
    }

    // =========================================================================
    // SEND NOTE (AJAX - conversation-style)
    // =========================================================================

    public function sendNote(Request $request, ChecklistTask $task)
    {
        $today = now()->toDateString();
        $user = Auth::user();

        $assignedIds = $task->assignedUsers()->pluck('users.id')->toArray();
        if (!$user->isAdmin() && !empty($assignedIds) && !in_array($user->id, $assignedIds)) {
            return response()->json(['error' => 'Not assigned.'], 403);
        }

        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        // Determine lookup key based on submission mode
        $lookupKey = ['checklist_task_id' => $task->id, 'date' => $today];
        if ($task->submission_mode === 'individual') {
            $lookupKey['user_id'] = $user->id;
        }

        $submission = ChecklistSubmission::firstOrCreate(
            $lookupKey,
            ['user_id' => $user->id, 'status' => 'completed']
        );

        // If reverted, mark as completed again
        if ($submission->status === 'pending') {
            $submission->status = 'completed';
            $submission->save();
        }

        // Set started_at if not already set
        if (!$submission->started_at) {
            $submission->started_at = now();
            $submission->save();
        }

        // Append note (keep in submission.notes for backward compat, but logs are the real source)
        $submission->update(['notes' => $request->notes]);

        ChecklistSubmissionLog::create([
            'checklist_submission_id' => $submission->id,
            'user_id'                 => $user->id,
            'action'                  => 'note_sent',
            'notes_snapshot'          => \Str::limit($request->notes, 200),
            'file_count'              => 0,
            'created_at'              => now(),
        ]);

        return response()->json([
            'success'  => true,
            'notes'    => $request->notes,
            'sent_by'  => $user->name,
            'sent_at'  => now()->format('g:i A'),
        ]);
    }
}
