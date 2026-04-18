<x-layout>
  <x-slot name="heading">Users</x-slot>
  <x-slot name="title">Users</x-slot>

  <div class="p-4 max-w-5xl mx-auto space-y-4 mt-16" x-data="{ filterRole: '', showAdd: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <h1 class="text-xl font-bold text-gray-800">Users</h1>
        <p class="text-sm text-gray-500">{{ $users->count() }} total users</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="showAdd = !showAdd"
                class="text-sm px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition"
                x-text="showAdd ? '✕ Close' : '+ Add User'">
        </button>
        <a href="{{ route('admin.roles') }}"
           class="text-sm px-3 py-1.5 rounded-lg border border-purple-300 bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium">
          <i class="fa-solid fa-shield-halved mr-1"></i> Roles
        </a>
      </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
      <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif

    {{-- Add User (Collapsible) --}}
    <div x-show="showAdd" x-collapse x-cloak
         class="bg-blue-50 border border-blue-200 rounded-xl p-4">
      <h2 class="font-semibold text-blue-800 text-sm mb-3"><i class="fa-solid fa-user-plus mr-1"></i> Add New User</h2>
      <form method="POST" action="{{ route('admin.store-user') }}">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs text-blue-700 font-medium mb-1">Full Name *</label>
            <input type="text" name="name" required placeholder="Juan Dela Cruz"
                   class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400">
          </div>
          <div>
            <label class="block text-xs text-blue-700 font-medium mb-1">Username <span class="text-blue-400">(for login)</span></label>
            <input type="text" name="username" placeholder="juan.delacruz"
                   class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400">
          </div>
          <div>
            <label class="block text-xs text-blue-700 font-medium mb-1">Email *</label>
            <input type="email" name="email" required placeholder="juan@example.com"
                   class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400">
          </div>
          <div>
            <label class="block text-xs text-blue-700 font-medium mb-1">Password *</label>
            <input type="text" name="password" required placeholder="Min 6 characters"
                   class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400">
          </div>
          <div>
            <label class="block text-xs text-blue-700 font-medium mb-1">Role *</label>
            <select name="role_id" required
                    class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400">
              <option value="">Select role...</option>
              @foreach($roles as $r)
                @if($r->level <= (auth()->user()->role->level ?? 0))
                  <option value="{{ $r->id }}">{{ $r->name }}{{ $r->is_admin ? ' (Admin)' : '' }}</option>
                @endif
              @endforeach
            </select>
          </div>
          <div class="flex items-end">
            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium transition">
              <i class="fa-solid fa-user-plus mr-1"></i> Add User
            </button>
          </div>
        </div>
      </form>
    </div>

    {{-- Filter --}}
    <div class="flex items-center gap-3 flex-wrap">
      <select x-model="filterRole"
              class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 shadow-sm">
        <option value="">All Roles</option>
        @foreach($roles as $r)
          <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->users_count }})</option>
        @endforeach
      </select>
      <button x-show="filterRole" @click="filterRole = ''" x-cloak
              class="text-xs text-red-500 hover:text-red-700 font-medium px-2 py-1 rounded border border-red-200 hover:bg-red-50 transition">
        <i class="fa-solid fa-xmark mr-0.5"></i> Clear Filter
      </button>
    </div>

    {{-- User Cards --}}
    <div class="space-y-2">
      @foreach($users as $user)
        <div x-data="{ editing: false, showPw: false }"
             data-user-card data-role-id="{{ $user->role_id }}"
             x-show="!filterRole || filterRole == '{{ $user->role_id }}'"
             class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden transition-all"
             :class="editing ? 'ring-2 ring-blue-300' : 'hover:shadow-md'">

          {{-- View Mode --}}
          @php
            $authLevel = auth()->user()->role->level ?? 0;
            $targetLevel = $user->role->level ?? 0;
            $canManage = $targetLevel <= $authLevel;
          @endphp
          <div x-show="!editing" class="p-4">
            <div class="flex items-start gap-3">
              {{-- Avatar --}}
              <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0
                {{ $user->role && $user->role->level >= 100 ? 'bg-amber-500' : ($user->role && $user->role->is_admin ? 'bg-purple-500' : 'bg-blue-500') }}">
                {{ strtoupper(substr($user->name, 0, 1)) }}
              </div>

              {{-- Info --}}
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <h3 class="font-semibold text-gray-800 text-sm">{{ $user->name }}</h3>
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium
                    {{ $user->role && $user->role->level >= 100 ? 'bg-amber-100 text-amber-700' : ($user->role && $user->role->is_admin ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600') }}">
                    {{ $user->role?->name ?? 'No Role' }}
                  </span>
                  @if($user->is_active)
                    <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0" title="Active"></span>
                  @else
                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-500 font-medium">Inactive</span>
                  @endif
                </div>

                <div class="flex items-center gap-4 mt-1 flex-wrap text-xs text-gray-500">
                  @if($user->username)
                    <span class="font-mono bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $user->username }}</span>
                  @endif
                  <span>{{ $user->email }}</span>
                  @if($canManage)
                    <span class="flex items-center gap-1">
                      <span class="font-mono" x-text="showPw ? '{{ $user->plain_password ?? '(hidden)' }}' : '••••••'"></span>
                      @if($user->plain_password)
                        <button type="button" @click="showPw = !showPw" class="text-gray-400 hover:text-gray-600" x-text="showPw ? '🙈' : '👁'">👁</button>
                      @endif
                    </span>
                  @else
                    <span class="text-xs text-gray-400"><i class="fa-solid fa-lock"></i> ••••••</span>
                  @endif
                </div>
              </div>

              {{-- Actions --}}
              <div class="flex items-center gap-1 flex-shrink-0">
                @if($canManage)
                  <button @click="editing = true"
                          class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition font-medium">
                    <i class="fa-solid fa-pen-to-square mr-0.5"></i> Edit
                  </button>
                  <form method="POST" action="{{ route('admin.toggle-user', $user) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="text-xs px-3 py-1.5 rounded-lg border transition font-medium
                              {{ $user->is_active ? 'border-amber-200 text-amber-600 hover:bg-amber-50' : 'border-green-200 text-green-600 hover:bg-green-50' }}">
                      {{ $user->is_active ? 'Disable' : 'Enable' }}
                    </button>
                  </form>
                  @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.destroy-user', $user) }}" onsubmit="return confirm('Delete user \'{{ $user->name }}\'? This cannot be undone.')">
                      @csrf @method('DELETE')
                      <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-400 hover:bg-red-50 hover:text-red-600 transition">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  @endif
                @else
                  <span class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 text-gray-400 font-medium">
                    <i class="fa-solid fa-lock mr-0.5"></i> Protected
                  </span>
                @endif
              </div>
            </div>
          </div>

          {{-- Edit Mode --}}
          <div x-show="editing" x-cloak class="p-4 bg-gray-50 border-t border-gray-100">
            <form method="POST" action="{{ route('admin.update-user', $user) }}">
              @csrf @method('PATCH')
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 font-medium mb-1">Name</label>
                  <input type="text" name="name" value="{{ $user->name }}" required
                         class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                  <label class="block text-xs text-gray-500 font-medium mb-1">Username</label>
                  <input type="text" name="username" value="{{ $user->username }}" placeholder="username"
                         class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                  <label class="block text-xs text-gray-500 font-medium mb-1">Email</label>
                  <input type="email" name="email" value="{{ $user->email }}" required
                         class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                  <label class="block text-xs text-gray-500 font-medium mb-1">New Password <span class="text-gray-400">(blank = keep)</span></label>
                  <input type="text" name="password" placeholder="Leave blank to keep current"
                         class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                  <label class="block text-xs text-gray-500 font-medium mb-1">Role</label>
                  <select name="role_id" required
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300">
                    @foreach($roles as $r)
                      @if($r->level <= (auth()->user()->role->level ?? 0))
                        <option value="{{ $r->id }}" {{ $user->role_id == $r->id ? 'selected' : '' }}>{{ $r->name }}{{ $r->is_admin ? ' (Admin)' : '' }}</option>
                      @endif
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium transition">
                  <i class="fa-solid fa-check mr-1"></i> Save Changes
                </button>
                <button type="button" @click="editing = false" class="px-4 py-2 text-sm text-gray-500 rounded-lg border border-gray-200 hover:bg-white transition">
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Empty state --}}
    <div x-show="document.querySelectorAll('[data-user-card]:not([style*=none])').length === 0" x-cloak
         class="text-center py-8 text-gray-400 text-sm">
      No users found for this filter.
    </div>

  </div>
</x-layout>
