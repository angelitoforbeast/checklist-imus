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
      $roles = \App\Models\Role::with('users')->where('name', '!=', 'Admin')->orderBy('name')->get();
      $allUsers = $roles->flatMap(fn($r) => $r->users);
      $totalUsers = $allUsers->count();

      // Build a lookup: task_id => [user_ids]
      $taskUserMap = [];
      $taskRoleMap = [];
      foreach ($allTasks as $t) {
          $tUserIds = $t->assignedUsers->pluck('id')->toArray();
          $taskUserMap[$t->id] = $tUserIds;
          // Map task to roles via assigned users
          $tRoleIds = [];
          foreach ($roles as $role) {
              $roleUserIds = $role->users->pluck('id')->toArray();
              if (!empty(array_intersect($tUserIds, $roleUserIds))) {
                  $tRoleIds[] = $role->id;
              }
          }
          $taskRoleMap[$t->id] = $tRoleIds;
      }
    @endphp

    {{-- ====== FILTER BAR ====== --}}
    <div x-data="{
      filterRole: '',
      filterUser: '',
      taskUserMap: {{ json_encode($taskUserMap) }},
      taskRoleMap: {{ json_encode($taskRoleMap) }},
      usersForRole: {},
      init() {
        this.usersForRole = {{ json_encode($roles->mapWithKeys(fn($r) => [$r->id => $r->users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()])) }};
        this.$watch('filterRole', () => { this.filterUser = ''; this.applyFilter(); });
        this.$watch('filterUser', () => this.applyFilter());
      },
      get availableUsers() {
        if (!this.filterRole) return {{ json_encode($allUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->unique('id')->sortBy('name')->values()) }};
        return this.usersForRole[this.filterRole] || [];
      },
      applyFilter() {
        const cards = document.querySelectorAll('.task-row');
        let shown = 0;
        cards.forEach(card => {
          const tid = card.dataset.id;
          const taskUsers = this.taskUserMap[tid] || [];
          const taskRoles = this.taskRoleMap[tid] || [];
          let visible = true;

          // If task has no assigned users (anyone), always show
          const isUnassigned = taskUsers.length === 0;

          if (this.filterRole && !isUnassigned) {
            visible = taskRoles.includes(parseInt(this.filterRole));
          }
          if (visible && this.filterUser && !isUnassigned) {
            visible = taskUsers.includes(parseInt(this.filterUser));
          }
          // Unassigned tasks (anyone) always show
          if (isUnassigned) visible = true;

          card.style.display = visible ? '' : 'none';
          if (visible) shown++;
        });
        // Update counter
        const counter = document.getElementById('filter-count');
        if (counter) counter.textContent = shown + ' task' + (shown !== 1 ? 's' : '') + ' shown';
      },
      clearFilters() {
        this.filterRole = '';
        this.filterUser = '';
      }
    }" class="bg-white border border-gray-200 rounded-2xl px-4 py-3 shadow-sm">
      <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-1.5 text-xs text-gray-500 flex-shrink-0">
          <i class="fa-solid fa-filter text-gray-400"></i>
          <span class="font-medium">Filter:</span>
        </div>

        {{-- Role filter --}}
        <select x-model="filterRole"
                class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-400 cursor-pointer">
          <option value="">All Roles</option>
          @foreach($roles as $role)
            <option value="{{ $role->id }}">{{ $role->name }} ({{ $role->users->count() }})</option>
          @endforeach
        </select>

        {{-- User filter --}}
        <select x-model="filterUser"
                class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-400 cursor-pointer">
          <option value="">All Users</option>
          <template x-for="u in availableUsers" :key="u.id">
            <option :value="u.id" x-text="u.name"></option>
          </template>
        </select>

        {{-- Clear button --}}
        <button x-show="filterRole || filterUser" x-transition @click="clearFilters()"
                class="text-xs text-red-500 hover:text-red-700 font-medium px-2 py-1 rounded-lg hover:bg-red-50 transition">
          <i class="fa-solid fa-xmark mr-0.5"></i> Clear
        </button>

        <span id="filter-count" class="text-[10px] text-gray-400 ml-auto">{{ $allTasks->count() }} task{{ $allTasks->count() !== 1 ? 's' : '' }} shown</span>
      </div>
    </div>

    {{-- ====== ADD TASK FORM (Slide-down) ====== --}}
    <div x-data="{
           showForm: {{ $errors->any() ? 'true' : 'false' }},
           taskType: '{{ old('type', 'photo_note') }}',
           requiredPhotos: {{ old('required_photos', 1) }},
           freq: '{{ old('frequency', 'daily') }}',
           subMode: '{{ old('submission_mode', 'group') }}',
           assignOpen: false,
           selectedUsers: new Set(),
           get selectedCount() { return this.selectedUsers.size; },
           toggleUser(id) {
             if (this.selectedUsers.has(id)) this.selectedUsers.delete(id);
             else this.selectedUsers.add(id);
             this.selectedUsers = new Set(this.selectedUsers);
           },
           toggleRole(userIds) {
             const allChecked = userIds.every(id => this.selectedUsers.has(id));
             userIds.forEach(id => { if (allChecked) this.selectedUsers.delete(id); else this.selectedUsers.add(id); });
             this.selectedUsers = new Set(this.selectedUsers);
           },
           toggleAll() {
             const allIds = {{ json_encode($allUsers->pluck('id')->values()->toArray()) }};
             const allChecked = allIds.every(id => this.selectedUsers.has(id));
             allIds.forEach(id => { if (allChecked) this.selectedUsers.delete(id); else this.selectedUsers.add(id); });
             this.selectedUsers = new Set(this.selectedUsers);
           },
           isAllSelected() {
             const allIds = {{ json_encode($allUsers->pluck('id')->values()->toArray()) }};
             return allIds.length > 0 && allIds.every(id => this.selectedUsers.has(id));
           },
           isRoleSelected(userIds) { return userIds.length > 0 && userIds.every(id => this.selectedUsers.has(id)); }
         }"
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
          <textarea name="description" placeholder="Brief description..." rows="2"
                    class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent resize-y">{{ old('description') }}</textarea>
        </div>

        {{-- Instructions --}}
        <div>
          <label class="text-xs font-medium text-gray-600 mb-1 block">Instructions <span class="text-gray-400 font-normal">(optional - visible to users)</span></label>
          <textarea name="instructions" placeholder="Step-by-step instructions for users..." rows="3"
                    class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent resize-y">{{ old('instructions') }}</textarea>
          <p class="text-[10px] text-gray-400 mt-0.5">Users will see these instructions when they open the task</p>
        </div>

        {{-- Type + Submission Mode row --}}
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Task Type</label>
            <select name="type" x-model="taskType" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
              <option value="photo_note">📸 Photo + Note</option>
              <option value="any">📎 Any</option>
              <option value="photo">📸 Photo only</option>
              <option value="note">📝 Note only</option>
              <option value="both">📸📝 Both required</option>
              <option value="announcement">📢 Announcement</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Submission Mode</label>
            <select name="submission_mode" x-model="subMode" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                    :disabled="taskType === 'announcement'" :class="taskType === 'announcement' && 'bg-gray-100 text-gray-400'">
              <option value="group">👥 Group</option>
              <option value="individual">👤 Individual</option>
            </select>
            <p class="text-[10px] text-gray-400 mt-0.5" x-show="taskType === 'announcement'">Announcements are always individual</p>
          </div>
        </div>

        {{-- Required Photos: Before Start (left) + Before Done (right) --}}
        <div x-show="['photo','photo_note','both'].includes(taskType)" x-transition class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">📸 Photos Before Start <span class="text-gray-400">(0 = none)</span></label>
            <input type="number" name="required_photos_before_start" min="0" max="50" value="{{ old('required_photos_before_start', 0) }}"
                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent">
            <p class="text-[10px] text-gray-400 mt-1">Must upload before Start button appears</p>
          </div>
          <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">📸 Photos Before Done <span class="text-gray-400">(min. 1)</span></label>
            <input type="number" name="required_photos" x-model="requiredPhotos" min="1" max="50" value="1"
                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
            <p class="text-[10px] text-gray-400 mt-1">Must upload before Done button appears</p>
          </div>
        </div>

        {{-- Announcement info box --}}
        <div x-show="taskType === 'announcement'" x-transition class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
          <p class="text-xs font-semibold text-amber-700 flex items-center gap-1.5 mb-1">📢 Announcement Mode</p>
          <p class="text-xs text-amber-600 leading-relaxed">This task will appear as an announcement. Users will see the title and description, then tap <strong>"Acknowledge"</strong> to confirm they've read it. No photos or notes required. Each assigned user must acknowledge individually.</p>
        </div>

        {{-- Frequency + Schedule --}}
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-gray-600 mb-1 block">Frequency</label>
              <select name="frequency" x-model="freq" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <option value="daily">🔄 Daily</option>
                <option value="once">1️⃣ Once</option>
                <option value="weekly">📅 Weekly</option>
                <option value="monthly">🗓️ Monthly</option>
                <option value="custom">📌 Custom Dates</option>
                <option value="recurring_on_complete">🔁 Recurring on Complete</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-gray-600 mb-1 block">Scheduled Time <span class="text-gray-400 font-normal">(opt.)</span></label>
              <input type="time" name="task_time" value="{{ old('task_time') }}"
                     class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
            </div>
          </div>

          {{-- Weekly: day checkboxes --}}
          <div x-show="freq === 'weekly'" x-transition class="bg-gray-50 rounded-xl p-3">
            <label class="text-xs font-medium text-gray-600 mb-2 block">Days of Week</label>
            <div class="flex flex-wrap gap-2">
              @foreach(['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '0' => 'Sun'] as $val => $day)
                <label class="flex items-center gap-1 px-2.5 py-1.5 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 text-xs has-[:checked]:bg-blue-100 has-[:checked]:border-blue-300 has-[:checked]:text-blue-700 transition">
                  <input type="checkbox" name="schedule_days[]" value="{{ $val }}" class="accent-blue-600 w-3 h-3"
                         {{ is_array(old('schedule_days')) && in_array($val, old('schedule_days')) ? 'checked' : '' }}>
                  {{ $day }}
                </label>
              @endforeach
            </div>
          </div>

          {{-- Monthly: day of month --}}
          <div x-show="freq === 'monthly'" x-transition class="bg-gray-50 rounded-xl p-3">
            <label class="text-xs font-medium text-gray-600 mb-2 block">Days of Month</label>
            <div class="flex flex-wrap gap-1.5">
              @for($d = 1; $d <= 31; $d++)
                <label class="w-8 h-8 flex items-center justify-center bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 text-xs has-[:checked]:bg-blue-100 has-[:checked]:border-blue-300 has-[:checked]:text-blue-700 transition">
                  <input type="checkbox" name="schedule_days[]" value="{{ $d }}" class="hidden"
                         {{ is_array(old('schedule_days')) && in_array((string)$d, old('schedule_days')) ? 'checked' : '' }}>
                  {{ $d }}
                </label>
              @endfor
            </div>
          </div>

          {{-- Custom: date picker --}}
          <div x-show="freq === 'custom'" x-transition class="bg-gray-50 rounded-xl p-3" x-data="{ dates: {{ json_encode(old('schedule_dates', [])) }} }">
            <label class="text-xs font-medium text-gray-600 mb-2 block">Specific Dates</label>
            <div class="flex gap-2 mb-2">
              <input type="date" x-ref="newDate" class="flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
              <button type="button" @click="if($refs.newDate.value && !dates.includes($refs.newDate.value)) { dates.push($refs.newDate.value); $refs.newDate.value=''; }"
                      class="px-3 py-2 bg-blue-600 text-white text-sm rounded-xl hover:bg-blue-700 active:scale-95 transition">
                <i class="fa-solid fa-plus"></i>
              </button>
            </div>
            <div class="flex flex-wrap gap-1.5">
              <template x-for="(dt, i) in dates" :key="i">
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs">
                  <span x-text="dt"></span>
                  <button type="button" @click="dates.splice(i, 1)" class="text-blue-400 hover:text-blue-600">&times;</button>
                  <input type="hidden" name="schedule_dates[]" :value="dt">
                </span>
              </template>
            </div>
            <p class="text-[10px] text-gray-400 mt-1" x-show="dates.length === 0">No dates selected</p>
          </div>

          {{-- Recurring on Complete: delay + max count --}}
          <div x-show="freq === 'recurring_on_complete'" x-transition class="bg-gray-50 rounded-xl p-3 space-y-2">
            <p class="text-xs text-gray-500">⏱️ When this task is completed, a new instance will auto-spawn after the delay below.</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Respawn Delay <span class="text-gray-400 font-normal">(minutes)</span></label>
                <input type="number" name="respawn_delay_minutes" min="0" max="1440" value="{{ old('respawn_delay_minutes', 5) }}"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <p class="text-[10px] text-gray-400 mt-1">0 = spawn immediately. Default: 5</p>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Max per Day</label>
                <input type="number" name="max_daily_count" min="1" max="100" value="{{ old('max_daily_count', 10) }}"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <p class="text-[10px] text-gray-400 mt-1">Hard cap on daily spawns. Default: 10</p>
              </div>
            </div>
          </div>

          {{-- Start / End date --}}
          <div class="grid grid-cols-2 gap-3" x-show="freq !== 'once' && freq !== 'custom' && freq !== 'recurring_on_complete'" x-transition>
            <div>
              <label class="text-xs font-medium text-gray-600 mb-1 block">Start Date <span class="text-gray-400 font-normal">(opt.)</span></label>
              <input type="date" name="start_date" value="{{ old('start_date') }}"
                     class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
            </div>
            <div>
              <label class="text-xs font-medium text-gray-600 mb-1 block">End Date <span class="text-gray-400 font-normal">(opt.)</span></label>
              <input type="date" name="end_date" value="{{ old('end_date') }}"
                     class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
            </div>
          </div>
        </div>

        {{-- AI Prompt --}}
        <div x-data="{ show: false }" x-show="taskType !== 'announcement'">
          <button type="button" @click="show = !show" class="text-xs text-purple-600 hover:text-purple-800 flex items-center gap-1 mb-1">
            <i class="fa-solid fa-robot"></i> AI Prompt Focus
            <svg class="w-3 h-3 transition-transform" :class="show && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <textarea x-show="show" x-transition name="ai_prompt" rows="2" placeholder="e.g. Check if the workstation is clean..."
                    class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none">{{ old('ai_prompt') }}</textarea>
        </div>

        {{-- Approval Criteria --}}
        <div x-data="{ show: false }" x-show="taskType !== 'announcement'">
          <button type="button" @click="show = !show" class="text-xs text-emerald-600 hover:text-emerald-800 flex items-center gap-1 mb-1">
            <i class="fa-solid fa-clipboard-check"></i> Approval Criteria
            <svg class="w-3 h-3 transition-transform" :class="show && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <textarea x-show="show" x-transition name="approval_prompt" rows="2" placeholder="e.g. The floor must be visibly clean..."
                    class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 resize-none">{{ old('approval_prompt') }}</textarea>
        </div>

        {{-- ===== ASSIGN TO (Inline Expandable) ===== --}}
        <div>
          <label class="text-xs font-medium text-gray-600 mb-1 block">Assign to <span class="text-gray-400 font-normal">(blank = anyone)</span></label>
          <button type="button" @click="assignOpen = !assignOpen"
                  class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-left bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400 flex items-center justify-between gap-2">
            <span class="text-gray-500" x-text="selectedCount === 0 ? 'Select users...' : selectedCount + ' of {{ $totalUsers }} user(s) selected'">Select users...</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="assignOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>

          {{-- Inline expandable user list --}}
          <div x-show="assignOpen" x-transition class="mt-2 border border-gray-200 rounded-xl bg-white overflow-hidden">
            {{-- Select All header --}}
            <label @click.prevent="toggleAll()"
                   class="flex items-center gap-2.5 px-3 py-2.5 bg-blue-50 border-b border-blue-100 cursor-pointer hover:bg-blue-100 transition">
              <input type="checkbox" class="accent-blue-600 w-4 h-4 pointer-events-none rounded" :checked="isAllSelected()">
              <span class="text-xs font-bold text-blue-700">Select All</span>
              <span class="text-xs text-blue-400 font-normal">({{ $totalUsers }} users)</span>
            </label>

            {{-- Roles + Users --}}
            <div class="divide-y divide-gray-100">
              @foreach($roles as $role)
                @if($role->users->count() > 0)
                  @php $roleUserIds = $role->users->pluck('id')->toArray(); @endphp
                  <div>
                    {{-- Role header (toggle all in role) --}}
                    <label @click.prevent="toggleRole({{ json_encode($roleUserIds) }})"
                           class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 cursor-pointer hover:bg-gray-100 transition">
                      <input type="checkbox" class="accent-blue-600 w-3.5 h-3.5 pointer-events-none rounded"
                             :checked="isRoleSelected({{ json_encode($roleUserIds) }})">
                      <span class="text-xs font-semibold text-gray-700">{{ $role->name }}</span>
                      <span class="text-[10px] text-gray-400 font-normal">({{ $role->users->count() }})</span>
                    </label>
                    {{-- Individual users --}}
                    @foreach($role->users as $u)
                      <label @click.prevent="toggleUser({{ $u->id }})"
                             class="flex items-center gap-2.5 px-3 py-2 pl-8 cursor-pointer hover:bg-gray-50 transition"
                             :class="selectedUsers.has({{ $u->id }}) && 'bg-blue-50/50'">
                        <input type="checkbox" name="assigned_users[]" value="{{ $u->id }}"
                               class="accent-blue-600 w-3.5 h-3.5 pointer-events-none rounded"
                               :checked="selectedUsers.has({{ $u->id }})">
                        <span class="text-xs text-gray-600">{{ $u->name }}</span>
                      </label>
                    @endforeach
                  </div>
                @endif
              @endforeach
            </div>

            {{-- Done button --}}
            <div class="px-3 py-2 bg-gray-50 border-t border-gray-100">
              <button type="button" @click="assignOpen = false"
                      class="w-full py-2 bg-blue-600 text-white text-xs font-semibold rounded-xl hover:bg-blue-700 transition">
                Done
              </button>
            </div>
          </div>
        </div>

        {{-- Reference Files (multiple images/videos) --}}
        <div x-data="{ previews: [] }" x-show="taskType !== 'announcement'">
          <label class="text-xs font-medium text-gray-600 mb-1 block">Reference Photos/Videos <span class="text-gray-400 font-normal">(optional, multiple)</span></label>
          <label class="cursor-pointer px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm text-gray-600 transition inline-flex items-center gap-1.5 active:scale-95">
            <i class="fa-solid fa-photo-film"></i> Choose Files
            <input type="file" name="reference_files[]" accept="image/*,video/*" multiple class="hidden"
                   @change="previews = [...$event.target.files].map(f => ({ url: URL.createObjectURL(f), type: f.type }))">
          </label>
          <div class="flex flex-wrap gap-2 mt-2" x-show="previews.length > 0">
            <template x-for="(p, i) in previews" :key="i">
              <div class="relative">
                <template x-if="p.type.startsWith('image')">
                  <img :src="p.url" class="w-14 h-14 object-cover rounded-xl border border-gray-200">
                </template>
                <template x-if="p.type.startsWith('video')">
                  <div class="w-14 h-14 rounded-xl border border-gray-200 bg-gray-800 flex items-center justify-center">
                    <i class="fa-solid fa-play text-white text-xs"></i>
                  </div>
                </template>
              </div>
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
    <div x-data="{
           selectedTasks: new Set(),
           get selectedCount() { return this.selectedTasks.size; },
           toggleTask(id) {
             if (this.selectedTasks.has(id)) this.selectedTasks.delete(id);
             else this.selectedTasks.add(id);
             this.selectedTasks = new Set(this.selectedTasks);
           },
           isSelected(id) { return this.selectedTasks.has(id); },
           selectAll() {
             const visible = [...document.querySelectorAll('.task-row')].filter(r => r.style.display !== 'none').map(r => parseInt(r.dataset.id));
             const allSelected = visible.every(id => this.selectedTasks.has(id));
             visible.forEach(id => { if (allSelected) this.selectedTasks.delete(id); else this.selectedTasks.add(id); });
             this.selectedTasks = new Set(this.selectedTasks);
           },
           allVisibleSelected() {
             const visible = [...document.querySelectorAll('.task-row')].filter(r => r.style.display !== 'none').map(r => parseInt(r.dataset.id));
             return visible.length > 0 && visible.every(id => this.selectedTasks.has(id));
           },
           clearSelection() { this.selectedTasks = new Set(); },
           bulkAssignOpen: false,
           bulkAssignUsers: new Set(),
           get bulkAssignCount() { return this.bulkAssignUsers.size; },
           bulkToggleUser(id) {
             if (this.bulkAssignUsers.has(id)) this.bulkAssignUsers.delete(id);
             else this.bulkAssignUsers.add(id);
             this.bulkAssignUsers = new Set(this.bulkAssignUsers);
           },
           bulkToggleRole(userIds) {
             const allChecked = userIds.every(id => this.bulkAssignUsers.has(id));
             userIds.forEach(id => { if (allChecked) this.bulkAssignUsers.delete(id); else this.bulkAssignUsers.add(id); });
             this.bulkAssignUsers = new Set(this.bulkAssignUsers);
           },
           bulkToggleAll() {
             const allIds = {{ json_encode($allUsers->pluck('id')->values()->toArray()) }};
             const allChecked = allIds.every(id => this.bulkAssignUsers.has(id));
             allIds.forEach(id => { if (allChecked) this.bulkAssignUsers.delete(id); else this.bulkAssignUsers.add(id); });
             this.bulkAssignUsers = new Set(this.bulkAssignUsers);
           },
           bulkIsAllSelected() {
             const allIds = {{ json_encode($allUsers->pluck('id')->values()->toArray()) }};
             return allIds.length > 0 && allIds.every(id => this.bulkAssignUsers.has(id));
           },
           bulkIsRoleSelected(userIds) { return userIds.length > 0 && userIds.every(id => this.bulkAssignUsers.has(id)); }
         }">

    <div id="task-sort-list" class="space-y-3">
      @forelse($allTasks as $t)
        @php $assignedIds = $t->assignedUsers->pluck('id')->toArray(); @endphp

        <div x-data="{
               editing: false,
               expanded: false,
               editFreq: '{{ $t->frequency ?? 'daily' }}',
               editType: '{{ $t->type }}',
               editRequiredPhotos: {{ $t->required_photos ?? 1 }},
               editSubMode: '{{ $t->submission_mode ?? 'group' }}',
               editDates: {{ json_encode($t->schedule_dates ?? []) }},
               editAssignOpen: false,
               editSelectedUsers: new Set({{ json_encode($assignedIds) }}),
               get editSelectedCount() { return this.editSelectedUsers.size; },
               editToggleUser(id) {
                 if (this.editSelectedUsers.has(id)) this.editSelectedUsers.delete(id);
                 else this.editSelectedUsers.add(id);
                 this.editSelectedUsers = new Set(this.editSelectedUsers);
               },
               editToggleRole(userIds) {
                 const allChecked = userIds.every(id => this.editSelectedUsers.has(id));
                 userIds.forEach(id => { if (allChecked) this.editSelectedUsers.delete(id); else this.editSelectedUsers.add(id); });
                 this.editSelectedUsers = new Set(this.editSelectedUsers);
               },
               editToggleAll() {
                 const allIds = {{ json_encode($allUsers->pluck('id')->values()->toArray()) }};
                 const allChecked = allIds.every(id => this.editSelectedUsers.has(id));
                 allIds.forEach(id => { if (allChecked) this.editSelectedUsers.delete(id); else this.editSelectedUsers.add(id); });
                 this.editSelectedUsers = new Set(this.editSelectedUsers);
               },
               editIsAllSelected() {
                 const allIds = {{ json_encode($allUsers->pluck('id')->values()->toArray()) }};
                 return allIds.length > 0 && allIds.every(id => this.editSelectedUsers.has(id));
               },
               editIsRoleSelected(userIds) { return userIds.length > 0 && userIds.every(id => this.editSelectedUsers.has(id)); }
             }"
             data-id="{{ $t->id }}"
             class="task-row bg-white border {{ $t->is_active ? 'border-gray-200' : 'border-gray-100 opacity-60' }} rounded-2xl shadow-sm overflow-hidden transition-all">

          {{-- Card Header (always visible) --}}
          <div class="px-4 py-3">
            <div class="flex items-center gap-3">
              {{-- Checkbox --}}
              <label class="flex-shrink-0 cursor-pointer" @click.stop>
                <input type="checkbox" class="accent-blue-600 w-4 h-4 rounded cursor-pointer"
                       :checked="isSelected({{ $t->id }})"
                       @change="toggleTask({{ $t->id }})">
              </label>

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
                  @php
                    $typeBadge = match($t->type) {
                      'photo' => ['bg-blue-100 text-blue-700', '📸 Photo'],
                      'note' => ['bg-yellow-100 text-yellow-700', '📝 Note'],
                      'photo_note' => ['bg-indigo-100 text-indigo-700', '📸 Photo+Note'],
                      'both' => ['bg-pink-100 text-pink-700', '📸📝 Both'],
                      'announcement' => ['bg-orange-100 text-orange-700', '📢 Announcement'],
                      default => ['bg-gray-100 text-gray-600', '📎 Any'],
                    };
                    $freqBadge = match($t->frequency ?? 'daily') {
                      'daily' => ['bg-cyan-100 text-cyan-700', '🔄 Daily'],
                      'once' => ['bg-amber-100 text-amber-700', '1️⃣ Once'],
                      'weekly' => ['bg-violet-100 text-violet-700', '📅 Weekly'],
                      'monthly' => ['bg-teal-100 text-teal-700', '🗓️ Monthly'],
                      'custom' => ['bg-rose-100 text-rose-700', '📌 Custom'],
                      'recurring_on_complete' => ['bg-purple-100 text-purple-700', '🔁 On Complete'],
                      default => ['bg-cyan-100 text-cyan-700', '🔄 Daily'],
                    };
                  @endphp
                  <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $typeBadge[0] }}">{{ $typeBadge[1] }}</span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $freqBadge[0] }}">{{ $freqBadge[1] }}</span>
                  @if(($t->submission_mode ?? 'group') === 'individual')
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-purple-100 text-purple-700">👤 Individual</span>
                  @endif
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

              {{-- Quick action buttons --}}
              <div class="flex items-center gap-1 flex-shrink-0">
                {{-- Edit --}}
                <button @click="expanded = true; $nextTick(() => editing = true)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition" title="Edit">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                {{-- Duplicate --}}
                <form method="POST" action="{{ route('checklist.duplicate-task', $t) }}" class="inline">
                  @csrf
                  <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition" title="Duplicate">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  </button>
                </form>
                {{-- Delete --}}
                <form method="POST" action="{{ route('checklist.destroy-task', $t) }}"
                      onsubmit="return confirm('Delete this task?')" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </form>
                {{-- Expand chevron --}}
                <button @click="expanded = !expanded" class="p-1.5 text-gray-400 hover:text-gray-600">
                  <svg class="w-5 h-5 transition-transform duration-200" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
              </div>
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
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Description</span>
                  <span class="text-xs text-gray-700">{{ $t->description }}</span>
                </div>
              @endif
              @if($t->instructions)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Instructions</span>
                  <span class="text-xs text-gray-700 whitespace-pre-line">{{ $t->instructions }}</span>
                </div>
              @endif
              <div class="flex items-start gap-2">
                <span class="text-xs text-gray-400 w-24 flex-shrink-0">Assigned</span>
                <span class="text-xs text-indigo-600">{{ $t->assignedUsers->count() ? $t->assignedUsers->pluck('name')->implode(', ') : 'Anyone' }}</span>
              </div>
              <div class="flex items-start gap-2">
                <span class="text-xs text-gray-400 w-24 flex-shrink-0">Mode</span>
                <span class="text-xs text-gray-700">{{ ($t->submission_mode ?? 'group') === 'group' ? '👥 Group' : '👤 Individual' }}</span>
              </div>
              @if(($t->frequency ?? 'daily') === 'weekly' && $t->schedule_days)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Schedule</span>
                  <span class="text-xs text-gray-700">
                    @php
                      $dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                      echo collect($t->schedule_days)->map(fn($d) => $dayNames[$d] ?? $d)->implode(', ');
                    @endphp
                  </span>
                </div>
              @endif
              @if(($t->frequency ?? 'daily') === 'monthly' && $t->schedule_days)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Schedule</span>
                  <span class="text-xs text-gray-700">Day {{ collect($t->schedule_days)->implode(', ') }} of month</span>
                </div>
              @endif
              @if(($t->frequency ?? 'daily') === 'custom' && $t->schedule_dates)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Dates</span>
                  <span class="text-xs text-gray-700">{{ collect($t->schedule_dates)->implode(', ') }}</span>
                </div>
              @endif
              @if(($t->frequency ?? 'daily') === 'recurring_on_complete')
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Respawn</span>
                  <span class="text-xs text-gray-700">Every {{ $t->respawn_delay_minutes ?? 5 }}min, up to {{ $t->max_daily_count ?? 10 }}/day</span>
                </div>
              @endif
              @if($t->start_date || $t->end_date)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Period</span>
                  <span class="text-xs text-gray-700">{{ $t->start_date ?? '...' }} → {{ $t->end_date ?? '...' }}</span>
                </div>
              @endif
              @if($t->ai_prompt)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">AI Prompt</span>
                  <span class="text-xs text-purple-600 italic">{{ $t->ai_prompt }}</span>
                </div>
              @endif
              @if($t->approval_prompt)
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Approval</span>
                  <span class="text-xs text-emerald-600 italic">{{ $t->approval_prompt }}</span>
                </div>
              @endif
              @php $refFiles = $t->referenceFiles; @endphp
              @if($t->reference_image || $refFiles->count())
                <div class="flex items-start gap-2">
                  <span class="text-xs text-gray-400 w-24 flex-shrink-0">Reference</span>
                  <div class="flex flex-wrap gap-2">
                    @if($t->reference_image && $refFiles->where('file_path', $t->reference_image)->isEmpty())
                      <img src="{{ Storage::url($t->reference_image) }}" class="w-16 h-16 object-cover rounded-xl border border-gray-200">
                    @endif
                    @foreach($refFiles as $rf)
                      @if($rf->isVideo())
                        <div class="w-16 h-16 rounded-xl border border-gray-200 bg-gray-800 flex items-center justify-center relative overflow-hidden">
                          <video src="{{ Storage::url($rf->file_path) }}" class="w-full h-full object-cover"></video>
                          <div class="absolute inset-0 flex items-center justify-center bg-black/30"><i class="fa-solid fa-play text-white text-xs"></i></div>
                        </div>
                      @else
                        <img src="{{ Storage::url($rf->file_path) }}" class="w-16 h-16 object-cover rounded-xl border border-gray-200">
                      @endif
                    @endforeach
                  </div>
                </div>
              @endif

              {{-- Action buttons --}}
              <div class="flex gap-2 pt-2">
                <button @click="editing = true"
                        class="flex-1 py-2 text-xs font-medium rounded-xl border border-blue-200 text-blue-600 hover:bg-blue-50 active:scale-95 transition">
                  <i class="fa-solid fa-pen mr-1"></i> Edit
                </button>
                <form method="POST" action="{{ route('checklist.duplicate-task', $t) }}" class="flex-1">
                  @csrf
                  <button type="submit"
                          class="w-full py-2 text-xs font-medium rounded-xl border border-green-200 text-green-600 hover:bg-green-50 active:scale-95 transition">
                    <i class="fa-solid fa-copy mr-1"></i> Duplicate
                  </button>
                </form>
                <form method="POST" action="{{ route('checklist.update-task', $t) }}" class="flex-1">
                  @csrf @method('PATCH')
                  <input type="hidden" name="title" value="{{ $t->title }}">
                  <input type="hidden" name="description" value="{{ $t->description }}">
                  <input type="hidden" name="instructions" value="{{ $t->instructions }}">
                  <input type="hidden" name="type" value="{{ $t->type }}">
                  <input type="hidden" name="ai_prompt" value="{{ $t->ai_prompt }}">
                  <input type="hidden" name="approval_prompt" value="{{ $t->approval_prompt }}">
                  <input type="hidden" name="task_time" value="{{ $t->task_time }}">
                  <input type="hidden" name="frequency" value="{{ $t->frequency ?? 'daily' }}">
                  <input type="hidden" name="submission_mode" value="{{ $t->submission_mode ?? 'group' }}">
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
                      onsubmit="return confirm('Delete this task?')" class="flex-shrink-0">
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
                <textarea name="description" placeholder="Description..." rows="2"
                          class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent resize-y">{{ $t->description }}</textarea>
              </div>

              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Instructions <span class="text-gray-400 font-normal">(visible to users)</span></label>
                <textarea name="instructions" placeholder="Step-by-step instructions..." rows="3"
                          class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent resize-y">{{ $t->instructions }}</textarea>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">Type</label>
                  <select name="type" x-model="editType" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <option value="photo_note">📸 Photo+Note</option>
                    <option value="any">📎 Any</option>
                    <option value="photo">📸 Photo</option>
                    <option value="note">📝 Note</option>
                    <option value="both">📸📝 Both</option>
                    <option value="announcement">📢 Announcement</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">Submission Mode</label>
                  <select name="submission_mode" x-model="editSubMode" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
                          :disabled="editType === 'announcement'" :class="editType === 'announcement' && 'bg-gray-100 text-gray-400'">
                    <option value="group">👥 Group</option>
                    <option value="individual">👤 Individual</option>
                  </select>
                </div>
              </div>

              {{-- Required Photos: Before Start (left) + Before Done (right) --}}
              <div x-show="['photo','photo_note','both'].includes(editType)" x-transition class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">📸 Photos Before Start <span class="text-gray-400">(0 = none)</span></label>
                  <input type="number" name="required_photos_before_start" min="0" max="50"
                         value="{{ $t->required_photos_before_start ?? 0 }}"
                         class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent">
                  <p class="text-[10px] text-gray-400 mt-1">Must upload before Start button appears</p>
                </div>
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">📸 Photos Before Done <span class="text-gray-400">(min. 1)</span></label>
                  <input type="number" name="required_photos" x-model="editRequiredPhotos" min="1" max="50"
                         class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                  <p class="text-[10px] text-gray-400 mt-1">Must upload before Done button appears</p>
                </div>
              </div>

              {{-- Announcement info box (edit) --}}
              <div x-show="editType === 'announcement'" x-transition class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                <p class="text-xs font-semibold text-amber-700 flex items-center gap-1.5 mb-1">📢 Announcement Mode</p>
                <p class="text-xs text-amber-600 leading-relaxed">Users will see the title + description and tap <strong>"Acknowledge"</strong> to confirm. No photos or notes needed.</p>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">Frequency</label>
                  <select name="frequency" x-model="editFreq" class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <option value="daily">🔄 Daily</option>
                    <option value="once">1️⃣ Once</option>
                    <option value="weekly">📅 Weekly</option>
                    <option value="monthly">🗓️ Monthly</option>
                    <option value="custom">📌 Custom</option>
                    <option value="recurring_on_complete">🔁 Recurring on Complete</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">Scheduled Time</label>
                  <input type="time" name="task_time" value="{{ $t->task_time ? \Carbon\Carbon::parse($t->task_time)->format('H:i') : '' }}"
                         class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                </div>
              </div>

              {{-- Weekly days --}}
              <div x-show="editFreq === 'weekly'" x-transition class="bg-gray-50 rounded-xl p-3">
                <label class="text-xs font-medium text-gray-600 mb-2 block">Days of Week</label>
                <div class="flex flex-wrap gap-2">
                  @foreach(['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '0' => 'Sun'] as $val => $day)
                    <label class="flex items-center gap-1 px-2.5 py-1.5 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 text-xs has-[:checked]:bg-blue-100 has-[:checked]:border-blue-300 has-[:checked]:text-blue-700 transition">
                      <input type="checkbox" name="schedule_days[]" value="{{ $val }}" class="accent-blue-600 w-3 h-3"
                             {{ is_array($t->schedule_days) && in_array((int)$val, $t->schedule_days) ? 'checked' : '' }}>
                      {{ $day }}
                    </label>
                  @endforeach
                </div>
              </div>

              {{-- Monthly days --}}
              <div x-show="editFreq === 'monthly'" x-transition class="bg-gray-50 rounded-xl p-3">
                <label class="text-xs font-medium text-gray-600 mb-2 block">Days of Month</label>
                <div class="flex flex-wrap gap-1.5">
                  @for($d = 1; $d <= 31; $d++)
                    <label class="w-8 h-8 flex items-center justify-center bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 text-xs has-[:checked]:bg-blue-100 has-[:checked]:border-blue-300 has-[:checked]:text-blue-700 transition">
                      <input type="checkbox" name="schedule_days[]" value="{{ $d }}" class="hidden"
                             {{ is_array($t->schedule_days) && in_array($d, $t->schedule_days) ? 'checked' : '' }}>
                      {{ $d }}
                    </label>
                  @endfor
                </div>
              </div>

              {{-- Custom dates --}}
              <div x-show="editFreq === 'custom'" x-transition class="bg-gray-50 rounded-xl p-3">
                <label class="text-xs font-medium text-gray-600 mb-2 block">Specific Dates</label>
                <div class="flex gap-2 mb-2">
                  <input type="date" x-ref="editNewDate{{ $t->id }}" class="flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                  <button type="button" @click="let v = $refs['editNewDate{{ $t->id }}'].value; if(v && !editDates.includes(v)) { editDates.push(v); $refs['editNewDate{{ $t->id }}'].value=''; }"
                          class="px-3 py-2 bg-blue-600 text-white text-sm rounded-xl hover:bg-blue-700 active:scale-95 transition">
                    <i class="fa-solid fa-plus"></i>
                  </button>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <template x-for="(dt, i) in editDates" :key="i">
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded-lg text-xs">
                      <span x-text="dt"></span>
                      <button type="button" @click="editDates.splice(i, 1)" class="text-blue-400 hover:text-blue-600">&times;</button>
                      <input type="hidden" name="schedule_dates[]" :value="dt">
                    </span>
                  </template>
                </div>
              </div>

              {{-- Recurring on Complete: delay + max count --}}
              <div x-show="editFreq === 'recurring_on_complete'" x-transition class="bg-gray-50 rounded-xl p-3 space-y-2">
                <p class="text-xs text-gray-500">⏱️ When this task is completed, a new instance will auto-spawn after the delay below.</p>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Respawn Delay <span class="text-gray-400 font-normal">(minutes)</span></label>
                    <input type="number" name="respawn_delay_minutes" min="0" max="1440" value="{{ $t->respawn_delay_minutes ?? 5 }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <p class="text-[10px] text-gray-400 mt-1">0 = spawn immediately. Default: 5</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Max per Day</label>
                    <input type="number" name="max_daily_count" min="1" max="100" value="{{ $t->max_daily_count ?? 10 }}"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <p class="text-[10px] text-gray-400 mt-1">Hard cap on daily spawns. Default: 10</p>
                  </div>
                </div>
              </div>

              {{-- Start / End date --}}
              <div class="grid grid-cols-2 gap-3" x-show="editFreq !== 'once' && editFreq !== 'custom' && editFreq !== 'recurring_on_complete'" x-transition>
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">Start Date</label>
                  <input type="date" name="start_date" value="{{ $t->start_date }}"
                         class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                </div>
                <div>
                  <label class="text-xs font-medium text-gray-600 mb-1 block">End Date</label>
                  <input type="date" name="end_date" value="{{ $t->end_date }}"
                         class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                </div>
              </div>

              <div x-show="editType !== 'announcement'">
                <label class="text-xs font-medium text-gray-600 mb-1 block">AI Prompt Focus</label>
                <textarea name="ai_prompt" rows="2" placeholder="e.g. Check if the workstation is clean..."
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 resize-none">{{ $t->ai_prompt }}</textarea>
              </div>

              <div x-show="editType !== 'announcement'">
                <label class="text-xs font-medium text-gray-600 mb-1 block">Approval Criteria</label>
                <textarea name="approval_prompt" rows="2" placeholder="e.g. The floor must be visibly clean..."
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 resize-none">{{ $t->approval_prompt }}</textarea>
              </div>

              <input type="hidden" name="is_active" value="{{ $t->is_active ? '1' : '0' }}">

              {{-- ===== ASSIGN TO (Edit - Inline Expandable) ===== --}}
              <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Assigned to</label>
                <button type="button" @click="editAssignOpen = !editAssignOpen"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-left bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400 flex items-center justify-between gap-2">
                  <span class="text-gray-500" x-text="editSelectedCount === 0 ? 'Anyone (no filter)' : editSelectedCount + ' of {{ $totalUsers }} user(s) selected'"></span>
                  <svg class="w-4 h-4 text-gray-400 transition-transform" :class="editAssignOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="editAssignOpen" x-transition class="mt-2 border border-gray-200 rounded-xl bg-white overflow-hidden">
                  {{-- Select All --}}
                  <label @click.prevent="editToggleAll()"
                         class="flex items-center gap-2.5 px-3 py-2.5 bg-blue-50 border-b border-blue-100 cursor-pointer hover:bg-blue-100 transition">
                    <input type="checkbox" class="accent-blue-600 w-4 h-4 pointer-events-none rounded" :checked="editIsAllSelected()">
                    <span class="text-xs font-bold text-blue-700">Select All</span>
                    <span class="text-xs text-blue-400 font-normal">({{ $totalUsers }} users)</span>
                  </label>

                  <div class="divide-y divide-gray-100">
                    @foreach($roles as $role)
                      @if($role->users->count() > 0)
                        @php $roleUserIds = $role->users->pluck('id')->toArray(); @endphp
                        <div>
                          <label @click.prevent="editToggleRole({{ json_encode($roleUserIds) }})"
                                 class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 cursor-pointer hover:bg-gray-100 transition">
                            <input type="checkbox" class="accent-blue-600 w-3.5 h-3.5 pointer-events-none rounded"
                                   :checked="editIsRoleSelected({{ json_encode($roleUserIds) }})">
                            <span class="text-xs font-semibold text-gray-700">{{ $role->name }}</span>
                            <span class="text-[10px] text-gray-400 font-normal">({{ $role->users->count() }})</span>
                          </label>
                          @foreach($role->users as $u)
                            <label @click.prevent="editToggleUser({{ $u->id }})"
                                   class="flex items-center gap-2.5 px-3 py-2 pl-8 cursor-pointer hover:bg-gray-50 transition"
                                   :class="editSelectedUsers.has({{ $u->id }}) && 'bg-blue-50/50'">
                              <input type="checkbox" name="assigned_users[]" value="{{ $u->id }}"
                                     class="accent-blue-600 w-3.5 h-3.5 pointer-events-none rounded"
                                     :checked="editSelectedUsers.has({{ $u->id }})">
                              <span class="text-xs text-gray-600">{{ $u->name }}</span>
                            </label>
                          @endforeach
                        </div>
                      @endif
                    @endforeach
                  </div>

                  <div class="px-3 py-2 bg-gray-50 border-t border-gray-100">
                    <button type="button" @click="editAssignOpen = false"
                            class="w-full py-2 bg-blue-600 text-white text-xs font-semibold rounded-xl hover:bg-blue-700 transition">
                      Done
                    </button>
                  </div>
                </div>
              </div>

              {{-- Reference Files --}}
              <div x-data="{ newPreviews: [] }" x-show="editType !== 'announcement'">
                <label class="text-xs font-medium text-gray-600 mb-1 block">Reference Photos/Videos</label>
                @if($t->referenceFiles->count())
                  <div class="flex flex-wrap gap-2 mb-2">
                    @foreach($t->referenceFiles as $rf)
                      <div x-data="{ deleted: false }" x-show="!deleted" class="relative group">
                        @if($rf->isVideo())
                          <div class="w-14 h-14 rounded-xl border border-gray-200 bg-gray-800 flex items-center justify-center relative overflow-hidden">
                            <video src="{{ Storage::url($rf->file_path) }}" class="w-full h-full object-cover"></video>
                            <div class="absolute inset-0 flex items-center justify-center bg-black/30"><i class="fa-solid fa-play text-white text-xs"></i></div>
                          </div>
                        @else
                          <img src="{{ Storage::url($rf->file_path) }}" class="w-14 h-14 object-cover rounded-xl border border-gray-200">
                        @endif
                        <button type="button" @click="deleted = true; $el.closest('.relative').querySelector('.del-input').disabled = false"
                                class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow">
                          <i class="fa-solid fa-xmark"></i>
                        </button>
                        <input type="hidden" name="delete_reference_files[]" value="{{ $rf->id }}" disabled class="del-input">
                      </div>
                    @endforeach
                  </div>
                @endif
                <label class="cursor-pointer px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm text-gray-600 transition inline-flex items-center gap-1.5 active:scale-95">
                  <i class="fa-solid fa-photo-film"></i> Add Files
                  <input type="file" name="reference_files[]" accept="image/*,video/*" multiple class="hidden"
                         @change="newPreviews = [...$event.target.files].map(f => ({ url: URL.createObjectURL(f), type: f.type }))">
                </label>
                <div class="flex flex-wrap gap-2 mt-2" x-show="newPreviews.length > 0">
                  <template x-for="(p, i) in newPreviews" :key="i">
                    <div class="relative">
                      <template x-if="p.type.startsWith('image')">
                        <img :src="p.url" class="w-14 h-14 object-cover rounded-xl border border-blue-300">
                      </template>
                      <template x-if="p.type.startsWith('video')">
                        <div class="w-14 h-14 rounded-xl border border-blue-300 bg-gray-800 flex items-center justify-center">
                          <i class="fa-solid fa-play text-white text-xs"></i>
                        </div>
                      </template>
                    </div>
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

    {{-- ====== FLOATING BULK ACTIONS BAR ====== --}}
    <div x-show="selectedCount > 0" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4"
         x-cloak
         class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 bg-gray-900 text-white rounded-2xl shadow-2xl px-4 py-3 flex items-center gap-3 max-w-lg w-[calc(100%-2rem)]">

      {{-- Select All toggle --}}
      <label class="flex-shrink-0 cursor-pointer flex items-center gap-2" @click.prevent="selectAll()">
        <input type="checkbox" class="accent-blue-400 w-4 h-4 rounded pointer-events-none" :checked="allVisibleSelected()">
        <span class="text-xs font-medium">All</span>
      </label>

      {{-- Count --}}
      <span class="text-xs bg-blue-600 px-2 py-0.5 rounded-full font-semibold" x-text="selectedCount + ' selected'"></span>

      <div class="flex-1"></div>

      {{-- Assign To --}}
      <div class="relative">
        <button @click="bulkAssignOpen = !bulkAssignOpen; if(bulkAssignOpen) bulkAssignUsers = new Set()"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-xl transition">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          Assign To
        </button>

        {{-- Assign dropdown --}}
        <div x-show="bulkAssignOpen" @click.outside="bulkAssignOpen = false" x-transition
             class="absolute bottom-full mb-2 right-0 w-72 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden text-gray-800 max-h-80 flex flex-col">

          {{-- Select All header --}}
          <label @click.prevent="bulkToggleAll()"
                 class="flex items-center gap-2.5 px-3 py-2.5 bg-blue-50 border-b border-blue-100 cursor-pointer hover:bg-blue-100 transition flex-shrink-0">
            <input type="checkbox" class="accent-blue-600 w-4 h-4 pointer-events-none rounded" :checked="bulkIsAllSelected()">
            <span class="text-xs font-bold text-blue-700">Select All</span>
            <span class="text-xs text-blue-400 font-normal">({{ $totalUsers }} users)</span>
          </label>

          {{-- Scrollable roles + users --}}
          <div class="divide-y divide-gray-100 overflow-y-auto flex-1">
            @foreach($roles as $role)
              @if($role->users->count() > 0)
                @php $roleUserIds = $role->users->pluck('id')->toArray(); @endphp
                <div>
                  <label @click.prevent="bulkToggleRole({{ json_encode($roleUserIds) }})"
                         class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" class="accent-blue-600 w-3.5 h-3.5 pointer-events-none rounded"
                           :checked="bulkIsRoleSelected({{ json_encode($roleUserIds) }})">
                    <span class="text-xs font-semibold text-gray-700">{{ $role->name }}</span>
                    <span class="text-[10px] text-gray-400 font-normal">({{ $role->users->count() }})</span>
                  </label>
                  @foreach($role->users as $u)
                    <label @click.prevent="bulkToggleUser({{ $u->id }})"
                           class="flex items-center gap-2.5 px-3 py-2 pl-8 cursor-pointer hover:bg-gray-50 transition"
                           :class="bulkAssignUsers.has({{ $u->id }}) && 'bg-blue-50/50'">
                      <input type="checkbox" class="accent-blue-600 w-3.5 h-3.5 pointer-events-none rounded"
                             :checked="bulkAssignUsers.has({{ $u->id }})">
                      <span class="text-xs text-gray-600">{{ $u->name }}</span>
                    </label>
                  @endforeach
                </div>
              @endif
            @endforeach
          </div>

          {{-- Apply button --}}
          <form method="POST" action="{{ route('checklist.bulk-assign') }}" class="flex-shrink-0">
            @csrf
            <template x-for="tid in [...selectedTasks]" :key="tid">
              <input type="hidden" name="task_ids[]" :value="tid">
            </template>
            <template x-for="uid in [...bulkAssignUsers]" :key="uid">
              <input type="hidden" name="assigned_users[]" :value="uid">
            </template>
            <div class="px-3 py-2 bg-gray-50 border-t border-gray-100">
              <button type="submit"
                      class="w-full py-2 bg-blue-600 text-white text-xs font-semibold rounded-xl hover:bg-blue-700 transition">
                <span x-text="'Apply to ' + selectedCount + ' task(s)'"></span>
              </button>
            </div>
          </form>
        </div>
      </div>

      {{-- Delete Selected --}}
      <form method="POST" action="{{ route('checklist.bulk-delete') }}"
            onsubmit="return confirm('Delete all selected tasks?')">
        @csrf
        <template x-for="tid in [...selectedTasks]" :key="tid">
          <input type="hidden" name="task_ids[]" :value="tid">
        </template>
        <button type="submit"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-xl transition">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          Delete
        </button>
      </form>

      {{-- Clear selection --}}
      <button @click="clearSelection()" class="p-1.5 text-gray-400 hover:text-white transition" title="Clear selection">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    </div> {{-- end bulk selection wrapper --}}

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
