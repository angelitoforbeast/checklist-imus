<x-layout>
  <x-slot name="heading">Manage Checklist Tasks</x-slot>
  <x-slot name="title">Manage Tasks</x-slot>

  <div class="max-w-2xl mx-auto px-4 pb-8 mt-16 space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between pt-2">
      <div>
        <h1 class="text-lg font-bold text-gray-800">Manage Tasks</h1>
        <p class="text-xs text-gray-500">{{ $allTasks->count() }} task{{ $allTasks->count() !== 1 ? 's' : '' }}</p>
      </div>
      <button x-data @click="$dispatch('toggle-add-form')"
              class="flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 active:scale-95 transition shadow-sm">
        <i class="fa-solid fa-plus text-xs"></i> New Task
      </button>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
      <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-green-500"></i> {{ session('success') }}
      </div>
    @endif
    @if(session('error'))
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif

    @php
      $roles = \App\Models\Role::with('users')->orderBy('name')->get();
    @endphp

    {{-- ====== ADD TASK FORM (Slide-down) ====== --}}
    <div x-data="{ showForm: {{ $errors->any() ? 'true' : 'false' }} }"
         @toggle-add-form.window="showForm = !showForm"
         x-show="showForm" x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
         x-cloak
         class="bg-white border border-blue-200 rounded-2xl shadow-md overflow-hidden">

      <div class="bg-blue-50 px-4 py-3 flex items-center justify-between border-b border-blue-100">
        <h2 class="font-semibold text-blue-800 text-sm flex items-center gap-2">
          <i class="fa-solid fa-plus-circle"></i> New Task
        </h2>
        <button @click="showForm = false" class="text-blue-400 hover:text-blue-600 text-lg">&times;</button>
      </div>

      <form method="POST" action="{{ route('checklist.store-task') }}" class="p-4 space-y-4" enctype="multipart/form-data">
        @csrf

        {{-- Title --}}
        <div>
          <label class="text-xs font-medium text-gray-600 mb-1 block">Task Title *</label>
          <input type="text" name="title" placeholder="e.g. Clean the kitchen" required value="{{ old('title') }}"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
        </div>

        {{-- Description --}}
        <div>
          <label class="text-xs font-medium text-gray-600 mb-1 block">Description <span class="text-gray-400 font-normal">(optional)</span></label>
          <input type="text" name="description" placeholder="Brief description..." value="{{ old('description') }}"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
        </div>

        {{-- Type + Frequency row --}}
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Submission Type</label>
            <select name="type" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
              <option value="photo_note" {{ old('type') === 'photo_note' ? 'selected' : '' }}>📸 Photo + Note</option>
              <option value="any" {{ old('type') === 'any' ? 'selected' : '' }}>📎 Any</option>
              <option value="photo" {{ old('type') === 'photo' ? 'selected' : '' }}>📸 Photo only</option>
              <option value="note" {{ old('type') === 'note' ? 'selected' : '' }}>📝 Note only</option>
              <option value="both" {{ old('type') === 'both' ? 'selected' : '' }}>📸📝 Both required</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Frequency</label>
            <select name="frequency" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
              <option value="daily" {{ old('frequency') === 'daily' ? 'selected' : '' }}>🔄 Daily</option>
              <option value="once" {{ old('frequency') === 'once' ? 'selected' : '' }}>1️⃣ Once</option>
            </select>
          </div>
        </div>

        {{-- Scheduled Time --}}
        <div>
          <label class="text-xs font-medium text-gray-600 mb-1 block">Scheduled Time <span class="text-gray-400 font-normal">(optional)</span></label>
          <input type="time" name="task_time" value="{{ old('task_time') }}"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
        </div>

        {{-- AI Prompt --}}
        <div x-data="{ show: false }">
          <button type="button" @click="show = !show" class="text-xs text-purple-600 hover:text-purple-800 flex items-center gap-1 mb-1">
            <i class="fa-solid fa-robot"></i> AI Prompt Focus
            <svg class="w-3 h-3 transition-transform" :class="show && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <textarea x-show="show" x-transition name="ai_prompt" rows="2" placeholder="e.g. Check if the workstation is clean..."
                    class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none">{{ old('ai_prompt') }}</textarea>
        </div>

        {{-- Approval Criteria --}}
        <div x-data="{ show: false }">
          <button type="button" @click="show = !show" class="text-xs text-emerald-600 hover:text-emerald-800 flex items-center gap-1 mb-1">
            <i class="fa-solid fa-clipboard-check"></i> Approval Criteria
            <svg class="w-3 h-3 transition-transform" :class="show && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <textarea x-show="show" x-transition name="approval_prompt" rows="2" placeholder="e.g. The floor must be visibly clean..."
                    class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 resize-none">{{ old('approval_prompt') }}</textarea>
        </div>

        {{-- Assign to --}}
        <div x-data="{ open: false, selectedCount: 0, updateCount() { this.selectedCount = this.$root.querySelectorAll('.add-form-cb:checked').length; } }" class="relative">
          <label class="text-xs font-medium text-gray-600 mb-1 block">Assign to <span class="text-gray-400 font-normal">(blank = anyone)</span></label>
          <button type="button" @click="open = !open"
                  class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-left bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400 flex items-center justify-between gap-2">
            <span class="text-gray-500" x-text="selectedCount === 0 ? 'Select users...' : selectedCount + ' user(s) selected'">Select users...</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div x-show="open" x-transition
               class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto p-2 space-y-1">
            @foreach($roles as $role)
              @if($role->users->count() > 0)
                <div class="space-y-0.5">
                  <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-gray-50 cursor-pointer hover:bg-gray-100 font-medium text-xs text-gray-700"
                         @click.prevent="
                           const cbs = $el.closest('.space-y-0\\.5').querySelectorAll('input.add-form-cb');
                           const allChecked = [...cbs].every(c => c.checked);
                           cbs.forEach(c => c.checked = !allChecked);
                           updateCount();
                         ">
                    <input type="checkbox" class="accent-blue-600 pointer-events-none role-cb"
                           @click.stop="
                             const cbs = $el.closest('.space-y-0\\.5').querySelectorAll('input.add-form-cb');
                             cbs.forEach(c => c.checked = $el.checked);
                             updateCount();
                           ">
                    {{ $role->name }}
                    <span class="text-gray-400 font-normal">({{ $role->users->count() }})</span>
                  </label>
                  @foreach($role->users as $u)
                    <label class="flex items-center gap-2 px-2 py-1 pl-7 rounded-lg cursor-pointer hover:bg-gray-50 text-xs text-gray-600">
                      <input type="checkbox" name="assigned_users[]" value="{{ $u->id }}" class="accent-blue-600 add-form-cb"
                             @change="updateCount()">
                      {{ $u->name }}
                    </label>
                  @endforeach
                </div>
              @endif
            @endforeach
            <div class="sticky bottom-0 pt-2 pb-1 bg-white border-t border-gray-100 mt-1">
              <button type="button" @click="open = false"
                      class="w-full py-2 bg-blue-600 text-white text-xs font-semibold rounded-xl hover:bg-blue-700 transition">
                Done
              </button>
            </div>
          </div>
        </div>

        {{-- Reference Image --}}
        <div x-data="{ preview: null }">
          <label class="text-xs font-medium text-gray-600 mb-1 block">Reference Photo <span class="text-gray-400 font-normal">(optional)</span></label>
          <div class="flex items-center gap-3">
            <label class="cursor-pointer px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm text-gray-600 transition inline-flex items-center gap-1.5 active:scale-95">
              <i class="fa-solid fa-camera"></i> Choose Photo
              <input type="file" name="reference_image" accept="image/*" class="hidden"
                     @change="if($event.target.files[0]) { preview = URL.createObjectURL($event.target.files[0]) }">
            </label>
            <template x-if="preview">
              <img :src="preview" class="w-14 h-14 object-cover rounded-xl border border-gray-200">
            </template>
          </div>
        </div>

        <button type="submit"
                class="w-full py-3 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 active:scale-[0.98] transition shadow-sm">
          <i class="fa-solid fa-plus mr-1"></i> Create Task
        </button>
      </form>
    </div>

    {{-- ====== TASK CARDS ====== --}}
    <div id="task-sort-list" class="space-y-3">
      @forelse($allTasks as $t)
        @php $assignedIds = $t->assignedUsers->pluck('id')->toArray(); @endphp

        <div x-data="{ editing: false, expanded: false }"
             data-id="{{ $t->id }}"
             class="task-row bg-white border {{ $t->is_active ? 'border-gray-200' : 'border-gray-100 opacity-60' }} rounded-2xl shadow-sm overflow-hidden transition-all">

          {{-- Card Header (always visible) --}}
          <div class="px-4 py-3">
            <div class="flex items-center gap-3">
              {{-- Drag handle --}}
              <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 flex-shrink-0 select-none text-lg leading-none" title="Drag to reorder">⠿</span>

              {{-- Status dot --}}
              <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $t->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></div>

              {{-- Title + badges --}}
              <div class="flex-1 min-w-0" @click="expanded = !expanded">
                <div class="flex items-center flex-wrap gap-1.5">
                  <span class="text-sm font-semibold text-gray-800 truncate">{{ $t->title }}</span>
                </div>
                <div class="flex items-center flex-wrap gap-1.5 mt-1">
                  <span class="text-[10px] px-2 py-0.5 rounded-full font-medium
                    {{ $t->type === 'photo' ? 'bg-blue-100 text-blue-700' : ($t->type === 'note' ? 'bg-yellow-100 text-yellow-700' : ($t->type === 'photo_note' ? 'bg-indigo-100 text-indigo-700' : ($t->type === 'both' ? 'bg-pink-100 text-pink-700' : 'bg-gray-100 text-gray-600'))) }}">
                    {{ $t->type === 'photo' ? '📸 Photo' : ($t->type === 'note' ? '📝 Note' : ($t->type === 'photo_note' ? '📸 Photo+Note' : ($t->type === 'both' ? '📸📝 Both' : '📎 Any'))) }}
                  </span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ ($t->frequency ?? 'daily') === 'daily' ? 'bg-cyan-100 text-cyan-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ ($t->frequency ?? 'daily') === 'daily' ? '🔄 Daily' : '1️⃣ Once' }}
                  </span>
                  @if($t->task_time)
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-green-50 text-green-600 font-medium">
                      🕐 {{ \Carbon\Carbon::parse($t->task_time)->format('g:i A') }}
                    </span>
                  @endif
                  @if(!$t->is_active)
                    <span class="text-[10px] text-gray-400 italic">(inactive)</span>
                  @endif
                </div>
              </div>

              {{-- Expand chevron --}}
              <button @click="expanded = !expanded" class="flex-shrink-0 p-1 text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 transition-transform duration-200" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>
            </div>
          </div>

          {{-- Expanded Details --}}
          <div x-show="expanded" x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-[1000px]"
               x-cloak class="border-t border-gray-100">

            {{-- Details section --}}
            <div class="px-4 py-3 space-y-2 bg-gray-50/50" x-show="!editing">
              @if($t->description)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-20 flex-shrink-0">Description</span>
                  <span class="text-xs text-gray-700">{{ $t->description }}</span>
                </div>
              @endif
              <div class="flex items-start gap-2">
                <span class="text-xs text-gray-400 w-20 flex-shrink-0">Assigned</span>
                <span class="text-xs text-indigo-600">{{ $t->assignedUsers->count() ? $t->assignedUsers->pluck('name')->implode(', ') : 'Anyone' }}</span>
              </div>
              @if($t->ai_prompt)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-20 flex-shrink-0">AI Prompt</span>
                  <span class="text-xs text-purple-600 italic">{{ $t->ai_prompt }}</span>
                </div>
              @endif
              @if($t->approval_prompt)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-20 flex-shrink-0">Approval</span>
                  <span class="text-xs text-emerald-600 italic">{{ $t->approval_prompt }}</span>
                </div>
              @endif
              @if($t->reference_image)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-20 flex-shrink-0">Reference</span>
                  <img src="{{ Storage::url($t->reference_image) }}" class="w-16 h-16 object-cover rounded-xl border border-gray-200">
                </div>
              @endif

              {{-- Action buttons --}}
              <div class="flex gap-2 pt-2">
                <button @click="editing = true"
                        class="flex-1 py-2 text-xs font-medium rounded-xl border border-blue-200 text-blue-600 hover:bg-blue-50 active:scale-95 transition">
                  <i class="fa-solid fa-pen mr-1"></i> Edit
                </button>
                <form method="POST" action="{{ route('checklist.update-task', $t) }}" class="flex-1">
                  @csrf @method('PATCH')
                  <input type="hidden" name="title" value="{{ $t->title }}">
                  <input type="hidden" name="description" value="{{ $t->description }}">
                  <input type="hidden" name="type" value="{{ $t->type }}">
                  <input type="hidden" name="ai_prompt" value="{{ $t->ai_prompt }}">
                  <input type="hidden" name="approval_prompt" value="{{ $t->approval_prompt }}">
                  <input type="hidden" name="task_time" value="{{ $t->task_time }}">
                  <input type="hidden" name="frequency" value="{{ $t->frequency ?? 'daily' }}">
                  <input type="hidden" name="is_active" value="{{ $t->is_active ? '0' : '1' }}">
                  @foreach($assignedIds as $uid)
                    <input type="hidden" name="assigned_users[]" value="{{ $uid }}">
                  @endforeach
                  <button type="submit"
                          class="w-full py-2 text-xs font-medium rounded-xl border {{ $t->is_active ? 'border-amber-200 text-amber-600 hover:bg-amber-50' : 'border-green-200 text-green-600 hover:bg-green-50' }} active:scale-95 transition">
                    <i class="fa-solid {{ $t->is_active ? 'fa-eye-slash' : 'fa-eye' }} mr-1"></i>
                    {{ $t->is_active ? 'Disable' : 'Enable' }}
                  </button>
                </form>
                <form method="POST" action="{{ route('checklist.destroy-task', $t) }}"
                      onsubmit="return confirm('Delete \'{{ addslashes($t->title) }}\'?')" class="flex-shrink-0">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="py-2 px-3 text-xs font-medium rounded-xl border border-red-200 text-red-500 hover:bg-red-50 active:scale-95 transition">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </div>
            </div>

            {{-- Edit Form --}}
            <form method="POST" action="{{ route('checklist.update-task', $t) }}"
                  class="px-4 py-4 space-y-3 bg-blue-50/30" x-show="editing" x-transition
                  enctype="multipart/form-data">
              @csrf @method('PATCH')

              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Title *</label>
                <input type="text" name="title" value="{{ $t->title }}" required
                       class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
              </div>

              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Description</label>
                <input type="text" name="description" value="{{ $t->description }}" placeholder="Description..."
                       class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">Type</label>
                  <select name="type" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <option value="photo_note" {{ $t->type === 'photo_note' ? 'selected' : '' }}>📸 Photo+Note</option>
                    <option value="any" {{ $t->type === 'any' ? 'selected' : '' }}>📎 Any</option>
                    <option value="photo" {{ $t->type === 'photo' ? 'selected' : '' }}>📸 Photo</option>
                    <option value="note" {{ $t->type === 'note' ? 'selected' : '' }}>📝 Note</option>
                    <option value="both" {{ $t->type === 'both' ? 'selected' : '' }}>📸📝 Both</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">Frequency</label>
                  <select name="frequency" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <option value="daily" {{ ($t->frequency ?? 'daily') === 'daily' ? 'selected' : '' }}>🔄 Daily</option>
                    <option value="once" {{ ($t->frequency ?? 'daily') === 'once' ? 'selected' : '' }}>1️⃣ Once</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Scheduled Time</label>
                <input type="time" name="task_time" value="{{ $t->task_time ? \Carbon\Carbon::parse($t->task_time)->format('H:i') : '' }}"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
              </div>

              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">AI Prompt Focus</label>
                <textarea name="ai_prompt" rows="2" placeholder="e.g. Check if the workstation is clean..."
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none">{{ $t->ai_prompt }}</textarea>
              </div>

              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Approval Criteria</label>
                <textarea name="approval_prompt" rows="2" placeholder="e.g. The floor must be visibly clean..."
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 resize-none">{{ $t->approval_prompt }}</textarea>
              </div>

              <input type="hidden" name="is_active" value="{{ $t->is_active ? '1' : '0' }}">

              {{-- Assign to --}}
              <div x-data="{ editOpen: false, editCount: {{ count($assignedIds) }}, updateEditCount() { this.editCount = this.$root.querySelectorAll('.edit-cb-{{ $t->id }}:checked').length; } }" class="relative">
                <label class="text-xs font-medium text-gray-600 mb-1 block">Assigned to</label>
                <button type="button" @click="editOpen = !editOpen"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-left bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400 flex items-center justify-between gap-2">
                  <span class="text-gray-500" x-text="editCount === 0 ? 'Anyone (no filter)' : editCount + ' user(s) selected'">{{ count($assignedIds) > 0 ? count($assignedIds) . ' user(s) selected' : 'Anyone (no filter)' }}</span>
                  <svg class="w-4 h-4 text-gray-400 transition-transform" :class="editOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="editOpen" x-transition
                     class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto p-2 space-y-1">
                  @foreach($roles as $role)
                    @if($role->users->count() > 0)
                      <div class="space-y-0.5">
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-gray-50 cursor-pointer hover:bg-gray-100 font-medium text-xs text-gray-700"
                               @click.prevent="
                                 const cbs = $el.closest('.space-y-0\\.5').querySelectorAll('input.edit-cb-{{ $t->id }}');
                                 const allChecked = [...cbs].every(c => c.checked);
                                 cbs.forEach(c => c.checked = !allChecked);
                                 updateEditCount();
                               ">
                          <input type="checkbox" class="accent-blue-600 pointer-events-none"
                                 @click.stop="
                                   const cbs = $el.closest('.space-y-0\\.5').querySelectorAll('input.edit-cb-{{ $t->id }}');
                                   cbs.forEach(c => c.checked = $el.checked);
                                   updateEditCount();
                                 ">
                          {{ $role->name }}
                        </label>
                        @foreach($role->users as $u)
                          <label class="flex items-center gap-2 px-2 py-1 pl-7 rounded-lg cursor-pointer hover:bg-gray-50 text-xs text-gray-600">
                            <input type="checkbox" name="assigned_users[]" value="{{ $u->id }}"
                                   {{ in_array($u->id, $assignedIds) ? 'checked' : '' }}
                                   class="accent-blue-600 edit-cb-{{ $t->id }}"
                                   @change="updateEditCount()">
                            {{ $u->name }}
                          </label>
                        @endforeach
                      </div>
                    @endif
                  @endforeach
                  <div class="sticky bottom-0 pt-2 pb-1 bg-white border-t border-gray-100 mt-1">
                    <button type="button" @click="editOpen = false"
                            class="w-full py-2 bg-blue-600 text-white text-xs font-semibold rounded-xl hover:bg-blue-700 transition">
                      Done
                    </button>
                  </div>
                </div>
              </div>

              {{-- Reference Image --}}
              <div x-data="{ editPreview: {{ $t->reference_image ? "'" . Storage::url($t->reference_image) . "'" : 'null' }}, removeImg: false }">
                <label class="text-xs font-medium text-gray-600 mb-1 block">Reference Photo</label>
                <div class="flex items-center gap-3">
                  <label class="cursor-pointer px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm text-gray-600 transition inline-flex items-center gap-1.5 active:scale-95">
                    <i class="fa-solid fa-camera"></i> {{ $t->reference_image ? 'Change' : 'Choose' }}
                    <input type="file" name="reference_image" accept="image/*" class="hidden"
                           @change="if($event.target.files[0]) { editPreview = URL.createObjectURL($event.target.files[0]); removeImg = false; }">
                  </label>
                  <template x-if="editPreview && !removeImg">
                    <div class="flex items-center gap-2">
                      <img :src="editPreview" class="w-14 h-14 object-cover rounded-xl border border-gray-200">
                      <button type="button" @click="removeImg = true; editPreview = null" class="text-xs text-red-500 hover:text-red-700">
                        <i class="fa-solid fa-xmark"></i> Remove
                      </button>
                    </div>
                  </template>
                  <template x-if="removeImg">
                    <input type="hidden" name="remove_reference_image" value="1">
                  </template>
                </div>
              </div>

              <div class="flex gap-2 pt-1">
                <button type="submit" class="flex-1 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 active:scale-[0.98] transition">
                  <i class="fa-solid fa-check mr-1"></i> Save
                </button>
                <button type="button" @click="editing = false" class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-xl hover:bg-gray-200 active:scale-95 transition">
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      @empty
        <div class="bg-white border border-gray-200 rounded-2xl py-12 text-center">
          <div class="text-4xl mb-2">📋</div>
          <p class="text-sm text-gray-500">No tasks yet</p>
          <p class="text-xs text-gray-400 mt-1">Tap "New Task" to create one</p>
        </div>
      @endforelse
    </div>

  </div>

  <script>
    (function () {
      const list = document.getElementById('task-sort-list');
      if (!list) return;

      let dragging = null;

      list.querySelectorAll('.task-row').forEach(row => {
        row.setAttribute('draggable', 'false');

        const handle = row.querySelector('.drag-handle');
        if (handle) {
          handle.addEventListener('mousedown', () => row.setAttribute('draggable', 'true'));
          handle.addEventListener('mouseup', () => row.setAttribute('draggable', 'false'));
          // Touch drag support
          handle.addEventListener('touchstart', () => row.setAttribute('draggable', 'true'));
          handle.addEventListener('touchend', () => row.setAttribute('draggable', 'false'));
        }

        row.addEventListener('dragstart', e => {
          dragging = row;
          row.classList.add('opacity-40', 'ring-2', 'ring-blue-400');
          e.dataTransfer.effectAllowed = 'move';
        });

        row.addEventListener('dragend', () => {
          row.setAttribute('draggable', 'false');
          row.classList.remove('opacity-40', 'ring-2', 'ring-blue-400');
          list.querySelectorAll('.task-row').forEach(r => r.classList.remove('border-t-2', 'border-blue-400'));
          dragging = null;
          saveOrder();
        });

        row.addEventListener('dragover', e => {
          e.preventDefault();
          if (!dragging || dragging === row) return;
          const rect = row.getBoundingClientRect();
          const mid = rect.top + rect.height / 2;
          list.querySelectorAll('.task-row').forEach(r => r.classList.remove('border-t-2', 'border-blue-400'));
          if (e.clientY < mid) {
            row.classList.add('border-t-2', 'border-blue-400');
            list.insertBefore(dragging, row);
          } else {
            row.classList.add('border-t-2', 'border-blue-400');
            list.insertBefore(dragging, row.nextSibling);
          }
        });
      });

      function saveOrder() {
        const ids = [...list.querySelectorAll('.task-row')].map(r => r.dataset.id);
        fetch('{{ route("checklist.reorder") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}',
          },
          body: JSON.stringify({ order: ids }),
        });
      }
    })();
  </script>

</x-layout>
