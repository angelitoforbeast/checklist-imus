<x-layout>
  @section('title', 'Conversations')

  <meta name="csrf-token" content="{{ csrf_token() }}">

  <div x-data="{
    focusTask: null,
    showCompleted: false,
    commentText: '',
    sending: false,
    activeUserTab: {},

    async sendComment(taskId) {
      if (!this.commentText.trim() || this.sending) return;
      this.sending = true;
      try {
        const res = await fetch('/checklist/task/' + taskId + '/send-comment', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
          },
          body: JSON.stringify({ message: this.commentText, date: '{{ $dateObj->toDateString() }}' })
        });
        if (res.ok) {
          const chatArea = document.getElementById('admin-chat-' + taskId);
          if (chatArea) {
            const bubble = document.createElement('div');
            bubble.className = 'flex justify-end mb-3';
            bubble.innerHTML = `
              <div class='max-w-[80%]'>
                <div class='bg-blue-500 text-white rounded-2xl rounded-br-md px-4 py-2.5'>
                  <p class='text-sm'>${this.commentText}</p>
                </div>
                <p class='text-[10px] text-gray-400 mt-1 text-right'>Admin · just now</p>
              </div>`;
            chatArea.appendChild(bubble);
            chatArea.scrollTop = chatArea.scrollHeight;
          }
          this.commentText = '';
        }
      } catch(e) {}
      this.sending = false;
    }
  }" class="min-h-screen bg-gray-50">

    {{-- ===== HEADER ===== --}}
    <div class="sticky top-0 z-40 bg-white border-b border-gray-100 shadow-sm">
      <div class="max-w-lg mx-auto">
        <div class="flex items-center justify-between">
          <a href="{{ route('checklist.conversations', ['date' => $prevDate, 'role' => $roleFilter]) }}"
             class="flex items-center px-3 py-3.5 text-gray-400 hover:text-gray-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </a>
          <div class="text-center py-3">
            <p class="text-sm font-bold text-gray-800">{{ $dateObj->format('l') }}</p>
            <p class="text-xs text-gray-400">{{ $dateObj->format('M j, Y') }}</p>
          </div>
          <a href="{{ route('checklist.conversations', ['date' => $nextDate, 'role' => $roleFilter]) }}"
             class="flex items-center px-3 py-3.5 transition {{ $isToday ? 'text-gray-200 pointer-events-none' : 'text-gray-400 hover:text-gray-700' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </a>
        </div>

        <div class="flex items-center justify-between px-4 pb-3 gap-3">
          <form method="GET" action="{{ route('checklist.conversations') }}" class="flex items-center gap-2">
            <input type="hidden" name="date" value="{{ $dateObj->toDateString() }}">
            <select name="role" onchange="this.form.submit()"
                    class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-600 focus:outline-none cursor-pointer">
              <option value="">All Roles</option>
              @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ $roleFilter == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
              @endforeach
            </select>
          </form>

          <div class="flex items-center gap-3">
            @php $pct = $totalTasks > 0 ? round($doneCount / $totalTasks * 100) : 0; @endphp
            <div class="flex items-center gap-1.5">
              <svg class="w-7 h-7 -rotate-90" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                <circle cx="18" cy="18" r="15.9" fill="none"
                        stroke="{{ $doneCount === $totalTasks && $totalTasks > 0 ? '#22c55e' : '#3b82f6' }}"
                        stroke-width="3" stroke-dasharray="{{ $pct }}, 100" stroke-linecap="round"/>
              </svg>
              <span class="text-xs font-bold {{ $doneCount === $totalTasks && $totalTasks > 0 ? 'text-green-600' : 'text-gray-700' }}">{{ $doneCount }}/{{ $totalTasks }}</span>
            </div>
            <a href="{{ route('checklist.report', ['date' => $dateObj->toDateString(), 'role' => $roleFilter]) }}"
               class="text-xs px-2.5 py-1 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">
              Report
            </a>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== TASK LIST (main view) ===== --}}
    <div x-show="!focusTask" class="max-w-lg mx-auto px-4 py-4 space-y-3">

      @if($totalTasks === 0)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center">
          <p class="text-3xl mb-2">📋</p>
          <p class="text-gray-500 font-medium">No active tasks</p>
        </div>
      @else

        @php
          // For individual/announcement tasks: completed only when ALL assigned users have completed
          $pendingTasks = $tasks->filter(function($t) use ($allSubmissionsByTask) {
              $taskSubs = $allSubmissionsByTask->get($t->id, collect());
              if ($t->submission_mode === 'individual' || $t->type === 'announcement') {
                  $assignedIds = $t->assignedUsers->pluck('id')->toArray();
                  if (empty($assignedIds)) {
                      return $taskSubs->where('status', 'completed')->isEmpty();
                  }
                  foreach ($assignedIds as $uid) {
                      $userSub = $taskSubs->firstWhere('user_id', $uid);
                      if (!$userSub || $userSub->status !== 'completed') return true; // still pending
                  }
                  return false; // all done
              }
              // Group mode: check first submission
              $sub = $taskSubs->first();
              return !$sub || $sub->status !== 'completed';
          });
          $completedTasks = $tasks->filter(function($t) use ($allSubmissionsByTask) {
              $taskSubs = $allSubmissionsByTask->get($t->id, collect());
              if ($t->submission_mode === 'individual' || $t->type === 'announcement') {
                  $assignedIds = $t->assignedUsers->pluck('id')->toArray();
                  if (empty($assignedIds)) {
                      return $taskSubs->where('status', 'completed')->isNotEmpty();
                  }
                  foreach ($assignedIds as $uid) {
                      $userSub = $taskSubs->firstWhere('user_id', $uid);
                      if (!$userSub || $userSub->status !== 'completed') return false;
                  }
                  return true; // all assigned users completed
              }
              $sub = $taskSubs->first();
              return $sub && $sub->status === 'completed';
          });
        @endphp

        {{-- Pending tasks --}}
        @foreach($pendingTasks as $task)
          @php
            $sub = $submissionsByTask->get($task->id);
            $taskSubs = $allSubmissionsByTask->get($task->id) ?? collect();
            $photoCount = $taskSubs->sum(fn($s) => $s->files->filter(fn($f) => $f->isImage() || $f->isVideo())->count());
            $commentCount = ($commentsByTask->get($task->id) ?? collect())->count();
            $reverted = $sub && $sub->status === 'pending';
            $isIndividual = $task->submission_mode === 'individual';
            $isAnnouncement = $task->type === 'announcement';
            $inProgress = $sub && $sub->started_at && $sub->status !== 'completed';

            // For individual mode, count how many assigned users have submitted
            $individualDone = 0;
            $individualTotal = $task->assignedUsers->count();
            if ($isIndividual && $individualTotal > 0) {
              $individualDone = $taskSubs->where('status', 'completed')->count();
            }
          @endphp
          <div @click="focusTask = {{ $task->id }}; $nextTick(() => { const el = document.getElementById('admin-chat-{{ $task->id }}'); if(el) el.scrollTop = el.scrollHeight; })"
               class="bg-white rounded-2xl border-2 {{ $reverted ? 'border-amber-200' : 'border-blue-200' }} shadow-sm p-4 cursor-pointer hover:shadow-md transition active:scale-[0.98]">
            <div class="flex items-center justify-between">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  @if($isAnnouncement)
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-400 flex-shrink-0"></span>
                  @elseif($reverted)
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                  @elseif($inProgress)
                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 flex-shrink-0 animate-pulse"></span>
                  @else
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-400 flex-shrink-0"></span>
                  @endif
                  <p class="font-semibold text-gray-800 truncate">{{ $task->title }}</p>
                  @if($task->trashed())
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-600 flex-shrink-0">Archived</span>
                  @endif
                </div>
                @if($task->scheduled_time)
                  <p class="text-xs text-gray-400 mt-0.5 ml-5">⏰ {{ \Carbon\Carbon::parse($task->scheduled_time)->format('g:i A') }}</p>
                @endif
                @if($task->assignedUsers->count())
                  <p class="text-xs text-blue-400 mt-0.5 ml-5 truncate">→ {{ $task->assignedUsers->pluck('name')->implode(', ') }}</p>
                @endif
                @if($isIndividual && $individualTotal > 0)
                  <p class="text-xs text-gray-400 mt-0.5 ml-5">👥 {{ $individualDone }}/{{ $individualTotal }} submitted</p>
                @endif
              </div>
              <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                @if($photoCount > 0)
                  <span class="text-xs bg-blue-50 text-blue-500 px-2 py-0.5 rounded-full font-medium">📸 {{ $photoCount }}</span>
                @endif
                @if($commentCount > 0)
                  <span class="text-xs bg-blue-50 text-blue-500 px-2 py-0.5 rounded-full font-medium">💬 {{ $commentCount }}</span>
                @endif
                @if($isAnnouncement)
                  <span class="text-xs bg-purple-50 text-purple-600 px-2 py-0.5 rounded-full font-semibold">ANNOUNCE</span>
                @elseif($reverted)
                  <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full font-semibold">REVERTED</span>
                @elseif($inProgress)
                  <span class="text-xs bg-yellow-50 text-yellow-600 px-2 py-0.5 rounded-full font-semibold">IN PROGRESS</span>
                @else
                  <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full font-semibold">PENDING</span>
                @endif
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </div>
            </div>
          </div>
        @endforeach

        {{-- Completed tasks (collapsible) --}}
        @if($completedTasks->count() > 0)
          <div class="mt-4">
            <button @click="showCompleted = !showCompleted"
                    class="w-full flex items-center justify-between bg-green-50 rounded-2xl border border-green-200 px-4 py-3 text-left hover:bg-green-100 transition">
              <div class="flex items-center gap-2">
                <span class="text-green-600 font-semibold text-sm">✅ Completed ({{ $completedTasks->count() }})</span>
              </div>
              <svg :class="showCompleted ? 'rotate-180' : ''" class="w-4 h-4 text-green-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="showCompleted" x-transition class="space-y-3 mt-3">
              @foreach($completedTasks as $task)
                @php
                  $sub = $submissionsByTask->get($task->id);
                  $taskSubs = $allSubmissionsByTask->get($task->id) ?? collect();
                  $photoCount = $taskSubs->sum(fn($s) => $s->files->filter(fn($f) => $f->isImage() || $f->isVideo())->count());
                  $commentCount = ($commentsByTask->get($task->id) ?? collect())->count();
                @endphp
                <div @click="focusTask = {{ $task->id }}; $nextTick(() => { const el = document.getElementById('admin-chat-{{ $task->id }}'); if(el) el.scrollTop = el.scrollHeight; })"
                     class="bg-white rounded-2xl border border-green-200 shadow-sm p-4 cursor-pointer hover:shadow-md transition active:scale-[0.98]">
                  <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-400 flex-shrink-0"></span>
                        <p class="font-semibold text-gray-800 truncate">{{ $task->title }}</p>
                        @if($task->trashed())
                          <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 text-red-600 flex-shrink-0">Archived</span>
                        @endif
                      </div>
                      @if($sub && $sub->user)
                        <p class="text-xs text-gray-400 mt-0.5 ml-5">by {{ $sub->user->name }} · {{ $sub->created_at ? $sub->created_at->format('g:i A') : '' }}</p>
                      @endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                      @if($photoCount > 0)
                        <span class="text-xs bg-green-50 text-green-600 px-2 py-0.5 rounded-full font-medium">📸 {{ $photoCount }}</span>
                      @endif
                      @if($commentCount > 0)
                        <span class="text-xs bg-green-50 text-green-600 px-2 py-0.5 rounded-full font-medium">💬 {{ $commentCount }}</span>
                      @endif
                      <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">DONE</span>
                      <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endif

      @endif
    </div>

    {{-- ===== FOCUS MODE: Messenger Chat (Admin POV) ===== --}}
    @foreach($tasks as $task)
      @php
        $sub = $submissionsByTask->get($task->id);
        $taskSubs = $allSubmissionsByTask->get($task->id) ?? collect();
        $done = $sub && $sub->status === 'completed';
        $reverted = $sub && $sub->status === 'pending';
        $isIndividual = $task->submission_mode === 'individual';
        $isAnnouncement = $task->type === 'announcement';
        $taskComments = $commentsByTask->get($task->id) ?? collect();

        // For individual mode, build per-user data
        $userTabs = collect();
        if ($isIndividual) {
          foreach ($task->assignedUsers as $assignedUser) {
            $userSub = $taskSubs->where('user_id', $assignedUser->id)->first();
            $userTabs->push([
              'user' => $assignedUser,
              'submission' => $userSub,
              'status' => $userSub ? $userSub->status : 'not_started',
            ]);
          }
        }

        // Build timeline function (reused for group and per-user)
        // For group mode: merge all submissions into one timeline
        // For individual mode: build per-user timelines
        $buildTimeline = function($submissions, $comments = null) use ($task) {
          $timeline = collect();
          foreach ($submissions as $sub) {
            if (!$sub) continue;
            $subFiles = $sub->files ?? collect();
            $imageFiles = $subFiles->filter(fn($f) => $f->isImage() || $f->isVideo());

            // Started event
            if ($sub->started_at) {
              $timeline->push(['type' => 'event', 'text' => ($sub->user->name ?? 'User') . ' started this task', 'time' => $sub->started_at, 'user' => $sub->user]);
            }

            // Photo batches
            $currentBatch = collect();
            $lastTime = null;
            foreach ($imageFiles->sortBy('created_at') as $file) {
              $fileTime = $file->created_at;
              if ($lastTime && abs($fileTime->diffInSeconds($lastTime)) > 120) {
                if ($currentBatch->isNotEmpty()) {
                  $timeline->push(['type' => 'photo_batch', 'files' => $currentBatch, 'time' => $currentBatch->first()->created_at, 'user' => $sub->user]);
                }
                $currentBatch = collect();
              }
              $currentBatch->push($file);
              $lastTime = $fileTime;
            }
            if ($currentBatch->isNotEmpty()) {
              $timeline->push(['type' => 'photo_batch', 'files' => $currentBatch, 'time' => $currentBatch->first()->created_at, 'user' => $sub->user]);
            }

            // Notes from logs
            foreach (($sub->logs ?? collect())->where('action', 'note_sent')->sortBy('created_at') as $log) {
              $timeline->push(['type' => 'note', 'text' => $log->notes_snapshot ?? '', 'time' => $log->created_at, 'user' => $log->user]);
            }

            // Acknowledged event
            foreach (($sub->logs ?? collect())->where('action', 'acknowledged')->sortBy('created_at') as $log) {
              $timeline->push(['type' => 'event', 'text' => ($log->user->name ?? 'User') . ' acknowledged', 'time' => $log->created_at, 'user' => $log->user]);
            }

            // Submitted event
            if ($sub->status === 'completed' && $sub->updated_at) {
              $hasSubmitLog = ($sub->logs ?? collect())->where('action', 'submitted')->isNotEmpty();
              if (!$hasSubmitLog && $sub->files->count() > 0) {
                // Infer submission time from last file
                $lastFile = $sub->files->sortByDesc('created_at')->first();
                if ($lastFile) {
                  $timeline->push(['type' => 'event', 'text' => ($sub->user->name ?? 'User') . ' submitted', 'time' => $lastFile->created_at, 'user' => $sub->user]);
                }
              }
            }
          }

          // Admin comments
          if ($comments) {
            foreach ($comments as $comment) {
              $timeline->push(['type' => 'admin_comment', 'text' => $comment->message, 'time' => $comment->created_at, 'user' => $comment->user]);
            }
          }

          return $timeline->sortBy('time')->values();
        };

        // Build the main timeline
        if ($isIndividual) {
          // For individual mode, we still build a combined timeline for the main view
          $timeline = $buildTimeline($taskSubs, $taskComments);
          // Also build per-user timelines
          $userTimelines = [];
          foreach ($userTabs as $ut) {
            $userTimelines[$ut['user']->id] = $buildTimeline(
              $ut['submission'] ? collect([$ut['submission']]) : collect(),
              null // comments shown only in combined view
            );
          }
        } else {
          $timeline = $buildTimeline($taskSubs, $taskComments);
          $userTimelines = [];
        }
      @endphp

      <div x-show="focusTask === {{ $task->id }}"
           x-transition
           class="fixed inset-0 z-50 bg-gray-50 flex flex-col"
           style="display:none">

        {{-- Chat header --}}
        <div class="flex items-center gap-3 px-4 py-3 text-white flex-shrink-0" style="background-color:#1877F2">
          <button @click="focusTask = null; document.body.style.overflow = ''"
                  class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30 transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm truncate">{{ $task->title }}@if($task->trashed()) <span class="text-red-200">[Archived]</span>@endif</p>
            <p class="text-xs text-blue-100">
              @if($isAnnouncement) 📢 Announcement
              @elseif($done) ✅ Completed
              @elseif($reverted) ↩ Reverted
              @elseif($sub && $sub->started_at) 🔄 In Progress
              @else ⏳ Pending
              @endif
              @if($isIndividual) · Individual Mode @endif
              @if($sub && $sub->user) · {{ $sub->user->name }} @endif
            </p>
          </div>
          @if($sub)
            @if($done)
              <form method="POST" action="{{ route('checklist.revert-submission', $sub) }}"
                    onsubmit="return confirm('Revert to pending? User will need to re-submit.')" class="flex-shrink-0">
                @csrf
                <button type="submit" class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-full transition">↩ Revert</button>
              </form>
            @endif
            <button type="button"
                    x-data="{ resetting: false }"
                    @click="if (confirm('Are you sure you want to RESET this task? This will delete ALL photos, notes, and logs. The user will need to start from scratch.')) {
                      resetting = true;
                      fetch('{{ route('checklist.reset-submission', $sub) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                      }).then(r => r.json()).then(d => {
                        if (d.success) { window.location.reload(); }
                        else { alert('Reset failed'); resetting = false; }
                      }).catch(() => { alert('Reset failed'); resetting = false; });
                    }"
                    :disabled="resetting"
                    class="flex-shrink-0 flex items-center gap-1 text-xs px-3 py-1.5 rounded-full transition-all active:scale-95"
                    :class="resetting ? 'bg-gray-400 text-white' : 'bg-red-500/80 hover:bg-red-500 text-white'">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span x-text="resetting ? 'Resetting...' : 'Reset'"></span>
            </button>
          @endif
        </div>

        {{-- Individual mode: user tabs --}}
        @if($isIndividual && $userTabs->count() > 1)
          <div class="bg-white border-b border-gray-200 flex-shrink-0 overflow-x-auto">
            <div class="flex px-2 py-2 gap-1">
              <button @click="activeUserTab[{{ $task->id }}] = 'all'"
                      :class="(!activeUserTab[{{ $task->id }}] || activeUserTab[{{ $task->id }}] === 'all') ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600'"
                      class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition">
                All
              </button>
              @foreach($userTabs as $ut)
                <button @click="activeUserTab[{{ $task->id }}] = {{ $ut['user']->id }}"
                        :class="activeUserTab[{{ $task->id }}] === {{ $ut['user']->id }} ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition flex items-center gap-1">
                  {{ $ut['user']->name }}
                  @if($ut['status'] === 'completed')
                    <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                  @elseif($ut['submission'] && $ut['submission']->started_at)
                    <span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span>
                  @else
                    <span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
                  @endif
                </button>
              @endforeach
            </div>
          </div>
        @endif

        {{-- Reference files (if any) --}}
        @php $refFiles = $task->referenceFiles ?? collect(); @endphp
        @if($task->reference_image || $refFiles->count())
          <div x-data="{ expanded: false }" class="bg-amber-50 border-b border-amber-200 flex-shrink-0">
            <div @click="expanded = !expanded" class="px-4 py-2 flex items-center justify-between cursor-pointer">
              <span class="text-xs font-semibold text-amber-700">📌 Reference {{ $refFiles->count() > 1 ? 'Files ('.$refFiles->count().')' : 'Photo' }}</span>
              <svg :class="expanded ? 'rotate-180' : ''" class="w-3 h-3 text-amber-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div x-show="expanded" x-transition class="px-4 pb-3">
              <div class="flex flex-wrap gap-2">
                @if($task->reference_image && $refFiles->where('file_path', $task->reference_image)->isEmpty())
                  <img src="{{ Storage::url($task->reference_image) }}"
                       data-lightbox-src="{{ Storage::url($task->reference_image) }}"
                       data-lightbox-sender="Reference Photo"
                       data-lightbox-time="{{ $task->created_at->format('M d, Y') }}"
                       @click.stop="$dispatch('open-lightbox', { src: '{{ Storage::url($task->reference_image) }}', taskId: {{ $task->id }} })"
                       class="max-h-48 rounded-xl border border-amber-200 shadow-sm cursor-zoom-in hover:opacity-90 transition">
                @endif
                @foreach($refFiles as $rf)
                  @if($rf->isVideo())
                    <video src="{{ Storage::url($rf->file_path) }}" controls
                           class="max-h-48 rounded-xl border border-amber-200 shadow-sm"></video>
                  @else
                    <img src="{{ Storage::url($rf->file_path) }}"
                         data-lightbox-src="{{ Storage::url($rf->file_path) }}"
                         data-lightbox-sender="Reference Photo"
                         data-lightbox-time="{{ $task->created_at->format('M d, Y') }}"
                         @click.stop="$dispatch('open-lightbox', { src: '{{ Storage::url($rf->file_path) }}', taskId: {{ $task->id }} })"
                         class="max-h-48 rounded-xl border border-amber-200 shadow-sm cursor-zoom-in hover:opacity-90 transition">
                  @endif
                @endforeach
              </div>
            </div>
          </div>
        @endif

        {{-- Chat area --}}
        <div id="admin-chat-{{ $task->id }}" class="flex-1 overflow-y-auto px-4 py-4 space-y-3" style="background: #f0f2f5">

          {{-- Task info bubble (center) --}}
          <div class="text-center mb-4">
            <div class="inline-block bg-white rounded-xl px-4 py-2 shadow-sm border border-gray-100">
              <p class="text-xs text-gray-500 font-medium">{{ $task->title }}</p>
              @if($task->description)
                <p class="text-xs text-gray-400 mt-0.5">{{ $task->description }}</p>
              @endif
              <p class="text-[10px] text-gray-300 mt-1">
                @if($isAnnouncement) 📢 Announcement — users must acknowledge
                @elseif(in_array($task->type, ['photo','photo_note'])) 📸 Photo required
                @elseif($task->type === 'note') 📝 Note required
                @else 📎 Any submission
                @endif
                @if($isIndividual) · 👥 Individual @endif
              </p>
            </div>
          </div>

          {{-- Combined timeline (shown for group mode, or "All" tab in individual mode) --}}
          <div x-show="!activeUserTab[{{ $task->id }}] || activeUserTab[{{ $task->id }}] === 'all'">
            @if($timeline->isEmpty())
              <div class="text-center py-8">
                <p class="text-gray-400 text-sm">No messages yet</p>
                <p class="text-gray-300 text-xs mt-1">Waiting for user submission...</p>
              </div>
            @endif

            @foreach($timeline as $item)
              @if($item['type'] === 'event')
                {{-- System event (started, submitted, acknowledged) --}}
                <div class="text-center mb-3">
                  <span class="inline-block bg-white/80 text-gray-500 text-xs px-3 py-1 rounded-full shadow-sm border border-gray-100">
                    {{ $item['text'] }} · {{ $item['time']->format('g:i A') }}
                  </span>
                </div>

              @elseif($item['type'] === 'photo_batch')
                {{-- User photos = LEFT side --}}
                <div class="flex justify-start mb-3">
                  <div class="max-w-[85%]">
                    <div class="flex items-end gap-2">
                      <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                        {{ strtoupper(substr($item['user']->name ?? '?', 0, 1)) }}
                      </div>
                      <div>
                        @php $batchFiles = $item['files']; $count = $batchFiles->count(); @endphp
                        @if($count === 1)
                          @php $f = $batchFiles->first(); @endphp
                          <div class="rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                            @if($f->isVideo())
                              <video src="{{ Storage::url($f->file_path) }}" controls class="w-56 h-56 object-cover"></video>
                            @else
                              <img src="{{ Storage::url($f->file_path) }}"
                                   data-lightbox-src="{{ Storage::url($f->file_path) }}"
                                   data-lightbox-sender="{{ $item['user']->name ?? 'User' }}"
                                   data-lightbox-time="{{ $f->created_at->format('M d, Y · g:i A') }}"
                                   @click="$dispatch('open-lightbox', { src: '{{ Storage::url($f->file_path) }}', taskId: {{ $task->id }} })"
                                   class="w-56 h-56 object-cover cursor-zoom-in hover:opacity-90 transition">
                            @endif
                          </div>
                        @elseif($count === 2)
                          <div class="flex gap-0.5 rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                            @foreach($batchFiles as $file)
                              @if($file->isVideo())
                                <video src="{{ Storage::url($file->file_path) }}" controls class="w-32 h-40 object-cover"></video>
                              @else
                                <img src="{{ Storage::url($file->file_path) }}"
                                     data-lightbox-src="{{ Storage::url($file->file_path) }}"
                                     data-lightbox-sender="{{ $item['user']->name ?? 'User' }}"
                                     data-lightbox-time="{{ $file->created_at->format('M d, Y · g:i A') }}"
                                     @click="$dispatch('open-lightbox', { src: '{{ Storage::url($file->file_path) }}', taskId: {{ $task->id }} })"
                                     class="w-32 h-40 object-cover cursor-zoom-in hover:opacity-90 transition">
                              @endif
                            @endforeach
                          </div>
                        @else
                          {{-- 3+ photos: grid --}}
                          <div class="rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                            <div class="grid grid-cols-2 gap-0.5">
                              @foreach($batchFiles->take(4) as $idx => $file)
                                <div class="relative">
                                  @if($file->isVideo())
                                    <video src="{{ Storage::url($file->file_path) }}" controls class="w-full h-32 object-cover"></video>
                                  @else
                                    <img src="{{ Storage::url($file->file_path) }}"
                                         data-lightbox-src="{{ Storage::url($file->file_path) }}"
                                         data-lightbox-sender="{{ $item['user']->name ?? 'User' }}"
                                         data-lightbox-time="{{ $file->created_at->format('M d, Y · g:i A') }}"
                                         @click="$dispatch('open-lightbox', { src: '{{ Storage::url($file->file_path) }}', taskId: {{ $task->id }} })"
                                         class="w-full h-32 object-cover cursor-zoom-in hover:opacity-90 transition">
                                  @endif
                                  @if($idx === 3 && $count > 4)
                                    <div @click="$dispatch('open-lightbox', { src: '{{ Storage::url($file->file_path) }}', taskId: {{ $task->id }} })"
                                         class="absolute inset-0 bg-black/50 flex items-center justify-center cursor-zoom-in">
                                      <span class="text-white text-lg font-bold">+{{ $count - 4 }}</span>
                                    </div>
                                  @endif
                                </div>
                              @endforeach
                            </div>
                            @if($count > 4)
                              <div class="grid grid-cols-3 gap-0.5 mt-0.5">
                                @foreach($batchFiles->slice(4) as $file)
                                  @if($file->isVideo())
                                    <video src="{{ Storage::url($file->file_path) }}" controls class="w-full h-24 object-cover"></video>
                                  @else
                                    <img src="{{ Storage::url($file->file_path) }}"
                                         data-lightbox-src="{{ Storage::url($file->file_path) }}"
                                         data-lightbox-sender="{{ $item['user']->name ?? 'User' }}"
                                         data-lightbox-time="{{ $file->created_at->format('M d, Y · g:i A') }}"
                                         @click="$dispatch('open-lightbox', { src: '{{ Storage::url($file->file_path) }}', taskId: {{ $task->id }} })"
                                         class="w-full h-24 object-cover cursor-zoom-in hover:opacity-90 transition">
                                  @endif
                                @endforeach
                              </div>
                            @endif
                          </div>
                        @endif
                        <p class="text-[10px] text-gray-400 mt-1 ml-1">{{ $item['user']->name ?? 'User' }} · {{ $item['time']->format('g:i A') }}</p>
                      </div>
                    </div>
                  </div>
                </div>

              @elseif($item['type'] === 'note')
                {{-- User note = LEFT side --}}
                @if(!empty($item['text']))
                  <div class="flex justify-start mb-3">
                    <div class="max-w-[80%]">
                      <div class="flex items-end gap-2">
                        <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                          {{ strtoupper(substr($item['user']->name ?? '?', 0, 1)) }}
                        </div>
                        <div>
                          <div class="bg-white rounded-2xl rounded-bl-md px-4 py-2.5 shadow-sm border border-gray-100">
                            <p class="text-sm text-gray-800">{{ $item['text'] }}</p>
                          </div>
                          <p class="text-[10px] text-gray-400 mt-1 ml-1">{{ $item['user']->name ?? 'User' }} · {{ $item['time']->format('g:i A') }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                @endif

              @elseif($item['type'] === 'admin_comment')
                {{-- Admin comment = RIGHT side --}}
                <div class="flex justify-end mb-3">
                  <div class="max-w-[80%]">
                    <div class="bg-blue-500 text-white rounded-2xl rounded-br-md px-4 py-2.5 shadow-sm">
                      <p class="text-sm">{{ $item['text'] }}</p>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1 text-right">{{ $item['user']->name ?? 'Admin' }} · {{ $item['time']->format('g:i A') }}</p>
                  </div>
                </div>
              @endif
            @endforeach
          </div>

          {{-- Per-user timelines (individual mode) --}}
          @if($isIndividual)
            @foreach($userTabs as $ut)
              @php $userTL = $userTimelines[$ut['user']->id] ?? collect(); @endphp
              <div x-show="activeUserTab[{{ $task->id }}] === {{ $ut['user']->id }}">
                @if($userTL->isEmpty())
                  <div class="text-center py-8">
                    <p class="text-gray-400 text-sm">{{ $ut['user']->name }} has not started yet</p>
                  </div>
                @endif

                @foreach($userTL as $item)
                  @if($item['type'] === 'event')
                    <div class="text-center mb-3">
                      <span class="inline-block bg-white/80 text-gray-500 text-xs px-3 py-1 rounded-full shadow-sm border border-gray-100">
                        {{ $item['text'] }} · {{ $item['time']->format('g:i A') }}
                      </span>
                    </div>
                  @elseif($item['type'] === 'photo_batch')
                    <div class="flex justify-start mb-3">
                      <div class="max-w-[85%]">
                        <div class="flex items-end gap-2">
                          <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                            {{ strtoupper(substr($ut['user']->name, 0, 1)) }}
                          </div>
                          <div>
                            @php $batchFiles = $item['files']; $count = $batchFiles->count(); @endphp
                            <div class="rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                              <div class="grid {{ $count > 1 ? 'grid-cols-2' : '' }} gap-0.5">
                                @foreach($batchFiles->take(4) as $idx => $file)
                                  <div class="relative">
                                    @if($file->isVideo())
                                      <video src="{{ Storage::url($file->file_path) }}" controls class="w-full {{ $count === 1 ? 'h-56' : 'h-32' }} object-cover"></video>
                                    @else
                                      <img src="{{ Storage::url($file->file_path) }}"
                                           data-lightbox-src="{{ Storage::url($file->file_path) }}"
                                           data-lightbox-sender="{{ $ut['user']->name }}"
                                           data-lightbox-time="{{ $file->created_at->format('M d, Y · g:i A') }}"
                                           @click="$dispatch('open-lightbox', { src: '{{ Storage::url($file->file_path) }}', taskId: {{ $task->id }} })"
                                           class="w-full {{ $count === 1 ? 'h-56' : 'h-32' }} object-cover cursor-zoom-in hover:opacity-90 transition">
                                    @endif
                                    @if($idx === 3 && $count > 4)
                                      <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                        <span class="text-white text-lg font-bold">+{{ $count - 4 }}</span>
                                      </div>
                                    @endif
                                  </div>
                                @endforeach
                              </div>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1 ml-1">{{ $ut['user']->name }} · {{ $item['time']->format('g:i A') }}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  @elseif($item['type'] === 'note')
                    @if(!empty($item['text']))
                      <div class="flex justify-start mb-3">
                        <div class="max-w-[80%]">
                          <div class="flex items-end gap-2">
                            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                              {{ strtoupper(substr($ut['user']->name, 0, 1)) }}
                            </div>
                            <div>
                              <div class="bg-white rounded-2xl rounded-bl-md px-4 py-2.5 shadow-sm border border-gray-100">
                                <p class="text-sm text-gray-800">{{ $item['text'] }}</p>
                              </div>
                              <p class="text-[10px] text-gray-400 mt-1 ml-1">{{ $ut['user']->name }} · {{ $item['time']->format('g:i A') }}</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    @endif
                  @endif
                @endforeach
              </div>
            @endforeach
          @endif

        </div>

        {{-- Bottom bar: Admin comment input --}}
        <div class="flex-shrink-0 bg-white border-t border-gray-200 px-4 py-3 safe-area-bottom">
          <div class="flex items-center gap-2 max-w-lg mx-auto">
            <div class="flex-1">
              <input type="text"
                     x-model="commentText"
                     @keydown.enter="sendComment({{ $task->id }})"
                     placeholder="Type a comment..."
                     class="w-full text-sm border border-gray-200 rounded-full px-4 py-2.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 bg-gray-50">
            </div>
            <button @click="sendComment({{ $task->id }})"
                    :disabled="sending || !commentText.trim()"
                    :class="commentText.trim() ? 'opacity-100' : 'opacity-40'"
                    class="w-10 h-10 rounded-full flex items-center justify-center text-white flex-shrink-0 transition" style="background-color:#1877F2">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
          </div>
        </div>

      </div>
    @endforeach

    {{-- ===== LIGHTBOX WITH ZOOM + SENDER INFO ===== --}}
    @php
      $imagesByTask = [];
      foreach ($tasks as $task) {
        $taskImages = collect();
        if ($task->reference_image) {
          $taskImages->push([
            'src' => Storage::url($task->reference_image),
            'sender' => 'Reference Photo',
            'time' => $task->created_at->format('M d, Y'),
          ]);
        }
        if ($task->referenceFiles) {
          foreach ($task->referenceFiles as $rf) {
            if ($rf->isImage()) {
              $rfSrc = Storage::url($rf->file_path);
              if ($rfSrc !== Storage::url($task->reference_image)) {
                $taskImages->push([
                  'src' => $rfSrc,
                  'sender' => 'Reference Photo',
                  'time' => $task->created_at->format('M d, Y'),
                ]);
              }
            }
          }
        }
        $taskSubs = $allSubmissionsByTask->get($task->id) ?? collect();
        foreach ($taskSubs as $sub) {
          foreach ($sub->files->filter(fn($f) => $f->isImage())->sortBy('created_at') as $f) {
            $taskImages->push([
              'src' => Storage::url($f->file_path),
              'sender' => $sub->user->name ?? 'User',
              'time' => $f->created_at->format('M d, Y · g:i A'),
            ]);
          }
        }
        $imagesByTask[$task->id] = $taskImages->values()->toArray();
      }
    @endphp

    <div x-data="{
           lightbox: false,
           allTaskImages: {{ json_encode($imagesByTask) }},
           images: [],
           currentIndex: 0,
           scale: 1,
           translateX: 0,
           translateY: 0,
           isDragging: false,
           startX: 0,
           startY: 0,
           lastTX: 0,
           lastTY: 0,
           initialPinchDist: 0,
           initialPinchScale: 1,
           get current() { return this.images[this.currentIndex] ?? { src: '', sender: '', time: '' }; },
           get lightSrc() { return this.current.src; },
           get senderName() { return this.current.sender; },
           get senderTime() { return this.current.time; },
           open(detail) {
               let src, taskId;
               if (typeof detail === 'object' && detail.src) {
                   src = detail.src;
                   taskId = detail.taskId;
               } else {
                   src = typeof detail === 'string' ? detail : detail;
                   taskId = null;
               }
               if (taskId && this.allTaskImages[taskId]) {
                   this.images = this.allTaskImages[taskId];
               }
               const idx = this.images.findIndex(i => i.src === src);
               this.currentIndex = idx >= 0 ? idx : 0;
               this.resetZoom();
               this.lightbox = true;
           },
           resetZoom() { this.scale = 1; this.translateX = 0; this.translateY = 0; },
           prev() { if (this.currentIndex > 0) { this.currentIndex--; this.resetZoom(); } },
           next() { if (this.currentIndex < this.images.length - 1) { this.currentIndex++; this.resetZoom(); } },
           handleWheel(e) {
               e.preventDefault();
               const delta = e.deltaY > 0 ? 0.9 : 1.1;
               this.scale = Math.min(Math.max(this.scale * delta, 1), 8);
               if (this.scale === 1) { this.translateX = 0; this.translateY = 0; }
           },
           handleTouchStart(e) {
               if (e.touches.length === 2) {
                   this.initialPinchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
                   this.initialPinchScale = this.scale;
               } else if (e.touches.length === 1 && this.scale > 1) {
                   this.isDragging = true;
                   this.startX = e.touches[0].clientX - this.translateX;
                   this.startY = e.touches[0].clientY - this.translateY;
               }
           },
           handleTouchMove(e) {
               e.preventDefault();
               if (e.touches.length === 2) {
                   const dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
                   this.scale = Math.min(Math.max(this.initialPinchScale * (dist / this.initialPinchDist), 1), 8);
                   if (this.scale === 1) { this.translateX = 0; this.translateY = 0; }
               } else if (e.touches.length === 1 && this.isDragging) {
                   this.translateX = e.touches[0].clientX - this.startX;
                   this.translateY = e.touches[0].clientY - this.startY;
               }
           },
           handleTouchEnd(e) {
               this.isDragging = false;
               this.initialPinchDist = 0;
           },
           lastTapTime: 0,
           handleDoubleTap(e) {
               e.stopPropagation();
               const now = Date.now();
               if (now - this.lastTapTime < 300) {
                   if (this.scale > 1) { this.resetZoom(); } else { this.scale = 3; }
                   this.lastTapTime = 0;
               } else {
                   this.lastTapTime = now;
               }
           }
         }"
         @open-lightbox.window="open($event.detail)"
         @keydown.escape.window="lightbox = false"
         @keydown.arrow-left.window="if (lightbox) prev()"
         @keydown.arrow-right.window="if (lightbox) next()"
         x-show="lightbox"
         x-transition.opacity
         @click="if (scale <= 1) lightbox = false"
         class="fixed inset-0 z-[60] bg-black/90 flex flex-col items-center justify-center"
         style="display:none; touch-action:none">

      <button @click="lightbox = false"
              class="absolute top-4 right-4 w-9 h-9 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-lg transition z-20">✕</button>

      <template x-if="images.length > 1">
        <div class="absolute top-4 left-1/2 -translate-x-1/2 bg-black/50 text-white text-xs px-3 py-1 rounded-full z-20"
             x-text="(currentIndex + 1) + ' / ' + images.length"></div>
      </template>

      <template x-if="images.length > 1">
        <button @click.stop="prev()"
                :class="currentIndex === 0 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-20">‹</button>
      </template>

      <div class="flex-1 flex items-center justify-center w-full overflow-hidden p-4"
           style="touch-action:none"
           @wheel.prevent="handleWheel($event)"
           @touchstart="handleTouchStart($event)"
           @touchmove.prevent="handleTouchMove($event)"
           @touchend="handleTouchEnd($event)">
        <img :src="lightSrc"
             :style="'transform: scale(' + scale + ') translate(' + (translateX/scale) + 'px, ' + (translateY/scale) + 'px); transition: ' + (isDragging ? 'none' : 'transform 0.2s ease')"
             class="max-w-full max-h-full rounded-xl shadow-2xl object-contain select-none"
             style="touch-action:none"
             @click.stop="handleDoubleTap($event)"
             draggable="false">
      </div>

      <template x-if="images.length > 1">
        <button @click.stop="next()"
                :class="currentIndex === images.length - 1 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-20">›</button>
      </template>

      <div class="w-full flex-shrink-0 bg-black/70 px-4 py-3 text-center z-20" @click.stop>
        <p class="text-white text-sm font-medium" x-text="senderName"></p>
        <p class="text-white/60 text-xs" x-text="senderTime"></p>
      </div>
    </div>

  </div>

  <style>
    .safe-area-bottom { padding-bottom: max(12px, env(safe-area-inset-bottom)); }
  </style>

</x-layout>
