<x-layout>
  <x-slot name="heading">Manage Roles & Users</x-slot>
  <x-slot name="title">Manage Roles & Users</x-slot>

  <div class="min-h-screen bg-gray-50 mt-16">

    {{-- HEADER --}}
    <div class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-screen-2xl mx-auto px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h1 class="text-base font-bold text-gray-800">Manage Roles & Users</h1>
          <p class="text-xs text-gray-400">Add, edit, or remove roles and users.</p>
        </div>
        <div class="flex items-center gap-3">
          <a href="{{ route('checklist.manage') }}" class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition">⚙ Manage Tasks</a>
          <a href="{{ route('checklist.index') }}" class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition">← Checklist</a>
        </div>
      </div>
    </div>

    <div class="max-w-screen-2xl mx-auto px-4 py-5 space-y-6">

      {{-- Alerts --}}
      @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm flex gap-2 items-center">
          <span class="text-green-500 font-bold">✓</span> {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">{{ session('error') }}</div>
      @endif
      @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm space-y-0.5">
          @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
      @endif

      {{-- ===== ROLES SECTION ===== --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-bold text-gray-700">All Roles ({{ $roles->count() }})</h2>
        </div>

        {{-- Add Role Form --}}
        <div class="px-5 py-4 bg-gray-50/50 border-b border-gray-100" x-data="{ open: false }">
          <button @click="open = !open" class="text-xs px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition font-medium">
            + Add Role
          </button>
          <form x-show="open" x-transition method="POST" action="{{ route('admin.store-role') }}" class="mt-3 flex items-end gap-3 flex-wrap">
            @csrf
            <div>
              <label class="text-xs text-gray-400 mb-1 block font-medium">Role Name</label>
              <input type="text" name="name" required placeholder="e.g. Driver, Cook..."
                     class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 w-48">
            </div>
            <div class="flex items-center gap-2">
              <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                <input type="checkbox" name="is_admin" value="1" class="rounded border-gray-300">
                <span>Admin privileges</span>
              </label>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-xl font-semibold transition">
              Create Role
            </button>
          </form>
        </div>

        {{-- Roles Table --}}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/80 text-xs text-gray-400 uppercase tracking-wide font-semibold">
                <th class="text-left px-5 py-3">Role Name</th>
                <th class="text-left px-3 py-3">Slug</th>
                <th class="text-center px-3 py-3">Admin?</th>
                <th class="text-center px-3 py-3">Users</th>
                <th class="px-3 py-3 w-[200px]"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($roles as $role)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40" x-data="{ editing: false }">
                  {{-- View mode --}}
                  <template x-if="!editing">
                    <td class="px-5 py-3 font-semibold text-gray-800">
                      {{ $role->name }}
                      @if($role->is_admin)
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-500 ml-1">Admin</span>
                      @endif
                    </td>
                  </template>
                  <template x-if="editing">
                    <td class="px-5 py-3">
                      <form method="POST" action="{{ route('admin.update-role', $role) }}" class="flex items-center gap-2" id="edit-role-{{ $role->id }}">
                        @csrf @method('PATCH')
                        <input type="text" name="name" value="{{ $role->name }}" required
                               class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer">
                          <input type="checkbox" name="is_admin" value="1" {{ $role->is_admin ? 'checked' : '' }} class="rounded border-gray-300">
                          <span>Admin</span>
                        </label>
                      </form>
                    </td>
                  </template>

                  <td class="px-3 py-3 text-gray-400 text-xs" x-show="!editing">{{ $role->slug }}</td>
                  <td class="px-3 py-3 text-gray-400 text-xs" x-show="editing"></td>

                  <td class="px-3 py-3 text-center" x-show="!editing">
                    @if($role->is_admin)
                      <span class="text-purple-500">✓</span>
                    @else
                      <span class="text-gray-300">—</span>
                    @endif
                  </td>
                  <td class="px-3 py-3" x-show="editing"></td>

                  <td class="px-3 py-3 text-center text-gray-500" x-show="!editing">{{ $role->users_count }}</td>
                  <td class="px-3 py-3" x-show="editing"></td>

                  <td class="px-3 py-3">
                    <div class="flex items-center gap-1 justify-end">
                      <template x-if="!editing">
                        <div class="flex items-center gap-1">
                          <button @click="editing = true" class="text-xs px-2 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">Edit</button>
                          <form method="POST" action="{{ route('admin.destroy-role', $role) }}" onsubmit="return confirm('Delete this role?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs px-2 py-1.5 rounded-lg border border-red-200 text-red-400 hover:bg-red-50 transition">Delete</button>
                          </form>
                        </div>
                      </template>
                      <template x-if="editing">
                        <div class="flex items-center gap-1">
                          <button type="submit" form="edit-role-{{ $role->id }}" class="text-xs px-2 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Save</button>
                          <button @click="editing = false" class="text-xs px-2 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">Cancel</button>
                        </div>
                      </template>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      {{-- ===== USERS SECTION ===== --}}
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-bold text-gray-700">All Users ({{ $users->count() }})</h2>
        </div>

        {{-- Add User Form --}}
        <div class="px-5 py-4 bg-gray-50/50 border-b border-gray-100" x-data="{ open: false }">
          <button @click="open = !open" class="text-xs px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700 transition font-medium">
            + Add User
          </button>
          <form x-show="open" x-transition method="POST" action="{{ route('admin.store-user') }}" class="mt-3 flex items-end gap-3 flex-wrap">
            @csrf
            <div>
              <label class="text-xs text-gray-400 mb-1 block font-medium">Name</label>
              <input type="text" name="name" required placeholder="Full name..."
                     class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 w-40">
            </div>
            <div>
              <label class="text-xs text-gray-400 mb-1 block font-medium">Email</label>
              <input type="email" name="email" required placeholder="email@example.com"
                     class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 w-48">
            </div>
            <div>
              <label class="text-xs text-gray-400 mb-1 block font-medium">Password</label>
              <input type="password" name="password" required placeholder="Min 6 chars..."
                     class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 w-36">
            </div>
            <div>
              <label class="text-xs text-gray-400 mb-1 block font-medium">Role</label>
              <select name="role_id" required class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                <option value="">Select role...</option>
                @foreach($roles as $r)
                  <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-xl font-semibold transition">
              Create User
            </button>
          </form>
        </div>

        {{-- Users Table --}}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/80 text-xs text-gray-400 uppercase tracking-wide font-semibold">
                <th class="text-left px-5 py-3">Name</th>
                <th class="text-left px-3 py-3">Email</th>
                <th class="text-left px-3 py-3">Role</th>
                <th class="text-center px-3 py-3">Status</th>
                <th class="px-3 py-3 w-[260px]"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($users as $user)
                <tr class="border-b border-gray-50 hover:bg-gray-50/40" x-data="{ editing: false }">
                  {{-- View mode --}}
                  <template x-if="!editing">
                    <td class="px-5 py-3">
                      <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full {{ $user->role?->is_admin ? 'bg-purple-100 text-purple-600' : 'bg-indigo-100 text-indigo-600' }} flex items-center justify-center text-xs font-bold flex-shrink-0">
                          {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <span class="font-semibold text-gray-800">{{ $user->name }}</span>
                        @if($user->id === auth()->id())
                          <span class="text-xs text-gray-300">(you)</span>
                        @endif
                      </div>
                    </td>
                  </template>
                  <template x-if="editing">
                    <td class="px-5 py-3">
                      <form method="POST" action="{{ route('admin.update-user', $user) }}" id="edit-user-{{ $user->id }}">
                        @csrf @method('PATCH')
                        <input type="text" name="name" value="{{ $user->name }}" required
                               class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-blue-300">
                      </form>
                    </td>
                  </template>

                  <template x-if="!editing">
                    <td class="px-3 py-3 text-gray-500">{{ $user->email }}</td>
                  </template>
                  <template x-if="editing">
                    <td class="px-3 py-3">
                      <input type="email" name="email" value="{{ $user->email }}" required form="edit-user-{{ $user->id }}"
                             class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-44 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </td>
                  </template>

                  <template x-if="!editing">
                    <td class="px-3 py-3">
                      <span class="text-xs px-2 py-0.5 rounded-full {{ $user->role?->is_admin ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600' }}">
                        {{ $user->role?->name ?? 'No Role' }}
                      </span>
                    </td>
                  </template>
                  <template x-if="editing">
                    <td class="px-3 py-3">
                      <select name="role_id" required form="edit-user-{{ $user->id }}"
                              class="border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                        @foreach($roles as $r)
                          <option value="{{ $r->id }}" {{ $user->role_id == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                        @endforeach
                      </select>
                      <input type="password" name="password" placeholder="New password (optional)" form="edit-user-{{ $user->id }}"
                             class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-blue-300 mt-1">
                    </td>
                  </template>

                  <td class="px-3 py-3 text-center">
                    @if($user->is_active)
                      <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600">Active</span>
                    @else
                      <span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-500">Inactive</span>
                    @endif
                  </td>

                  <td class="px-3 py-3">
                    <div class="flex items-center gap-1 justify-end">
                      <template x-if="!editing">
                        <div class="flex items-center gap-1">
                          <button @click="editing = true" class="text-xs px-2 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">Edit</button>
                          <form method="POST" action="{{ route('admin.toggle-user', $user) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs px-2 py-1.5 rounded-lg border {{ $user->is_active ? 'border-amber-200 text-amber-500 hover:bg-amber-50' : 'border-green-200 text-green-500 hover:bg-green-50' }} transition">
                              {{ $user->is_active ? 'Disable' : 'Enable' }}
                            </button>
                          </form>
                          @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.destroy-user', $user) }}" onsubmit="return confirm('Delete this user?')">
                              @csrf @method('DELETE')
                              <button type="submit" class="text-xs px-2 py-1.5 rounded-lg border border-red-200 text-red-400 hover:bg-red-50 transition">Delete</button>
                            </form>
                          @endif
                        </div>
                      </template>
                      <template x-if="editing">
                        <div class="flex items-center gap-1">
                          <button type="submit" form="edit-user-{{ $user->id }}" class="text-xs px-2 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">Save</button>
                          <button @click="editing = false" class="text-xs px-2 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">Cancel</button>
                        </div>
                      </template>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</x-layout>
