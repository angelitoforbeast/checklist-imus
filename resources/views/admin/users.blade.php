<x-layout>
  <x-slot name="heading">Users</x-slot>
  <x-slot name="title">Users</x-slot>

  <div class="p-4 max-w-4xl mx-auto space-y-4 mt-16" x-data="{ filterRole: '' }">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <h1 class="text-xl font-bold text-gray-800">Users</h1>
        <p class="text-sm text-gray-500">Manage user accounts and assignments.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.roles') }}"
           class="text-sm px-3 py-1.5 rounded-lg border border-purple-300 bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium">
          <i class="fa-solid fa-shield-halved mr-1"></i> Roles
        </a>
        <a href="{{ route('checklist.conversations') }}"
           class="text-sm px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-700">
          ← Checklist
        </a>
      </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
      <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
        @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
      </div>
    @endif

    {{-- Add User --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
      <h2 class="font-semibold text-gray-800 text-sm mb-3">Add New User</h2>
      <form method="POST" action="{{ route('admin.store-user') }}">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Full Name</label>
            <input type="text" name="name" required placeholder="Juan Dela Cruz"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Username <span class="text-gray-400">(for login)</span></label>
            <input type="text" name="username" placeholder="juan.delacruz"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Email</label>
            <input type="email" name="email" required placeholder="juan@example.com"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Password</label>
            <input type="text" name="password" required placeholder="Min 6 characters"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Role</label>
            <select name="role_id" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
              <option value="">Select role...</option>
              @foreach($roles as $r)
                <option value="{{ $r->id }}">{{ $r->name }}{{ $r->is_admin ? ' (Admin)' : '' }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <button type="submit" class="mt-3 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
          <i class="fa-solid fa-user-plus mr-1"></i> Add User
        </button>
      </form>
    </div>

    {{-- Filter --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm px-4 py-3 flex items-center gap-3 flex-wrap">
      <span class="text-sm text-gray-500 font-medium"><i class="fa-solid fa-filter mr-1"></i> Filter:</span>
      <select x-model="filterRole"
              class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
        <option value="">All Roles</option>
        @foreach($roles as $r)
          <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->users_count }})</option>
        @endforeach
      </select>
      <button x-show="filterRole" @click="filterRole = ''" x-cloak
              class="text-xs text-red-500 hover:text-red-700 font-medium">
        <i class="fa-solid fa-xmark mr-0.5"></i> Clear
      </button>
      <span class="ml-auto text-xs text-gray-400">
        <span x-text="document.querySelectorAll('[data-user-card]:not([style*=none])').length || {{ $users->count() }}"></span> users
      </span>
    </div>

    {{-- User List (Desktop Table) --}}
    <div class="hidden md:block bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-left text-xs text-gray-500 uppercase tracking-wide">
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Username</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Password</th>
            <th class="px-4 py-3">Role</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @foreach($users as $user)
            <tr x-data="{ editing: false, showPw: false }"
                data-user-card data-role-id="{{ $user->role_id }}"
                x-show="!filterRole || filterRole == '{{ $user->role_id }}'"
                class="hover:bg-gray-50/50 transition">

              {{-- View Mode --}}
              <template x-if="!editing">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs flex-shrink-0
                      {{ $user->role && $user->role->level >= 100 ? 'bg-amber-500' : ($user->role && $user->role->is_admin ? 'bg-purple-500' : 'bg-blue-500') }}">
                      {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <span class="font-medium text-gray-800">{{ $user->name }}</span>
                  </div>
                </td>
              </template>
              <template x-if="!editing">
                <td class="px-4 py-3">
                  @if($user->username)
                    <span class="text-gray-700 font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $user->username }}</span>
                  @else
                    <span class="text-gray-300 text-xs">—</span>
                  @endif
                </td>
              </template>
              <template x-if="!editing">
                <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
              </template>
              <template x-if="!editing">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1">
                    <span class="text-gray-400 font-mono text-xs" x-text="showPw ? '{{ $user->plain_password ?? '(hidden)' }}' : '••••••'"></span>
                    @if($user->plain_password)
                      <button type="button" @click="showPw = !showPw" class="text-gray-400 hover:text-gray-600 text-xs" x-text="showPw ? '🙈' : '👁'">👁</button>
                    @endif
                  </div>
                </td>
              </template>
              <template x-if="!editing">
                <td class="px-4 py-3">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium
                    {{ $user->role && $user->role->level >= 100 ? 'bg-amber-100 text-amber-700' : ($user->role && $user->role->is_admin ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600') }}">
                    {{ $user->role?->name ?? 'No Role' }}
                  </span>
                </td>
              </template>
              <template x-if="!editing">
                <td class="px-4 py-3">
                  @if($user->is_active)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600 font-medium">Active</span>
                  @else
                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-500 font-medium">Inactive</span>
                  @endif
                </td>
              </template>
              <template x-if="!editing">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1 justify-end">
                    <button @click="editing = true" class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">Edit</button>
                    <form method="POST" action="{{ route('admin.toggle-user', $user) }}">
                      @csrf @method('PATCH')
                      <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg border {{ $user->is_active ? 'border-amber-200 text-amber-500 hover:bg-amber-50' : 'border-green-200 text-green-500 hover:bg-green-50' }} transition">
                        {{ $user->is_active ? 'Disable' : 'Enable' }}
                      </button>
                    </form>
                    @if($user->id !== auth()->id())
                      <form method="POST" action="{{ route('admin.destroy-user', $user) }}" onsubmit="return confirm('Delete user \'{{ $user->name }}\'?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-400 hover:bg-red-50 transition">Delete</button>
                      </form>
                    @endif
                  </div>
                </td>
              </template>

              {{-- Edit Mode --}}
              <template x-if="editing">
                <td colspan="7" class="px-4 py-4">
                  <form method="POST" action="{{ route('admin.update-user', $user) }}" id="edit-user-{{ $user->id }}">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Name</label>
                        <input type="text" name="name" value="{{ $user->name }}" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                      </div>
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Username</label>
                        <input type="text" name="username" value="{{ $user->username }}" placeholder="username"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                      </div>
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Email</label>
                        <input type="email" name="email" value="{{ $user->email }}" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                      </div>
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Password <span class="text-gray-400">(blank = keep)</span></label>
                        <input type="text" name="password" placeholder="New password"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                      </div>
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Role</label>
                        <select name="role_id" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                          @foreach($roles as $r)
                            <option value="{{ $r->id }}" {{ $user->role_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                      <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
                        <i class="fa-solid fa-check mr-1"></i> Save
                      </button>
                      <button type="button" @click="editing = false" class="px-4 py-2 text-sm text-gray-500 rounded-lg border border-gray-200 hover:bg-gray-50">
                        Cancel
                      </button>
                    </div>
                  </form>
                </td>
              </template>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- User List (Mobile Cards) --}}
    <div class="md:hidden space-y-2">
      @foreach($users as $user)
        <div class="border border-gray-200 rounded-xl bg-white p-3 space-y-2"
             x-data="{ editing: false, showPw: false }"
             data-user-card data-role-id="{{ $user->role_id }}"
             x-show="!filterRole || filterRole == '{{ $user->role_id }}'">

          <template x-if="!editing">
            <div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs flex-shrink-0
                    {{ $user->role && $user->role->level >= 100 ? 'bg-amber-500' : ($user->role && $user->role->is_admin ? 'bg-purple-500' : 'bg-blue-500') }}">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-gray-800">{{ $user->name }}</p>
                    @if($user->username)
                      <p class="text-xs text-gray-500 font-mono">{{ $user->username }}</p>
                    @endif
                    <p class="text-xs text-gray-400">{{ $user->email }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-1.5">
                  @if($user->is_active)
                    <span class="w-2 h-2 rounded-full bg-green-400"></span>
                  @else
                    <span class="w-2 h-2 rounded-full bg-red-400"></span>
                  @endif
                </div>
              </div>

              <div class="flex items-center gap-2 mt-2">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                  {{ $user->role && $user->role->level >= 100 ? 'bg-amber-100 text-amber-700' : ($user->role && $user->role->is_admin ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600') }}">
                  {{ $user->role?->name ?? 'No Role' }}
                </span>
                <div class="flex items-center gap-1 text-xs">
                  <span class="text-gray-400 font-mono" x-text="showPw ? '{{ $user->plain_password ?? '(hidden)' }}' : '•••••••'"></span>
                  @if($user->plain_password)
                    <button type="button" @click="showPw = !showPw" class="text-gray-400 hover:text-gray-600" x-text="showPw ? '🙈' : '👁'">👁</button>
                  @endif
                </div>
              </div>

              <div class="flex items-center gap-1 mt-2 pt-2 border-t border-gray-100">
                <button @click="editing = true" class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition flex-1">Edit</button>
                <form method="POST" action="{{ route('admin.toggle-user', $user) }}" class="flex-1">
                  @csrf @method('PATCH')
                  <button type="submit" class="w-full text-xs px-2.5 py-1.5 rounded-lg border {{ $user->is_active ? 'border-amber-200 text-amber-500 hover:bg-amber-50' : 'border-green-200 text-green-500 hover:bg-green-50' }} transition">
                    {{ $user->is_active ? 'Disable' : 'Enable' }}
                  </button>
                </form>
                @if($user->id !== auth()->id())
                  <form method="POST" action="{{ route('admin.destroy-user', $user) }}" onsubmit="return confirm('Delete user?')" class="flex-1">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-400 hover:bg-red-50 transition">Delete</button>
                  </form>
                @endif
              </div>
            </div>
          </template>

          <template x-if="editing">
            <form method="POST" action="{{ route('admin.update-user', $user) }}" class="space-y-2">
              @csrf @method('PATCH')
              <input type="text" name="name" value="{{ $user->name }}" required placeholder="Name"
                     class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
              <input type="text" name="username" value="{{ $user->username }}" placeholder="Username (for login)"
                     class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
              <input type="email" name="email" value="{{ $user->email }}" required placeholder="Email"
                     class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
              <input type="text" name="password" placeholder="New password (leave blank to keep)"
                     class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
              <select name="role_id" required
                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                @foreach($roles as $r)
                  <option value="{{ $r->id }}" {{ $user->role_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
              </select>
              <div class="flex gap-2">
                <button type="submit" class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">Save</button>
                <button type="button" @click="editing = false" class="flex-1 px-3 py-2 text-sm text-gray-500 rounded-lg border border-gray-200 hover:bg-gray-50">Cancel</button>
              </div>
            </form>
          </template>
        </div>
      @endforeach
    </div>

  </div>
</x-layout>
