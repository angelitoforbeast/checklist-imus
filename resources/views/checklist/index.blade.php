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
                <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-700">{{ $doneCount }}/{{ $totalTasks }}</span>
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
        $pendingTasks = $tasks->filter(fn($t) => !$submissionsByTask->has($t->id));
        $doneTasks    = $tasks->filter(fn($t) => $submissionsByTask->has($t->id));

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

          <div class="rounded-3xl shadow-sm overflow-hidden bg-white border-2 border-blue-300">
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
                <span class="text-xs font-bold text-blue-700 bg-blue-100 px-3 py-1 rounded-full flex-shrink-0">PENDING</span>
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
                     class="rounded-3xl shadow-sm overflow-hidden bg-green-50 border-2 border-green-200">
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
                        <span class="text-xs font-bold text-green-600 bg-green-100 px-2.5 py-0.5 rounded-full">DONE</span>
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
                        <button @click="setFocus({{ $task->id }})"
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
        $done        = $sub !== null;
        $isMine      = $sub && $sub->user_id === Auth::id();
        $assignedIds = $task->assignedUsers->pluck('id')->toArray();
        $isAssigned  = empty($assignedIds) || in_array(Auth::id(), $assignedIds);
        $canSubmit   = !$done && $isAssigned;
        $subFiles    = $done ? $sub->files : collect();
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
                 @if($done)
                   @foreach($imageFiles as $f)
                     { url: '{{ Storage::url($f->file_path) }}', name: '{{ $f->file_original_name }}', time: '{{ $f->created_at->format('g:i A') }}', by: '{{ $sub->user->name ?? 'Unknown' }}' },
                   @endforeach
                 @endif
               ],
               sentNotes: [
                 @if($sub?->notes)
                   { text: {{ json_encode($sub->notes) }}, time: '{{ $sub->updated_at->format('g:i A') }}', by: '{{ $sub->user->name ?? 'Unknown' }}' },
                 @endif
               ],
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
                       this.sentPhotos.push({ url: data.url, name: data.name, time: data.uploaded_at, by: data.uploaded_by });
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
                     this.sentNotes = [{ text: data.notes, time: data.sent_at, by: data.sent_by }];
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

          {{-- ===== PINNED REFERENCE IMAGE (fixed between header and chat) ===== --}}
          @if($task->reference_image)
            <div x-data="{ pinExpanded: false }" class="flex-shrink-0 border-b border-gray-200 bg-white">
              <div class="max-w-lg mx-auto px-4 py-2">
                <div class="flex items-start gap-2">
                  <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold" style="background-color:#1877F2">📌</div>
                  <div class="flex-1 min-w-0">
                    <button type="button" @click="pinExpanded = !pinExpanded"
                            class="flex items-center gap-2 text-xs text-gray-500 font-medium mb-1 hover:text-gray-700 transition w-full">
                      <span>📌 Reference Photo</span>
                      <svg class="w-3.5 h-3.5 transition-transform" :class="pinExpanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="overflow-hidden transition-all duration-300" :style="pinExpanded ? 'max-height: 300px' : 'max-height: 60px'">
                      <img src="{{ Storage::url($task->reference_image) }}"
                           @click="$dispatch('open-lightbox', '{{ Storage::url($task->reference_image) }}')"
                           class="w-full max-w-[200px] object-cover rounded-xl cursor-zoom-in active:scale-95 transition-transform"
                           :class="pinExpanded ? 'h-auto' : 'h-[60px]'">
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

              {{-- Sent photos (right side, horizontal scroll) --}}
              <template x-if="sentPhotos.length > 0">
                <div>
                  <div class="overflow-x-auto -mx-4 px-4 pb-1" style="-webkit-overflow-scrolling: touch;">
                    <div class="flex gap-2 justify-end" style="min-width: min-content;">
                      <template x-for="(photo, i) in sentPhotos" :key="i">
                        <div class="flex-shrink-0 w-36">
                          <img :src="photo.url"
                               @click="$dispatch('open-lightbox', photo.url)"
                               class="w-36 h-36 object-cover rounded-2xl shadow-sm cursor-zoom-in active:scale-95 transition-transform"
                               :class="i === sentPhotos.length - 1 ? 'rounded-tr-md' : ''">
                          <p class="text-[10px] text-gray-400 text-right mt-0.5 mr-1 truncate">
                            <span x-text="photo.by"></span> · <span x-text="photo.time"></span>
                          </p>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
              </template>

              {{-- Sent notes (right side, blue bubble like Messenger) --}}
              <template x-for="(note, i) in sentNotes" :key="'n'+i">
                <div class="flex justify-end">
                  <div class="max-w-[75%]">
                    <div class="rounded-2xl rounded-tr-md px-4 py-2.5 text-white text-sm" style="background-color:#1877F2">
                      <span x-text="note.text"></span>
                    </div>
                    <p class="text-[10px] text-gray-400 text-right mt-1 mr-1">
                      <span x-text="note.by"></span> · <span x-text="note.time"></span>
                    </p>
                  </div>
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
                         accept="image/*" capture="environment"
                         @change="autoUpload($event.target.files); $event.target.value='';">
                  <input type="file" x-ref="msgGal{{ $task->id }}" class="hidden"
                         multiple accept="image/*"
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

  {{-- ===== LIGHTBOX ===== --}}
  <div x-data="{
           lightbox: false,
           lightSrc: '',
           open(src) { this.lightSrc = src; this.lightbox = true; }
       }"
       @open-lightbox.window="open($event.detail)"
       @keydown.escape.window="lightbox = false"
       x-show="lightbox"
       x-transition.opacity
       @click="lightbox = false"
       class="fixed inset-0 z-[60] bg-black/90 flex items-center justify-center p-4"
       style="display:none">

    <button @click="lightbox = false"
            class="absolute top-4 right-4 w-10 h-10 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-10">✕</button>

    <img :src="lightSrc"
         class="max-w-full max-h-full rounded-2xl shadow-2xl object-contain"
         @click.stop>
  </div>

</x-layout>
