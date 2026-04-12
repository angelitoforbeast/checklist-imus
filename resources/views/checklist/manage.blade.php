<x-layout>
  <x-slot name="heading">Manage Checklist Tasks</x-slot>
  <x-slot name="title">Manage Checklist Tasks</x-slot>

  <div class="p-4 max-w-4xl mx-auto space-y-4 mt-16">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <h1 class="text-xl font-bold text-gray-800">Manage Tasks</h1>
        <p class="text-sm text-gray-500">Add, edit, reorder, or delete checklist tasks.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('checklist.report') }}"
           class="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-700">
          📋 View Report
        </a>
        <a href="{{ route('checklist.index') }}"
           class="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-700">
          ← Checklist
        </a>
      </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
      <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
        @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
      </div>
    @endif

    @php
      $roles = \App\Models\Role::with('users')->orderBy('name')->get();
    @endphp

    {{-- ====== ADD TASK FORM ====== --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 space-y-3">
      <h2 class="font-semibold text-gray-800 text-sm">Add New Task</h2>

      <form method="POST" action="{{ route('checklist.store-task') }}" class="space-y-3" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
          <input type="text" name="title" placeholder="Task title..." required
                 class="sm:col-span-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
          <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
            <option value="photo_note">📸 Photo required, note optional</option>
            <option value="any">📎 Any (photo or note)</option>
            <option value="photo">📸 Photo only</option>
            <option value="note">📝 Note only</option>
            <option value="both">📸📝 Photo + Note (both required)</option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <input type="text" name="description" placeholder="Description (optional)..."
                 class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
          <div class="flex items-center gap-2">
            <label class="text-xs text-gray-500 whitespace-nowrap">Scheduled Time:</label>
            <input type="time" name="task_time"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 flex-1">
          </div>
        </div>

        <div>
          <label class="text-xs text-gray-500 mb-1 block">AI Prompt Focus <span class="text-gray-400">(optional — guides what the AI checks for)</span>:</label>
          <textarea name="ai_prompt" rows="2" placeholder="e.g. Check if the workstation is clean and items are organized properly..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none"></textarea>
        </div>

        <div>
          <label class="text-xs text-gray-500 mb-1 block">Approval Criteria <span class="text-gray-400">(optional — guides the approval check)</span>:</label>
          <textarea name="approval_prompt" rows="2" placeholder="e.g. The floor must be visibly clean with no debris or stains..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-400 resize-none"></textarea>
        </div>

        {{-- Assign to - Dropdown grouped by role --}}
        <div x-data="{ open: false, selectedCount: 0, updateCount() { this.selectedCount = this.$root.querySelectorAll('.add-form-cb:checked').length; } }" class="relative">
          <label class="text-xs text-gray-500 mb-1.5 block">Assign to (leave blank = anyone can submit):</label>
          <button type="button" @click="open = !open"
                  class="w-full sm:w-auto min-w-[280px] border border-gray-300 rounded-lg px-3 py-2 text-sm text-left bg-white hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-blue-400 flex items-center justify-between gap-2">
            <span class="text-gray-500" x-text="selectedCount === 0 ? 'Select users...' : selectedCount + ' user(s) selected'">Select users...</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>

          <div x-show="open" x-transition
               class="absolute z-20 mt-1 w-full sm:w-[320px] bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto p-2 space-y-1">
            @foreach($roles as $role)
              @if($role->users->count() > 0)
                <div class="space-y-0.5">
                  {{-- Role header with select-all checkbox --}}
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
                  {{-- Individual users --}}
                  @foreach($role->users as $u)
                    <label class="flex items-center gap-2 px-2 py-1 pl-7 rounded-lg cursor-pointer hover:bg-gray-50 text-xs text-gray-600">
                      <input type="checkbox" name="assigned_users[]" value="{{ $u->id }}" class="accent-blue-600 add-form-cb"
                             @change="updateCount()">
                      {{ $u->name }}
                      <span class="text-gray-300 text-xs">{{ $u->email }}</span>
                    </label>
                  @endforeach
                </div>
              @endif
            @endforeach
            {{-- Okay button --}}
            <div class="sticky bottom-0 pt-2 pb-1 bg-white border-t border-gray-100 mt-1">
              <button type="button" @click="open = false"
                      class="w-full py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                Okay
              </button>
            </div>
          </div>
        </div>

        {{-- Reference Image --}}
        <div x-data="{ preview: null }">
          <label class="text-xs text-gray-500 mb-1 block">Reference Photo <span class="text-gray-400">(optional — shown to user as guide)</span>:</label>
          <div class="flex items-center gap-3">
            <label class="cursor-pointer px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm text-gray-600 transition inline-flex items-center gap-1.5">
              📷 Choose Photo
              <input type="file" name="reference_image" accept="image/*" class="hidden"
                     @change="if($event.target.files[0]) { preview = URL.createObjectURL($event.target.files[0]) }">
            </label>
            <template x-if="preview">
              <img :src="preview" class="w-16 h-16 object-cover rounded-lg border border-gray-200">
            </template>
          </div>
        </div>

        <button type="submit"
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
          + Add Task
        </button>
      </form>
    </div>

    {{-- ====== TASK LIST ====== --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm divide-y divide-gray-100">
      <div class="px-4 py-3 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800 text-sm">All Tasks ({{ $allTasks->count() }})</h2>
        @if($allTasks->count() > 1)
          <span class="text-xs text-gray-400">⠿ Drag to reorder</span>
        @endif
      </div>

      <div id="task-sort-list" class="divide-y divide-gray-100">
      @forelse($allTasks as $t)
        @php $assignedIds = $t->assignedUsers->pluck('id')->toArray(); @endphp

        <div x-data="{ editing: false }"
             data-id="{{ $t->id }}"
             class="task-row {{ $t->is_active ? '' : 'opacity-60 bg-gray-50' }}">

          {{-- View Row --}}
          <div class="flex items-center gap-3 px-4 py-3" x-show="!editing">
            <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 flex-shrink-0 select-none text-lg leading-none" title="Drag to reorder">⠿</span>
            <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $t->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center flex-wrap gap-1.5">
                <span class="text-sm font-medium text-gray-800">{{ $t->title }}</span>
                <span class="text-xs px-1.5 py-0.5 rounded-full
                  {{ $t->type === 'photo' ? 'bg-blue-100 text-blue-700' : ($t->type === 'note' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                  {{ $t->type === 'photo' ? '📸' : ($t->type === 'note' ? '📝' : '📎') }}
                  {{ ucfirst($t->type) }}
                </span>
                @if($t->task_time)
                  <span class="text-xs px-1.5 py-0.5 rounded-full bg-green-50 text-green-600">
                    🕐 {{ \Carbon\Carbon::parse($t->task_time)->format('g:i A') }}
                  </span>
                @endif
                @if(!$t->is_active)
                  <span class="text-xs text-gray-400 italic">(inactive)</span>
                @endif
              </div>
              @if($t->description)
                <p class="text-xs text-gray-400 mt-0.5">{{ $t->description }}</p>
              @endif
              @if($t->assignedUsers->count())
                <p class="text-xs text-indigo-500 mt-0.5">→ {{ $t->assignedUsers->pluck('name')->implode(', ') }}</p>
              @else
                <p class="text-xs text-gray-400 mt-0.5">→ Anyone</p>
              @endif
              @if($t->ai_prompt)
                <p class="text-xs text-purple-500 mt-0.5 italic truncate max-w-sm" title="{{ $t->ai_prompt }}">🤖 {{ $t->ai_prompt }}</p>
              @endif
              @if($t->approval_prompt)
                <p class="text-xs text-emerald-500 mt-0.5 italic truncate max-w-sm" title="{{ $t->approval_prompt }}">☑ {{ $t->approval_prompt }}</p>
              @endif
              @if($t->reference_image)
                <div class="mt-1">
                  <img src="{{ Storage::url($t->reference_image) }}" class="w-12 h-12 object-cover rounded-lg border border-gray-200" title="Reference photo">
                </div>
              @endif
            </div>
            <div class="flex gap-1 flex-shrink-0">
              <button @click="editing = true"
                      class="text-xs px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">Edit</button>
              <form method="POST" action="{{ route('checklist.update-task', $t) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="title"       value="{{ $t->title }}">
                <input type="hidden" name="description" value="{{ $t->description }}">
                <input type="hidden" name="type"        value="{{ $t->type }}">
                <input type="hidden" name="ai_prompt"   value="{{ $t->ai_prompt }}">
                <input type="hidden" name="approval_prompt" value="{{ $t->approval_prompt }}">
                <input type="hidden" name="task_time"   value="{{ $t->task_time }}">
                <input type="hidden" name="is_active"   value="{{ $t->is_active ? '0' : '1' }}">
                @foreach($assignedIds as $uid)
                  <input type="hidden" name="assigned_users[]" value="{{ $uid }}">
                @endforeach
                <button type="submit"
                        class="text-xs px-2 py-1 rounded border {{ $t->is_active ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                  {{ $t->is_active ? 'Disable' : 'Enable' }}
                </button>
              </form>
              <form method="POST" action="{{ route('checklist.destroy-task', $t) }}"
                    onsubmit="return confirm('Delete \'{{ addslashes($t->title) }}\'? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="text-xs px-2 py-1 rounded border border-red-300 text-red-600 hover:bg-red-50">
                  Delete
                </button>
              </form>
            </div>
          </div>

          {{-- Edit Mode --}}
          <form method="POST" action="{{ route('checklist.update-task', $t) }}"
                class="px-4 py-3 space-y-2 bg-blue-50/30" x-show="editing" x-transition
                enctype="multipart/form-data">
            @csrf @method('PATCH')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <input type="text" name="title" value="{{ $t->title }}" required
                     class="sm:col-span-2 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
              <select name="type" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
                <option value="photo_note" {{ $t->type === 'photo_note' ? 'selected' : '' }}>📸 Photo + Note optional</option>
                <option value="any"   {{ $t->type === 'any'   ? 'selected' : '' }}>📎 Any</option>
                <option value="photo" {{ $t->type === 'photo' ? 'selected' : '' }}>📸 Photo</option>
                <option value="note"  {{ $t->type === 'note'  ? 'selected' : '' }}>📝 Note</option>
                <option value="both"  {{ $t->type === 'both'  ? 'selected' : '' }}>📸📝 Photo + Note</option>
              </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <input type="text" name="description" value="{{ $t->description }}"
                     placeholder="Description (optional)..."
                     class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
              <div class="flex items-center gap-2">
                <label class="text-xs text-gray-500 whitespace-nowrap">Time:</label>
                <input type="time" name="task_time" value="{{ $t->task_time ? \Carbon\Carbon::parse($t->task_time)->format('H:i') : '' }}"
                       class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 flex-1">
              </div>
            </div>

            <div>
              <label class="text-xs text-gray-500 mb-1 block">AI Prompt Focus <span class="text-gray-400">(optional)</span>:</label>
              <textarea name="ai_prompt" rows="2" placeholder="e.g. Check if the workstation is clean and items are organized properly..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none">{{ $t->ai_prompt }}</textarea>
            </div>

            <div>
              <label class="text-xs text-gray-500 mb-1 block">Approval Criteria <span class="text-gray-400">(optional)</span>:</label>
              <textarea name="approval_prompt" rows="2" placeholder="e.g. The floor must be visibly clean with no debris or stains..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-400 resize-none">{{ $t->approval_prompt }}</textarea>
            </div>
            <input type="hidden" name="is_active" value="{{ $t->is_active ? '1' : '0' }}">

            {{-- Assign to - Dropdown grouped by role (Edit mode) --}}
            <div x-data="{ editOpen: false, editCount: {{ count($assignedIds) }}, updateEditCount() { this.editCount = this.$root.querySelectorAll('.edit-cb-{{ $t->id }}:checked').length; } }" class="relative">
              <label class="text-xs text-gray-500 mb-1.5 block">Assigned to (blank = anyone):</label>
              <button type="button" @click="editOpen = !editOpen"
                      class="w-full sm:w-auto min-w-[280px] border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-left bg-white hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-blue-400 flex items-center justify-between gap-2">
                <span class="text-gray-500" x-text="editCount === 0 ? 'Select users...' : editCount + ' user(s) selected'">{{ count($assignedIds) > 0 ? count($assignedIds) . ' user(s) selected' : 'Select users...' }}</span>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="editOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </button>

              <div x-show="editOpen" x-transition
                   class="absolute z-20 mt-1 w-full sm:w-[320px] bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto p-2 space-y-1">
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
                        <span class="text-gray-400 font-normal">({{ $role->users->count() }})</span>
                      </label>
                      @foreach($role->users as $u)
                        <label class="flex items-center gap-2 px-2 py-1 pl-7 rounded-lg cursor-pointer hover:bg-gray-50 text-xs text-gray-600">
                          <input type="checkbox" name="assigned_users[]" value="{{ $u->id }}"
                                 {{ in_array($u->id, $assignedIds) ? 'checked' : '' }}
                                 class="accent-blue-600 edit-cb-{{ $t->id }}"
                                 @change="updateEditCount()">
                          {{ $u->name }}
                          <span class="text-gray-300 text-xs">{{ $u->email }}</span>
                        </label>
                      @endforeach
                    </div>
                  @endif
                @endforeach
                {{-- Okay button --}}
                <div class="sticky bottom-0 pt-2 pb-1 bg-white border-t border-gray-100 mt-1">
                  <button type="button" @click="editOpen = false"
                          class="w-full py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                    Okay
                  </button>
                </div>
              </div>
            </div>

            {{-- Reference Image (Edit) --}}
            <div x-data="{ editPreview: {{ $t->reference_image ? "'" . Storage::url($t->reference_image) . "'" : 'null' }}, removeImg: false }">
              <label class="text-xs text-gray-500 mb-1 block">Reference Photo:</label>
              <div class="flex items-center gap-3">
                <label class="cursor-pointer px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs text-gray-600 transition inline-flex items-center gap-1.5">
                  📷 {{ $t->reference_image ? 'Change Photo' : 'Choose Photo' }}
                  <input type="file" name="reference_image" accept="image/*" class="hidden"
                         @change="if($event.target.files[0]) { editPreview = URL.createObjectURL($event.target.files[0]); removeImg = false; }">
                </label>
                <template x-if="editPreview && !removeImg">
                  <div class="flex items-center gap-2">
                    <img :src="editPreview" class="w-12 h-12 object-cover rounded-lg border border-gray-200">
                    <button type="button" @click="removeImg = true; editPreview = null" class="text-xs text-red-500 hover:text-red-700">✕ Remove</button>
                  </div>
                </template>
                <template x-if="removeImg">
                  <input type="hidden" name="remove_reference_image" value="1">
                </template>
              </div>
            </div>

            <div class="flex gap-2 pt-1">
              <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 font-medium">Save Changes</button>
              <button type="button" @click="editing = false" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs rounded-lg hover:bg-gray-200">Cancel</button>
            </div>
          </form>
        </div>
      @empty
        <div class="px-4 py-10 text-center text-sm text-gray-400">No tasks yet. Add one above.</div>
      @endforelse
      </div> {{-- #task-sort-list --}}
    </div>

  </div>

  <script>
    (function () {
      const list    = document.getElementById('task-sort-list');
      if (!list) return;

      let dragging  = null;

      list.querySelectorAll('.task-row').forEach(row => {
        row.setAttribute('draggable', 'false');

        const handle = row.querySelector('.drag-handle');
        if (handle) {
          handle.addEventListener('mousedown', () => row.setAttribute('draggable', 'true'));
          handle.addEventListener('mouseup',   () => row.setAttribute('draggable', 'false'));
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
          const mid  = rect.top + rect.height / 2;
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
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                         || '{{ csrf_token() }}',
          },
          body: JSON.stringify({ order: ids }),
        });
      }
    })();
  </script>

</x-layout>
