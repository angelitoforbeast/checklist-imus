<x-layout>
  @section('title', 'Conversations')

  <meta name="csrf-token" content="{{ csrf_token() }}">

  <div x-data="{
    focusTask: null,
    showCompleted: false,
    commentText: '',
    sending: false,

    async sendComment(taskId) {
      if (!this.commentText.trim() || this.sending) return;
      this.sending = true;
      try {
        const res = await fetch('/checklist/send-comment/' + taskId, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
          },
          body: JSON.stringify({ message: this.commentText, date: '{{ $dateObj->toDateString() }}' })
        });
        if (res.ok) {
          const data = await res.json();
          // Add to chat
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

          {{-- Date nav --}}
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

        {{-- Filter + progress --}}
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
          $pendingTasks = $tasks->filter(fn($t) => !$submissionsByTask->has($t->id) || $submissionsByTask->get($t->id)->status !== 'completed');
          $completedTasks = $tasks->filter(fn($t) => $submissionsByTask->has($t->id) && $submissionsByTask->get($t->id)->status === 'completed');
        @endphp

        {{-- Pending tasks --}}
        @foreach($pendingTasks as $task)
          @php
            $sub = $submissionsByTask->get($task->id);
            $photoCount = $sub ? $sub->files->filter(fn($f) => $f->isImage())->count() : 0;
            $commentCount = ($commentsByTask->get($task->id) ?? collect())->count();
            $reverted = $sub && $sub->status === 'pending';
          @endphp
          <div @click="focusTask = {{ $task->id }}; $nextTick(() => { const el = document.getElementById('admin-chat-{{ $task->id }}'); if(el) el.scrollTop = el.scrollHeight; })"
               class="bg-white rounded-2xl border-2 border-blue-200 shadow-sm p-4 cursor-pointer hover:shadow-md transition active:scale-[0.98]">
            <div class="flex items-center justify-between">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  @if($reverted)
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                  @else
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-400 flex-shrink-0"></span>
                  @endif
                  <p class="font-semibold text-gray-800 truncate">{{ $task->title }}</p>
                </div>
                @if($task->scheduled_time)
                  <p class="text-xs text-gray-400 mt-0.5 ml-5">⏰ {{ \Carbon\Carbon::parse($task->scheduled_time)->format('g:i A') }}</p>
                @endif
                @if($task->assignedUsers->count())
                  <p class="text-xs text-blue-400 mt-0.5 ml-5 truncate">→ {{ $task->assignedUsers->pluck('name')->implode(', ') }}</p>
                @endif
              </div>
              <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                @if($photoCount > 0)
                  <span class="text-xs bg-blue-50 text-blue-500 px-2 py-0.5 rounded-full font-medium">📸 {{ $photoCount }}</span>
                @endif
                @if($commentCount > 0)
                  <span class="text-xs bg-blue-50 text-blue-500 px-2 py-0.5 rounded-full font-medium">💬 {{ $commentCount }}</span>
                @endif
                @if($reverted)
                  <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full font-semibold">REVERTED</span>
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
                  $photoCount = $sub ? $sub->files->filter(fn($f) => $f->isImage())->count() : 0;
                  $commentCount = ($commentsByTask->get($task->id) ?? collect())->count();
                @endphp
                <div @click="focusTask = {{ $task->id }}; $nextTick(() => { const el = document.getElementById('admin-chat-{{ $task->id }}'); if(el) el.scrollTop = el.scrollHeight; })"
                     class="bg-white rounded-2xl border border-green-200 shadow-sm p-4 cursor-pointer hover:shadow-md transition active:scale-[0.98]">
                  <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-green-400 flex-shrink-0"></span>
                        <p class="font-semibold text-gray-800 truncate">{{ $task->title }}</p>
                      </div>
                      @if($sub && $sub->user)
                        <p class="text-xs text-gray-400 mt-0.5 ml-5">by {{ $sub->user->name }} · {{ $sub->created_at->format('g:i A') }}</p>
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
        $done = $sub && $sub->status === 'completed';
        $reverted = $sub && $sub->status === 'pending';
        $subFiles = ($done || $reverted) ? $sub->files : collect();
        $imageFiles = $subFiles->filter(fn($f) => $f->isImage());
        $taskComments = $commentsByTask->get($task->id) ?? collect();

        // Build timeline: interleave photos (grouped by batch), notes, and admin comments
        $timeline = collect();

        // Group photos by batch (2-min gap)
        $photoBatches = collect();
        $currentBatch = collect();
        $lastTime = null;
        foreach ($imageFiles->sortBy('created_at') as $file) {
          $fileTime = $file->created_at;
          if ($lastTime && abs($fileTime->diffInSeconds($lastTime)) > 120) {
            if ($currentBatch->isNotEmpty()) {
              $photoBatches->push(['type' => 'photo_batch', 'files' => $currentBatch, 'time' => $currentBatch->first()->created_at, 'user' => $sub->user]);
            }
            $currentBatch = collect();
          }
          $currentBatch->push($file);
          $lastTime = $fileTime;
        }
        if ($currentBatch->isNotEmpty()) {
          $photoBatches->push(['type' => 'photo_batch', 'files' => $currentBatch, 'time' => $currentBatch->first()->created_at, 'user' => $sub->user]);
        }
        foreach ($photoBatches as $batch) {
          $timeline->push($batch);
        }

        // Add notes from submission_logs (note_sent)
        if ($sub) {
          foreach ($sub->logs->where('action', 'note_sent')->sortBy('created_at') as $log) {
            $timeline->push(['type' => 'note', 'text' => $log->notes_snapshot ?? '', 'time' => $log->created_at, 'user' => $log->user]);
          }
        }

        // Add admin comments
        foreach ($taskComments as $comment) {
          $timeline->push(['type' => 'admin_comment', 'text' => $comment->message, 'time' => $comment->created_at, 'user' => $comment->user]);
        }

        // Sort by time
        $timeline = $timeline->sortBy('time')->values();
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
            <p class="font-semibold text-sm truncate">{{ $task->title }}</p>
            <p class="text-xs text-blue-100">
              @if($done) ✅ Completed @elseif($reverted) ↩ Reverted @else ⏳ Pending @endif
              @if($sub && $sub->user) · {{ $sub->user->name }} @endif
            </p>
          </div>
          @if($done)
            <form method="POST" action="{{ route('checklist.revert-submission', $sub) }}"
                  onsubmit="return confirm('Revert to pending? User will need to re-submit.')" class="flex-shrink-0">
              @csrf
              <button type="submit" class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-full transition">↩ Revert</button>
            </form>
          @endif
        </div>

        {{-- Reference photo (if any) --}}
        @if($task->reference_image)
          <div x-data="{ expanded: false }" class="bg-amber-50 border-b border-amber-200 flex-shrink-0">
            <div @click="expanded = !expanded" class="px-4 py-2 flex items-center justify-between cursor-pointer">
              <span class="text-xs font-semibold text-amber-700">📌 Reference Photo</span>
              <svg :class="expanded ? 'rotate-180' : ''" class="w-3 h-3 text-amber-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <div x-show="expanded" x-transition class="px-4 pb-3">
              <img src="{{ Storage::url($task->reference_image) }}" class="max-h-48 rounded-xl border border-amber-200 shadow-sm">
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
                {{ in_array($task->type, ['photo','photo_note']) ? '📸 Photo required' : ($task->type === 'note' ? '📝 Note required' : '📎 Any submission') }}
              </p>
            </div>
          </div>

          @if($timeline->isEmpty())
            <div class="text-center py-8">
              <p class="text-gray-400 text-sm">No messages yet</p>
              <p class="text-gray-300 text-xs mt-1">Waiting for user submission...</p>
            </div>
          @endif

          {{-- Timeline messages --}}
          @foreach($timeline as $item)
            @if($item['type'] === 'photo_batch')
              {{-- User photos = LEFT side (received by admin) - Messenger style --}}
              <div class="flex justify-start mb-3">
                <div class="max-w-[85%]">
                  <div class="flex items-end gap-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                      {{ strtoupper(substr($item['user']->name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                      @php $batchFiles = $item['files']; $count = $batchFiles->count(); @endphp
                      @if($count === 1)
                        {{-- Single photo: large bubble --}}
                        <div class="rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                          <img src="{{ Storage::url($batchFiles->first()->file_path) }}"
                               @click="$dispatch('open-lightbox', '{{ Storage::url($batchFiles->first()->file_path) }}')"
                               class="w-56 h-56 object-cover cursor-zoom-in hover:opacity-90 transition">
                        </div>
                      @elseif($count === 2)
                        {{-- Two photos: side by side --}}
                        <div class="flex gap-0.5 rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                          @foreach($batchFiles as $file)
                            <img src="{{ Storage::url($file->file_path) }}"
                                 @click="$dispatch('open-lightbox', '{{ Storage::url($file->file_path) }}')"
                                 class="w-32 h-40 object-cover cursor-zoom-in hover:opacity-90 transition">
                          @endforeach
                        </div>
                      @elseif($count === 3)
                        {{-- Three photos: 1 big + 2 small --}}
                        <div class="rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                          <img src="{{ Storage::url($batchFiles->values()[0]->file_path) }}"
                               @click="$dispatch('open-lightbox', '{{ Storage::url($batchFiles->values()[0]->file_path) }}')"
                               class="w-64 h-36 object-cover cursor-zoom-in hover:opacity-90 transition">
                          <div class="flex gap-0.5 mt-0.5">
                            @foreach($batchFiles->values()->slice(1) as $file)
                              <img src="{{ Storage::url($file->file_path) }}"
                                   @click="$dispatch('open-lightbox', '{{ Storage::url($file->file_path) }}')"
                                   class="flex-1 h-28 object-cover cursor-zoom-in hover:opacity-90 transition">
                            @endforeach
                          </div>
                        </div>
                      @else
                        {{-- 4+ photos: 2-column grid --}}
                        <div class="rounded-2xl rounded-bl-md overflow-hidden shadow-sm border border-gray-200">
                          <div class="grid grid-cols-2 gap-0.5">
                            @foreach($batchFiles->take(4) as $idx => $file)
                              <div class="relative">
                                <img src="{{ Storage::url($file->file_path) }}"
                                     @click="$dispatch('open-lightbox', '{{ Storage::url($file->file_path) }}')"
                                     class="w-full h-32 object-cover cursor-zoom-in hover:opacity-90 transition">
                                @if($idx === 3 && $count > 4)
                                  <div @click="$dispatch('open-lightbox', '{{ Storage::url($file->file_path) }}')"
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
                                <img src="{{ Storage::url($file->file_path) }}"
                                     @click="$dispatch('open-lightbox', '{{ Storage::url($file->file_path) }}')"
                                     class="w-full h-24 object-cover cursor-zoom-in hover:opacity-90 transition">
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
              {{-- Admin comment = RIGHT side (sent by admin) --}}
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

        {{-- Bottom bar: Admin comment input (Messenger style) --}}
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

    {{-- ===== LIGHTBOX ===== --}}
    @php
      $allImageUrls = collect();
      foreach ($tasks as $task) {
        $sub = $submissionsByTask->get($task->id);
        if ($sub) {
          foreach ($sub->files->filter(fn($f) => $f->isImage()) as $f) {
            $allImageUrls->push(Storage::url($f->file_path));
          }
        }
        if ($task->reference_image) {
          $allImageUrls->push(Storage::url($task->reference_image));
        }
      }
    @endphp

    <div x-data="{
           lightbox: false,
           images: {{ json_encode($allImageUrls->values()->toArray()) }},
           currentIndex: 0,
           get lightSrc() { return this.images[this.currentIndex] ?? ''; },
           open(src) {
               const idx = this.images.indexOf(src);
               this.currentIndex = idx >= 0 ? idx : 0;
               this.lightbox = true;
           },
           prev() { if (this.currentIndex > 0) this.currentIndex--; },
           next() { if (this.currentIndex < this.images.length - 1) this.currentIndex++; }
         }"
         @open-lightbox.window="open($event.detail)"
         @keydown.escape.window="lightbox = false"
         @keydown.arrow-left.window="if (lightbox) prev()"
         @keydown.arrow-right.window="if (lightbox) next()"
         x-show="lightbox"
         x-transition.opacity
         @click="lightbox = false"
         class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4"
         style="display:none">

      <button @click="lightbox = false"
              class="absolute top-4 right-4 w-9 h-9 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-lg transition z-10">✕</button>

      <template x-if="images.length > 1">
        <div class="absolute top-4 left-1/2 -translate-x-1/2 bg-black/50 text-white text-xs px-3 py-1 rounded-full z-10"
             x-text="(currentIndex + 1) + ' / ' + images.length"></div>
      </template>

      <template x-if="images.length > 1">
        <button @click.stop="prev()"
                :class="currentIndex === 0 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-10">‹</button>
      </template>

      <img :src="lightSrc" class="max-w-full max-h-full rounded-xl shadow-2xl object-contain" @click.stop>

      <template x-if="images.length > 1">
        <button @click.stop="next()"
                :class="currentIndex === images.length - 1 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-10">›</button>
      </template>
    </div>

  </div>

  <style>
    .safe-area-bottom { padding-bottom: max(12px, env(safe-area-inset-bottom)); }
  </style>

</x-layout>
