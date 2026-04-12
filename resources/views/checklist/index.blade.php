<x-layout>
  <x-slot name="heading">Daily Checklist</x-slot>
  <x-slot name="title">Daily Checklist</x-slot>

  <div class="min-h-screen bg-gray-100 mt-14 pb-8">

    {{-- ===== STICKY HEADER ===== --}}
    <div class="bg-white border-b border-gray-200 shadow-sm sticky top-14 z-30">
      <div class="max-w-lg mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-lg font-bold text-gray-800">My Tasks</h1>
            <p class="text-xs text-gray-400">{{ now()->format('l, F j, Y') }}</p>
          </div>
          <div class="flex items-center gap-3">
            {{-- Progress --}}
            <div class="flex items-center gap-2">
              <div class="relative w-10 h-10">
                <svg class="w-10 h-10 -rotate-90" viewBox="0 0 36 36">
                  <circle cx="18" cy="18" r="15" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                  <circle cx="18" cy="18" r="15" fill="none"
                          stroke="{{ $doneCount === $totalTasks && $totalTasks > 0 ? '#22c55e' : '#3b82f6' }}"
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

    <div class="max-w-lg mx-auto px-4 pt-4 space-y-3">

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
        $sortedTasks  = $pendingTasks->concat($doneTasks);

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

        {{-- ===== TASK CARDS ===== --}}
        @foreach($sortedTasks as $task)
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

          <div x-data="{
                 showForm: false,
                 queue: [],
                 addFiles(fileList) {
                   for (const f of fileList) {
                     this.queue.push({
                       name: f.name,
                       url: f.type.startsWith('image/') ? URL.createObjectURL(f) : '',
                       file: f,
                       isImg: f.type.startsWith('image/')
                     });
                   }
                 },
                 removeQueued(i) {
                   if (this.queue[i].url) URL.revokeObjectURL(this.queue[i].url);
                   this.queue.splice(i, 1);
                 },
                 submitForm(e) {
                   if (this.queue.length === 0) return true;
                   e.preventDefault();
                   const form = e.target;
                   const fd = new FormData(form);
                   fd.delete('files[]');
                   this.queue.forEach(q => fd.append('files[]', q.file, q.name));
                   fetch(form.action, {
                     method: 'POST',
                     body: fd,
                     headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                   }).then(r => { window.location.reload(); }).catch(err => { form.submit(); });
                 }
               }"
               class="rounded-3xl shadow-sm overflow-hidden transition-all duration-300
                 {{ $done ? 'bg-green-50 border-2 border-green-200' : 'bg-white border-2 border-gray-100' }}">

            {{-- CARD HEADER --}}
            <div class="px-5 pt-4 pb-3">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  {{-- Status + Title --}}
                  <div class="flex items-center gap-2.5">
                    @if($done)
                      <div class="w-7 h-7 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                      </div>
                    @else
                      <div class="w-7 h-7 rounded-full border-2 border-orange-300 bg-orange-50 flex items-center justify-center flex-shrink-0">
                        <div class="w-2.5 h-2.5 rounded-full bg-orange-400"></div>
                      </div>
                    @endif
                    <h2 class="text-base font-bold text-gray-800 leading-tight">{{ $task->title }}</h2>
                  </div>

                  {{-- Time badge --}}
                  @if($task->task_time)
                    <div class="ml-9 mt-1">
                      <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-700">
                        🕐 {{ \Carbon\Carbon::parse($task->task_time)->format('g:i A') }}
                      </span>
                    </div>
                  @endif
                </div>

                {{-- Done badge --}}
                @if($done)
                  <span class="text-xs font-bold text-green-600 bg-green-100 px-3 py-1 rounded-full flex-shrink-0">DONE</span>
                @else
                  <span class="text-xs font-bold text-orange-600 bg-orange-100 px-3 py-1 rounded-full flex-shrink-0">PENDING</span>
                @endif
              </div>
            </div>

            {{-- SUBMITTED CONTENT (if done) --}}
            @if($done)
              <div class="px-5 pb-3 space-y-2">
                {{-- Photos --}}
                @if($imageFiles->count() > 0)
                  <div class="flex flex-wrap gap-2">
                    @foreach($imageFiles as $f)
                      <img src="{{ Storage::url($f->file_path) }}"
                           @click="$dispatch('open-lightbox', '{{ Storage::url($f->file_path) }}')"
                           class="w-20 h-20 object-cover rounded-2xl border border-gray-200 shadow-sm cursor-zoom-in active:scale-95 transition-transform">
                    @endforeach
                  </div>
                @endif
                {{-- Notes --}}
                @if($sub->notes)
                  <div class="bg-white/60 rounded-xl px-3 py-2">
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $sub->notes }}</p>
                  </div>
                @endif
                {{-- Submitted info --}}
                <div class="flex items-center gap-2 text-xs text-gray-400">
                  <div class="w-5 h-5 rounded-full bg-green-200 flex items-center justify-center text-xs font-bold text-green-700 flex-shrink-0">
                    {{ strtoupper(substr($sub->user->name ?? '?', 0, 1)) }}
                  </div>
                  <span>{{ $sub->user->name ?? 'Unknown' }} · {{ $sub->created_at->format('g:i A') }}</span>
                </div>
              </div>
            @endif

            {{-- ACTION BUTTONS --}}
            <div class="px-5 pb-4">
              @if($canSubmit)
                <button @click="showForm = !showForm"
                        class="w-full py-3.5 rounded-2xl font-bold text-base transition-all duration-200
                          bg-blue-600 text-white active:bg-blue-700 active:scale-[0.98] shadow-lg shadow-blue-200">
                  📸 Submit Task
                </button>
              @elseif($done && ($isMine || $isAdmin))
                <button @click="showForm = !showForm"
                        class="w-full py-2.5 rounded-2xl font-semibold text-sm transition-all duration-200
                          bg-white border-2 border-gray-200 text-gray-600 active:bg-gray-50 active:scale-[0.98]"
                        x-text="showForm ? 'Close' : '✏️ Edit Submission'">
                  ✏️ Edit Submission
                </button>
              @endif
            </div>

            {{-- ===== SUBMISSION FORM ===== --}}
            @if($canSubmit || ($done && ($isMine || $isAdmin)))
              <div x-show="showForm" x-transition.duration.200ms
                   class="border-t-2 {{ $done ? 'border-green-200 bg-green-50/50' : 'border-blue-100 bg-blue-50/30' }} px-5 py-5">
                <form method="POST" action="{{ route('checklist.submit', $task) }}" enctype="multipart/form-data" class="space-y-4" @submit="submitForm($event)">
                  @csrf

                  {{-- PHOTO UPLOAD AREA --}}
                  @if(in_array($task->type, ['photo', 'any', 'both', 'photo_note']))
                    <div>
                      <label class="block text-sm font-bold text-gray-700 mb-2">
                        📸 Photos
                        @if(in_array($task->type, ['photo', 'both', 'photo_note']))
                          <span class="text-red-500">*</span>
                        @endif
                      </label>

                      {{-- Existing photos (edit mode) --}}
                      @if($done && $imageFiles->count() > 0)
                        <div class="flex flex-wrap gap-2 mb-3">
                          @foreach($imageFiles as $f)
                            <div class="relative">
                              <img src="{{ Storage::url($f->file_path) }}" class="w-20 h-20 object-cover rounded-2xl border border-gray-200 shadow-sm">
                              @if($isMine || $isAdmin)
                                <form method="POST" action="{{ route('checklist.delete-file', $f) }}" onsubmit="return confirm('Remove this photo?')" class="absolute -top-2 -right-2">
                                  @csrf @method('DELETE')
                                  <button type="submit" class="w-6 h-6 bg-red-500 text-white rounded-full text-xs flex items-center justify-center shadow-lg font-bold">✕</button>
                                </form>
                              @endif
                            </div>
                          @endforeach
                        </div>
                      @endif

                      {{-- New photo queue --}}
                      <template x-if="queue.length > 0">
                        <div class="flex flex-wrap gap-2 mb-3">
                          <template x-for="(item, i) in queue" :key="i">
                            <div class="relative">
                              <template x-if="item.isImg">
                                <img :src="item.url" class="w-20 h-20 object-cover rounded-2xl border-2 border-blue-200 shadow-sm">
                              </template>
                              <template x-if="!item.isImg">
                                <div class="w-20 h-20 flex flex-col items-center justify-center rounded-2xl border-2 border-blue-200 bg-blue-50 text-xs text-blue-500">
                                  <span class="text-2xl">📎</span>
                                  <span class="truncate w-full text-center px-1" x-text="item.name.split('.').pop().toUpperCase()"></span>
                                </div>
                              </template>
                              <button type="button" @click.stop="removeQueued(i)"
                                      class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs flex items-center justify-center shadow-lg font-bold">✕</button>
                            </div>
                          </template>
                        </div>
                      </template>

                      {{-- Add Photo Buttons --}}
                      <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="$refs.cameraInput{{ $task->id }}.click()"
                                class="py-4 rounded-2xl border-2 border-dashed border-blue-300 bg-blue-50 text-center active:bg-blue-100 active:scale-[0.98] transition-all">
                          <p class="text-3xl">📷</p>
                          <p class="text-xs font-bold text-blue-600 mt-1">Take Photo</p>
                        </button>
                        <button type="button" @click="$refs.galleryInput{{ $task->id }}.click()"
                                class="py-4 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 text-center active:bg-gray-100 active:scale-[0.98] transition-all">
                          <p class="text-3xl">🖼️</p>
                          <p class="text-xs font-bold text-gray-600 mt-1">From Gallery</p>
                        </button>
                      </div>

                      {{-- Hidden file inputs --}}
                      <input type="file" x-ref="cameraInput{{ $task->id }}" name="files[]" class="hidden"
                             accept="image/*" capture="environment"
                             @change="addFiles($event.target.files); $event.target.value='';">
                      <input type="file" x-ref="galleryInput{{ $task->id }}" name="files[]" class="hidden"
                             multiple accept="{{ in_array($task->type, ['photo','both','photo_note']) ? 'image/*' : 'image/*,.pdf,.doc,.docx' }}"
                             @change="addFiles($event.target.files); $event.target.value='';">
                    </div>
                  @endif

                  {{-- NOTES --}}
                  @if(in_array($task->type, ['note', 'any', 'both', 'photo_note']))
                    <div>
                      <label class="block text-sm font-bold text-gray-700 mb-2">
                        📝 Notes
                        @if($task->type === 'both')
                          <span class="text-red-500">*</span>
                        @else
                          <span class="text-gray-400 font-normal text-xs">(optional)</span>
                        @endif
                      </label>
                      <textarea name="notes" rows="3" placeholder="Add notes or remarks..."
                                class="w-full border-2 border-gray-200 rounded-2xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-blue-300 bg-white">{{ $sub?->notes }}</textarea>
                    </div>
                  @endif

                  {{-- SUBMIT BUTTON --}}
                  <button type="submit"
                          class="w-full py-4 rounded-2xl font-bold text-base transition-all duration-200
                            bg-green-500 text-white active:bg-green-600 active:scale-[0.98] shadow-lg shadow-green-200">
                    ✅ {{ $sub ? 'Update Submission' : 'Submit Task' }}
                  </button>

                  {{-- Cancel --}}
                  <button type="button" @click="showForm = false; queue = [];"
                          class="w-full py-2.5 rounded-2xl text-sm text-gray-400 hover:bg-gray-100 transition font-medium">
                    Cancel
                  </button>

                  {{-- Delete submission --}}
                  @if($done && ($isMine || $isAdmin))
                    <div class="text-center pt-1">
                      <form method="POST" action="{{ route('checklist.delete-submission', $sub) }}"
                            onsubmit="return confirm('Remove entire submission?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-600 underline">Remove submission</button>
                      </form>
                    </div>
                  @endif
                </form>
              </div>
            @endif
          </div>
        @endforeach

        {{-- All done message --}}
        @if($doneCount === $totalTasks && $totalTasks > 0)
          <div class="bg-green-100 border-2 border-green-300 rounded-3xl px-6 py-5 text-center">
            <p class="text-3xl mb-2">🎉</p>
            <p class="text-base font-bold text-green-700">All tasks completed!</p>
            <p class="text-sm text-green-600 mt-1">Great job for today.</p>
          </div>
        @endif
      @endif
    </div>
  </div>

  {{-- ===== LIGHTBOX ===== --}}
  <div x-data="{
           lightbox: false,
           images: {{ json_encode($allImageUrls ?? []) }},
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
       class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
       style="display:none">

    <button @click="lightbox = false"
            class="absolute top-4 right-4 w-10 h-10 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-xl transition z-10">✕</button>

    <template x-if="images.length > 1">
      <div class="absolute top-4 left-1/2 -translate-x-1/2 bg-black/50 text-white text-sm px-4 py-1.5 rounded-full z-10"
           x-text="(currentIndex + 1) + ' / ' + images.length"></div>
    </template>

    <template x-if="images.length > 1">
      <button @click.stop="prev()"
              :class="currentIndex === 0 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
              class="absolute left-3 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-2xl transition z-10">
        ‹
      </button>
    </template>

    <img :src="lightSrc"
         class="max-w-full max-h-full rounded-2xl shadow-2xl object-contain"
         @click.stop>

    <template x-if="images.length > 1">
      <button @click.stop="next()"
              :class="currentIndex === images.length - 1 ? 'opacity-20 pointer-events-none' : 'opacity-80 hover:opacity-100'"
              class="absolute right-3 top-1/2 -translate-y-1/2 w-12 h-12 bg-white/10 hover:bg-white/20 text-white rounded-full flex items-center justify-center text-2xl transition z-10">
        ›
      </button>
    </template>
  </div>

</x-layout>
