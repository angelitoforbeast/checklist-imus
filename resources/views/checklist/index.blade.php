<x-layout>
  <x-slot name="heading">Daily Checklist</x-slot>
  <x-slot name="title">Daily Checklist</x-slot>

  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- Alpine.js global state for focus mode --}}
  <div x-data="{
         focusTask: null,
         showCompleted: false,
         setFocus(id) { this.focusTask = id; document.body.style.overflow = 'hidden'; },
         clearFocus() { this.focusTask = null; document.body.style.overflow = ''; }
       }"
       @keydown.escape.window="clearFocus()"
       class="min-h-screen bg-gray-100 mt-14 pb-8">

    {{-- ===== STICKY HEADER ===== --}}
    <div class="bg-white border-b border-gray-200 shadow-sm sticky top-14 z-30"
         x-show="!focusTask" x-transition.opacity>
      <div class="max-w-lg mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-lg font-bold text-gray-800">My Tasks</h1>
            <p class="text-xs text-gray-400">{{ now()->format('l, F j, Y') }}</p>
          </div>
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
              <div class="relative w-10 h-10">
                <svg class="w-10 h-10 -rotate-90" viewBox="0 0 36 36">
                  <circle cx="18" cy="18" r="15" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                  <circle cx="18" cy="18" r="15" fill="none"
                          stroke="{{ $doneCount === $totalTasks && $totalTasks > 0 ? '#22c55e' : '#1877F2' }}"
                          stroke-width="3" stroke-linecap="round"
                          stroke-dasharray="{{ $totalTasks > 0 ? round($doneCount / $totalTasks * 94.2) : 0 }} 94.2"/>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-700" data-poll-counter>{{ $doneCount }}/{{ $totalTasks }}</span>
              </div>
            </div>
            @if(Auth::user()->isAdmin())
              <a href="{{ route('checklist.report') }}" class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition" title="Report">📋</a>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-lg mx-auto px-4 pt-4 space-y-3" x-show="!focusTask" x-transition.opacity>

      {{-- Alerts --}}
      @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-2xl text-sm font-medium flex gap-2 items-center">
          <span class="text-xl">✅</span> {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm">{{ session('error') }}</div>
      @endif
      @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm space-y-1">
          @foreach($errors->all() as $e)<div>⚠️ {{ $e }}</div>@endforeach
        </div>
      @endif

      @php
        $isAdmin = Auth::user()?->isAdmin();
        // A task is "done" only if it has a submission with status 'completed'
        // A task with a reverted submission (status 'pending') should appear in pending list
        $pendingTasks = $tasks->filter(fn($t) => !$submissionsByTask->has($t->id) || ($submissionsByTask->has($t->id) && $submissionsByTask->get($t->id)->status === 'pending'));
        $doneTasks    = $tasks->filter(fn($t) => $submissionsByTask->has($t->id) && $submissionsByTask->get($t->id)->status === 'completed');

        $allImageUrls = [];
        foreach($tasks as $task) {
            $sub = $submissionsByTask->get($task->id);
            if (!$sub) continue;
            foreach($sub->files->filter(fn($f) => $f->isImage()) as $f) {
                $allImageUrls[] = Storage::url($f->file_path);
            }
        }
      @endphp

      @if($tasks->isEmpty())
        <div class="bg-white rounded-3xl shadow-sm p-10 text-center">
          <p class="text-5xl mb-3">📋</p>
          <p class="text-gray-500 font-semibold text-lg">No tasks for today</p>
          <p class="text-sm text-gray-400 mt-1">You're all set!</p>
        </div>
      @else

        {{-- ===== PENDING TASK CARDS ===== --}}
        @foreach($pendingTasks as $task)
          @php
            $assignedIds = $task->assignedUsers->pluck('id')->toArray();
            $isAssigned  = empty($assignedIds) || in_array(Auth::id(), $assignedIds);
          @endphp

          <div class="rounded-3xl shadow-sm overflow-hidden bg-white border-2 border-blue-300" data-poll-task="{{ $task->id }}">
            <div class="px-5 pt-4 pb-3">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full border-2 border-blue-400 bg-blue-50 flex items-center justify-center flex-shrink-0">
                      <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                    </div>
                    <h2 class="text-base font-bold text-gray-800 leading-tight">{{ $task->title }}</h2>
                  </div>
                  @if($task->task_time)
                    <div class="ml-9 mt-1">
                      <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-700">
                        🕐 {{ \Carbon\Carbon::parse($task->task_time)->format('g:i A') }}
                      </span>
                    </div>
                  @endif
                </div>
                <span class="text-xs font-bold text-blue-700 bg-blue-100 px-3 py-1 rounded-full flex-shrink-0" data-poll-status="pending">PENDING</span>
              </div>
            </div>

            @if($isAssigned)
              <div class="px-5 pb-4">
                <button @click="setFocus({{ $task->id }})"
                        class="w-full py-3.5 rounded-2xl font-bold text-base transition-all duration-200
                          text-white active:scale-[0.98] shadow-lg" style="background-color:#1877F2; box-shadow: 0 10px 15px -3px rgba(24,119,242,0.3)">
                  📸 Upload Photo
                </button>
              </div>
            @endif
          </div>
        @endforeach

        {{-- ===== COMPLETED TASKS (COLLAPSED) ===== --}}
        @if($doneTasks->count() > 0)
          <div class="mt-4">
            <button @click="showCompleted = !showCompleted"
                    class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl bg-green-50 border-2 border-green-200 text-green-700 font-bold text-sm transition-all active:scale-[0.98]">
              <span class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                  <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                Completed ({{ $doneTasks->count() }})
              </span>
              <svg class="w-5 h-5 transition-transform duration-200" :class="showCompleted ? 'rotate-180' : ''"
                   fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="showCompleted" x-collapse class="space-y-3 mt-3">
              @foreach($doneTasks as $task)
                @php
                  $sub        = $submissionsByTask->get($task->id);
                  $isMine     = $sub && $sub->user_id === Auth::id();
                  $subFiles   = $sub ? $sub->files : collect();
                  $imageFiles = $subFiles->filter(fn($f) => $f->isImage());
                @endphp

                <div x-data="{ expanded: false }"
                     class="rounded-3xl shadow-sm overflow-hidden bg-green-50 border-2 border-green-200" data-poll-task="{{ $task->id }}">
                  <button @click="expanded = !expanded" class="w-full px-5 py-3 text-left">
                    <div class="flex items-center justify-between gap-3">
                      <div class="flex items-center gap-2.5 flex-1 min-w-0">
                        <div class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                          <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                          <h2 class="text-sm font-bold text-green-800 leading-tight truncate">{{ $task->title }}</h2>
                          @if($task->task_time)
                            <span class="text-xs text-green-600">{{ \Carbon\Carbon::parse($task->task_time)->format('g:i A') }}</span>
                          @endif
                        </div>
                      </div>
                      <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-xs font-bold text-green-600 bg-green-100 px-2.5 py-0.5 rounded-full" data-poll-status="completed">DONE</span>
                        <svg class="w-4 h-4 text-green-500 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                      </div>
                    </div>
                  </button>

                  <div x-show="expanded" x-collapse>
                    <div class="px-5 pb-4 space-y-2 border-t border-green-200 pt-3">
                      @if($imageFiles->count() > 0)
                        <div class="overflow-x-auto -mx-5 px-5 pb-1" style="-webkit-overflow-scrolling: touch;">
                          <div class="flex gap-2" style="min-width: min-content;">
                            @foreach($imageFiles as $f)
                              <img src="{{ Storage::url($f->file_path) }}"
                                   data-lightbox-src="{{ Storage::url($f->file_path) }}"
                                   data-lightbox-sender="{{ $sub->user->name ?? 'User' }}"
                                   data-lightbox-time="{{ $f->created_at->format('M d, Y \u00b7 g:i A') }}"
                                   @click="$dispatch('open-lightbox', '{{ Storage::url($f->file_path) }}')"
                                   class="w-20 h-20 flex-shrink-0 object-cover rounded-2xl border border-green-200 shadow-sm cursor-zoom-in active:scale-95 transition-transform">
                            @endforeach
                          </div>
                        </div>
                      @endif
                      @if($sub->notes)
                        <div class="bg-white/60 rounded-xl px-3 py-2">
                          <p class="text-sm text-gray-600 leading-relaxed">{{ $sub->notes }}</p>
                        </div>
                      @endif
                      <div class="flex items-center gap-2 text-xs text-gray-400">
                        <div class="w-5 h-5 rounded-full bg-green-200 flex items-center justify-center text-xs font-bold text-green-700 flex-shrink-0">
                          {{ strtoupper(substr($sub->user->name ?? '?', 0, 1)) }}
                        </div>
                        <span>{{ $sub->user->name ?? 'Unknown' }} · {{ $sub->created_at->format('g:i A') }}</span>
                      </div>
                      @if($isMine || $isAdmin)
                        <button @click.stop="focusTask = {{ $task->id }}; document.body.style.overflow = 'hidden';"
                                class="w-full py-2.5 rounded-2xl font-semibold text-sm transition-all duration-200
                                  bg-white border-2 border-green-300 text-green-700 active:bg-green-50 active:scale-[0.98] mt-1">
                          📸 Add More Photos
                        </button>
                      @endif
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endif

        @if($doneCount === $totalTasks && $totalTasks > 0 && $pendingTasks->count() === 0)
          <div class="bg-green-100 border-2 border-green-300 rounded-3xl px-6 py-5 text-center">
            <p class="text-3xl mb-2">🎉</p>
            <p class="text-base font-bold text-green-700">All tasks completed!</p>
            <p class="text-sm text-green-600 mt-1">Great job for today.</p>
          </div>
        @endif
      @endif
    </div>

    {{-- ===== FULL-SCREEN MESSENGER-STYLE FOCUS MODE (per task) ===== --}}
    @foreach($tasks as $task)
      @php
        $sub         = $submissionsByTask->get($task->id);
        $done        = $sub !== null && $sub->status === 'completed';
        $reverted    = $sub !== null && $sub->status === 'pending';
        $isMine      = $sub && $sub->user_id === Auth::id();
        $assignedIds = $task->assignedUsers->pluck('id')->toArray();
        $isAssigned  = empty($assignedIds) || in_array(Auth::id(), $assignedIds);
        $canSubmit   = (!$done && $isAssigned) || $reverted;
        $subFiles    = ($done || $reverted) ? $sub->files : collect();
        $imageFiles  = $subFiles->filter(fn($f) => $f->isImage());
      @endphp

      @if($canSubmit || ($done && ($isMine || $isAdmin)))
        <div x-show="focusTask === {{ $task->id }}"
             x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 flex flex-col bg-white"
             style="display:none"
             x-data="{
               uploading: false,
               sendingNote: false,
               noteText: '{{ $sub?->notes ? addslashes($sub->notes) : '' }}',
               sentPhotos: [
                 @if($done || $reverted)
                   @foreach($imageFiles as $f)
                     { url: '{{ Storage::url($f->file_path) }}', name: '{{ $f->file_original_name }}', time: '{{ $f->created_at->format('g:i A') }}', ts: {{ $f->created_at->timestamp }}, by: '{{ $sub->user->name ?? 'Unknown' }}' },
                   @endforeach
                 @endif
               ],
               sentNotes: [
                 @if($sub)
                   @foreach($sub->logs->where('action', 'note_sent')->sortBy('created_at') as $noteLog)
                     @if($noteLog->notes_snapshot)
                       { text: {{ json_encode($noteLog->notes_snapshot) }}, time: '{{ $noteLog->created_at->format('g:i A') }}', ts: {{ $noteLog->created_at->timestamp }}, by: '{{ $noteLog->user->name ?? $sub->user->name ?? 'Unknown' }}' },
                     @endif
                   @endforeach
                 @endif
               ],
               adminComments: [
                 @php $taskComments = $commentsByTask->get($task->id, collect()); @endphp
                 @foreach($taskComments as $comment)
                   { text: {{ json_encode($comment->message) }}, time: '{{ $comment->created_at->format('g:i A') }}', ts: {{ $comment->created_at->timestamp }}, by: '{{ $comment->user->name ?? 'Admin' }}', initial: '{{ strtoupper(substr($comment->user->name ?? 'A', 0, 1)) }}' },
                 @endforeach
               ],
               get chatMessages() {
                 // Group photos into batches (within 120 seconds = same batch)
                 let messages = [];
                 let currentBatch = null;
                 for (const photo of this.sentPhotos) {
                   if (!currentBatch || Math.abs(photo.ts - currentBatch.ts) > 120) {
                     currentBatch = { type: 'photo_batch', photos: [photo], time: photo.time, ts: photo.ts, by: photo.by };
                     messages.push(currentBatch);
                   } else {
                     currentBatch.photos.push(photo);
                   }
                 }
                 // Add notes
                 for (const note of this.sentNotes) {
                   messages.push({ type: 'note', text: note.text, time: note.time, ts: note.ts, by: note.by });
                 }
                 // Add admin comments
                 for (const comment of this.adminComments) {
                   messages.push({ type: 'admin_comment', text: comment.text, time: comment.time, ts: comment.ts, by: comment.by, initial: comment.initial });
                 }
                 // Sort all by timestamp
                 messages.sort((a, b) => a.ts - b.ts);
                 return messages;
               },
               async autoUpload(fileList) {
                 const files = [...fileList].filter(f => f.type.startsWith('image/'));
                 if (files.length === 0) return;
                 for (const file of files) {
                   this.uploading = true;
                   const fd = new FormData();
                   fd.append('photo', file);
                   fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                   try {
                     const res = await fetch('{{ route('checklist.upload-photo', $task) }}', {
                       method: 'POST',
                       body: fd,
                       headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                     });
                     const data = await res.json();
                     if (data.success) {
                       this.sentPhotos.push({ url: data.url, name: data.name, time: data.uploaded_at, ts: Math.floor(Date.now()/1000), by: data.uploaded_by });
                       this.$nextTick(() => {
                         const chatArea = this.$refs.chatArea{{ $task->id }};
                         if (chatArea) chatArea.scrollTop = chatArea.scrollHeight;
                       });
                     } else {
                       alert(data.error || 'Upload failed');
                     }
                   } catch(e) {
                     alert('Upload failed. Check your connection.');
                   }
                   this.uploading = false;
                 }
               },
               async sendNote() {
                 if (this.sendingNote || !this.noteText.trim()) return;
                 this.sendingNote = true;
                 const fd = new FormData();
                 fd.append('notes', this.noteText.trim());
                 fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                 try {
                   const res = await fetch('{{ route('checklist.send-note', $task) }}', {
                     method: 'POST',
                     body: fd,
                     headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                   });
                   const data = await res.json();
                   if (data.success) {
                     this.sentNotes.push({ text: data.notes, time: data.sent_at, ts: Math.floor(Date.now()/1000), by: data.sent_by });
                     this.noteText = '';
                     this.$nextTick(() => {
                       const chatArea = this.$refs.chatArea{{ $task->id }};
                       if (chatArea) chatArea.scrollTop = chatArea.scrollHeight;
                     });
                   } else {
                     alert(data.error || 'Failed to send note');
                   }
                 } catch(e) {
                   alert('Failed to send. Check your connection.');
                 }
                 this.sendingNote = false;
               }
             }">

          {{-- ===== MESSENGER HEADER ===== --}}
          <div class="flex-shrink-0 border-b border-gray-200 shadow-sm" style="background-color:#1877F2">
            <div class="max-w-lg mx-auto px-4 py-3 flex items-center gap-3">
              <button @click="clearFocus()"
                      class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 text-white active:bg-white/30 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <div class="flex-1 min-w-0">
                <h1 class="text-white font-bold text-base truncate">{{ $task->title }}</h1>
                <div class="flex items-center gap-2">
                  @if($task->task_time)
                    <span class="text-white/70 text-xs">🕐 {{ \Carbon\Carbon::parse($task->task_time)->format('g:i A') }}</span>
                  @endif
                  <span class="text-xs font-semibold" :class="sentPhotos.length > 0 ? 'text-green-300' : 'text-white/70'"
                        x-text="sentPhotos.length > 0 ? '✅ ' + sentPhotos.length + ' photo' + (sentPhotos.length > 1 ? 's' : '') + ' sent' : '⏳ Pending'">
                  </span>
                </div>
              </div>
            </div>
          </div>

          {{-- ===== PINNED REFERENCE FILES (fixed between header and chat) ===== --}}
          @php $refFiles = $task->referenceFiles ?? collect(); @endphp
          @if($task->reference_image || $refFiles->count())
            <div x-data="{ pinExpanded: false }" class="flex-shrink-0 border-b border-gray-200 bg-white">
              <div class="max-w-lg mx-auto px-4 py-2">
                <div class="flex items-start gap-2">
                  <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold" style="background-color:#1877F2">📌</div>
                  <div class="flex-1 min-w-0">
                    <button type="button" @click="pinExpanded = !pinExpanded"
                            class="flex items-center gap-2 text-xs text-gray-500 font-medium mb-1 hover:text-gray-700 transition w-full">
                      <span>📌 Reference {{ $refFiles->count() > 1 ? 'Files ('.$refFiles->count().')' : 'Photo' }}</span>
                      <svg class="w-3.5 h-3.5 transition-transform" :class="pinExpanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="overflow-hidden transition-all duration-300" :style="pinExpanded ? 'max-height: 500px' : 'max-height: 60px'">
                      <div class="flex flex-wrap gap-2">
                        @if($task->reference_image && $refFiles->where('file_path', $task->reference_image)->isEmpty())
                          <img src="{{ Storage::url($task->reference_image) }}"
                               data-lightbox-src="{{ Storage::url($task->reference_image) }}"
                               data-lightbox-sender="Reference Photo"
                               data-lightbox-time="{{ $task->created_at->format('M d, Y') }}"
                               @click="$dispatch('open-lightbox', '{{ Storage::url($task->reference_image) }}')"
                               class="max-w-[200px] object-cover rounded-xl cursor-zoom-in active:scale-95 transition-transform"
                               :class="pinExpanded ? 'h-auto' : 'h-[60px]'">
                        @endif
                        @foreach($refFiles as $rf)
                          @if($rf->isVideo())
                            <video src="{{ Storage::url($rf->file_path) }}" controls
                                   class="max-w-[200px] rounded-xl"
                                   :class="pinExpanded ? 'h-auto' : 'h-[60px]'"></video>
                          @else
                            <img src="{{ Storage::url($rf->file_path) }}"
                                 data-lightbox-src="{{ Storage::url($rf->file_path) }}"
                                 data-lightbox-sender="Reference Photo"
                                 data-lightbox-time="{{ $task->created_at->format('M d, Y') }}"
                                 @click="$dispatch('open-lightbox', '{{ Storage::url($rf->file_path) }}')"
                                 class="max-w-[200px] object-cover rounded-xl cursor-zoom-in active:scale-95 transition-transform"
                                 :class="pinExpanded ? 'h-auto' : 'h-[60px]'">
                          @endif
                        @endforeach
                      </div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-0.5" x-text="pinExpanded ? 'Tap to collapse' : 'Tap to expand'"></p>
                  </div>
                </div>
              </div>
            </div>
          @endif

          {{-- ===== CHAT AREA ===== --}}
          <div class="flex-1 overflow-y-auto bg-gray-50" x-ref="chatArea{{ $task->id }}">
            <div class="max-w-lg mx-auto px-4 py-4 space-y-4">

              {{-- Task instruction bubble (received message, left side) --}}
              <div class="flex items-start gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-sm font-bold" style="background-color:#1877F2">📋</div>
                <div>
                  <div class="bg-gray-200 rounded-2xl rounded-tl-md px-4 py-2.5">
                    <p class="text-sm text-gray-800 font-medium">{{ $task->title }}</p>
                    @if(in_array($task->type, ['photo', 'photo_note', 'both']))
                      <p class="text-xs text-gray-500 mt-1">📸 Please send photo proof</p>
                    @endif
                    @if(in_array($task->type, ['note', 'both']))
                      <p class="text-xs text-gray-500 mt-0.5">📝 Notes required</p>
                    @endif
                  </div>
                  <p class="text-[10px] text-gray-400 mt-1 ml-1">Task assigned</p>
                </div>
              </div>

              {{-- Interleaved chat messages (photos batched, notes, admin comments) --}}
              <template x-for="(msg, mi) in chatMessages" :key="'msg'+mi">
                <div>
                  {{-- Photo batch (right side, user sent) --}}
                  <template x-if="msg.type === 'photo_batch'">
                    <div class="flex justify-end">
                      <div class="max-w-[85%]">
                        <div class="flex flex-wrap gap-1.5 justify-end">
                          <template x-for="(photo, pi) in msg.photos" :key="'p'+mi+'_'+pi">
                            <template x-if="photo.url && (photo.url.endsWith('.mp4') || photo.url.endsWith('.mov') || photo.url.endsWith('.webm') || photo.url.endsWith('.avi') || photo.url.endsWith('.3gp') || photo.mime?.startsWith('video/'))">
                              <video :src="photo.url" controls playsinline
                                     class="w-40 h-28 object-cover rounded-2xl shadow-sm"
                                     :class="pi === 0 && msg.photos.length === 1 ? 'rounded-tr-md' : ''">
                              </video>
                            </template>
                            <template x-if="!(photo.url && (photo.url.endsWith('.mp4') || photo.url.endsWith('.mov') || photo.url.endsWith('.webm') || photo.url.endsWith('.avi') || photo.url.endsWith('.3gp') || photo.mime?.startsWith('video/')))">
                              <img :src="photo.url"
                                   :data-lightbox-src="photo.url"
                                   :data-lightbox-sender="msg.by"
                                   :data-lightbox-time="msg.time"
                                   @click="$dispatch('open-lightbox', photo.url)"
                                   class="w-28 h-28 object-cover rounded-2xl shadow-sm cursor-zoom-in active:scale-95 transition-transform"
                                   :class="pi === 0 && msg.photos.length === 1 ? 'rounded-tr-md' : ''">
                            </template>
                          </template>
                        </div>
                        <p class="text-[10px] text-gray-400 text-right mt-1 mr-1">
                          <span x-text="msg.by"></span> · <span x-text="msg.time"></span>
                        </p>
                      </div>
                    </div>
                  </template>

                  {{-- Note (right side, blue bubble) --}}
                  <template x-if="msg.type === 'note'">
                    <div class="flex justify-end">
                      <div class="max-w-[75%]">
                        <div class="rounded-2xl rounded-tr-md px-4 py-2.5 text-white text-sm" style="background-color:#1877F2">
                          <span x-text="msg.text"></span>
                        </div>
                        <p class="text-[10px] text-gray-400 text-right mt-1 mr-1">
                          <span x-text="msg.by"></span> · <span x-text="msg.time"></span>
                        </p>
                      </div>
                    </div>
                  </template>

                  {{-- Admin comment (left side, gray bubble) --}}
                  <template x-if="msg.type === 'admin_comment'">
                    <div class="flex items-start gap-2">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold" style="background-color:#1877F2" x-text="msg.initial"></div>
                      <div class="max-w-[75%]">
                        <div class="bg-gray-200 rounded-2xl rounded-tl-md px-4 py-2.5">
                          <p class="text-sm text-gray-800" x-text="msg.text"></p>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1 ml-1">
                          <span x-text="msg.by"></span> · <span x-text="msg.time"></span>
                        </p>
                      </div>
                    </div>
                  </template>
                </div>
              </template>

              {{-- Uploading indicator --}}
              <template x-if="uploading">
                <div class="flex justify-end">
                  <div class="max-w-[75%]">
                    <div class="w-48 h-48 rounded-2xl rounded-tr-md bg-gray-200 flex flex-col items-center justify-center animate-pulse">
                      <svg class="animate-spin h-8 w-8 text-blue-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                      </svg>
                      <p class="text-xs text-gray-500 font-medium">Sending...</p>
                    </div>
                  </div>
                </div>
              </template>

            </div>
          </div>

          {{-- ===== MESSENGER BOTTOM BAR ===== --}}
          <div class="flex-shrink-0 border-t border-gray-200 bg-white" style="padding-bottom: env(safe-area-inset-bottom, 0px);">
            <div class="max-w-lg mx-auto px-3 py-2">
              <div class="flex items-end gap-2">

                {{-- Camera button --}}
                @if(in_array($task->type, ['photo', 'any', 'both', 'photo_note']))
                  <button type="button" @click="$refs.msgCam{{ $task->id }}.click()"
                          :disabled="uploading"
                          class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center active:bg-blue-50 transition"
                          :style="uploading ? 'color:#9ca3af' : 'color:#1877F2'">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 15.2a3.2 3.2 0 100-6.4 3.2 3.2 0 000 6.4z"/><path d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>
                  </button>

                  {{-- Gallery button --}}
                  <button type="button" @click="$refs.msgGal{{ $task->id }}.click()"
                          :disabled="uploading"
                          class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center active:bg-blue-50 transition"
                          :style="uploading ? 'color:#9ca3af' : 'color:#1877F2'">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                  </button>

                  {{-- Hidden file inputs with auto-upload --}}
                  <input type="file" x-ref="msgCam{{ $task->id }}" class="hidden"
                         accept="image/*,video/*" capture="environment"
                         @change="autoUpload($event.target.files); $event.target.value='';">
                  <input type="file" x-ref="msgGal{{ $task->id }}" class="hidden"
                         multiple accept="image/*,video/*"
                         @change="autoUpload($event.target.files); $event.target.value='';">
                @endif

                {{-- Text input (Messenger Aa style) --}}
                @if(in_array($task->type, ['note', 'any', 'both', 'photo_note']))
                  <div class="flex-1 relative">
                    <input type="text" x-model="noteText"
                           @keydown.enter.prevent="sendNote()"
                           placeholder="Aa"
                           class="w-full bg-gray-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 border-0 placeholder-gray-400">
                  </div>
                @else
                  <div class="flex-1"></div>
                @endif

                {{-- Send button (for notes) --}}
                <button type="button"
                        @click="sendNote()"
                        :disabled="sendingNote || !noteText.trim()"
                        class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-white active:scale-90 transition-all"
                        :class="sendingNote || !noteText.trim() ? 'bg-gray-300' : ''"
                        :style="sendingNote || !noteText.trim() ? '' : 'background-color:#1877F2'">
                  <template x-if="!sendingNote">
                    <svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                  </template>
                  <template x-if="sendingNote">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                  </template>
                </button>

              </div>
            </div>
          </div>

        </div>
      @endif
    @endforeach

  </div>

  {{-- ===== LIGHTBOX WITH ZOOM + SENDER INFO ===== --}}
  <div x-data="{
           lightbox: false,
           images: [],
           currentIndex: 0,
           scale: 1,
           translateX: 0,
           translateY: 0,
           isDragging: false,
           startX: 0,
           startY: 0,
           initialPinchDist: 0,
           initialPinchScale: 1,
           get current() { return this.images[this.currentIndex] ?? { src: '', sender: '', time: '' }; },
           get lightSrc() { return this.current.src; },
           get senderName() { return this.current.sender; },
           get senderTime() { return this.current.time; },
           open(src) {
               const allEls = Array.from(document.querySelectorAll('[data-lightbox-src]'));
               const seen = new Set();
               this.images = allEls.filter(el => {
                   const s = el.dataset.lightboxSrc;
                   if (seen.has(s)) return false;
                   seen.add(s);
                   return true;
               }).map(el => ({
                   src: el.dataset.lightboxSrc,
                   sender: el.dataset.lightboxSender || 'Unknown',
                   time: el.dataset.lightboxTime || ''
               }));
               if (this.images.length === 0) this.images = [{ src: src, sender: '', time: '' }];
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
           handleTouchEnd(e) { this.isDragging = false; this.initialPinchDist = 0; },
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

    {{-- Close button --}}
    <button @click="lightbox = false"
            class="absolute top-4 right-4 w-10 h-10 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-20">✕</button>

    {{-- Counter --}}
    <template x-if="images.length > 1">
      <div class="absolute top-4 left-1/2 -translate-x-1/2 bg-black/50 text-white text-xs px-3 py-1 rounded-full z-20"
           x-text="(currentIndex + 1) + ' / ' + images.length"></div>
    </template>

    {{-- Prev button --}}
    <template x-if="images.length > 1">
      <button @click.stop="prev()"
              :class="currentIndex === 0 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
              class="absolute left-3 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-2xl transition z-20">‹</button>
    </template>

    {{-- Image with zoom --}}
    <div class="flex-1 flex items-center justify-center w-full overflow-hidden p-4"
         style="touch-action:none"
         @wheel.prevent="handleWheel($event)"
         @touchstart="handleTouchStart($event)"
         @touchmove.prevent="handleTouchMove($event)"
         @touchend="handleTouchEnd($event)">
      <img :src="lightSrc"
           :style="'transform: scale(' + scale + ') translate(' + (translateX/scale) + 'px, ' + (translateY/scale) + 'px); transition: ' + (isDragging ? 'none' : 'transform 0.2s ease')"
           class="max-w-full max-h-full rounded-2xl shadow-2xl object-contain select-none"
           style="touch-action:none"
           @click.stop="handleDoubleTap($event)"
           draggable="false">
    </div>

    {{-- Next button --}}
    <template x-if="images.length > 1">
      <button @click.stop="next()"
              :class="currentIndex === images.length - 1 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
              class="absolute right-3 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-2xl transition z-20">›</button>
    </template>

    {{-- Sender info caption bar --}}
    <div class="w-full flex-shrink-0 bg-black/70 px-4 py-3 text-center z-20" @click.stop>
      <p class="text-white text-sm font-medium" x-text="senderName"></p>
      <p class="text-white/60 text-xs" x-text="senderTime"></p>
    </div>
  </div>

  {{-- ===== SILENT POLLING (5s) ===== --}}
  <script>
  (function() {
      const POLL_INTERVAL = 5000;
      const POLL_URL = '{{ route("checklist.poll-status") }}';

      async function poll() {
          try {
              const resp = await fetch(POLL_URL, {
                  headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                  credentials: 'same-origin'
              });
              if (!resp.ok) return;
              const data = await resp.json();

              // Update progress counter
              const counterEl = document.querySelector('[data-poll-counter]');
              if (counterEl) {
                  counterEl.textContent = data.done_count + '/' + data.total + ' Done';
              }

              // Update each task's status badge
              data.tasks.forEach(t => {
                  const card = document.querySelector('[data-poll-task="' + t.task_id + '"]');
                  if (!card) return;

                  const badge = card.querySelector('[data-poll-status]');
                  if (badge) {
                      const oldStatus = badge.dataset.pollStatus;
                      if (oldStatus !== t.status) {
                          badge.dataset.pollStatus = t.status;
                          // Update badge appearance
                          if (t.status === 'completed') {
                              badge.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700';
                              badge.textContent = 'DONE';
                          } else if (t.status === 'reverted') {
                              badge.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-orange-100 text-orange-700';
                              badge.textContent = 'REVERTED';
                          } else if (t.status === 'submitted') {
                              badge.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700';
                              badge.textContent = 'SUBMITTED';
                          } else {
                              badge.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500';
                              badge.textContent = 'PENDING';
                          }

                          // If status changed to reverted, show a subtle flash
                          if (t.status === 'reverted' && oldStatus === 'completed') {
                              card.style.transition = 'background-color 0.5s';
                              card.style.backgroundColor = '#FFF7ED';
                              setTimeout(() => { card.style.backgroundColor = ''; }, 2000);
                          }
                      }
                  }

                  // Update comment count
                  const commentBadge = card.querySelector('[data-poll-comments]');
                  if (commentBadge) {
                      if (t.comment_count > 0) {
                          commentBadge.textContent = t.comment_count + ' comment' + (t.comment_count > 1 ? 's' : '');
                          commentBadge.style.display = '';
                      } else {
                          commentBadge.style.display = 'none';
                      }
                  }
              });
          } catch (e) {
              // Silent fail — will retry next interval
          }
      }

      // Start polling
      setInterval(poll, POLL_INTERVAL);
  })();
  </script>

</x-layout>
