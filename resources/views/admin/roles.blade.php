<x-layout>
  <x-slot name="heading">Roles & Users</x-slot>
  <x-slot name="title">Roles & Users</x-slot>

  <div class="p-4 max-w-5xl mx-auto space-y-4 mt-16">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <h1 class="text-xl font-bold text-gray-800">Roles & Users</h1>
        <p class="text-sm text-gray-500">Manage roles and user accounts.</p>
      </div>
      <a href="{{ route('checklist.index') }}"
         class="text-sm px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-700">
        ← Checklist
      </a>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      {{-- ====== ROLES SECTION ====== --}}
      <div class="lg:col-span-1 space-y-3">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
          <h2 class="font-semibold text-gray-800 text-sm mb-3">Roles</h2>

          {{-- Add Role --}}
          <form method="POST" action="{{ route('admin.store-role') }}" class="flex gap-2 mb-3">
            @csrf
            <input type="text" name="name" placeholder="New role name..." required
                   class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            <label class="flex items-center gap-1 text-xs text-gray-500 whitespace-nowrap">
              <input type="checkbox" name="is_admin" value="1" class="accent-purple-600"> Admin
            </label>
            <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 font-medium">Add</button>
          </form>

          {{-- Role List --}}
          <div class="space-y-1.5">
            @foreach($roles as $role)
              <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 transition" x-data="{ editing: false }">
                <template x-if="!editing">
                  <div class="flex items-center gap-2 flex-1 min-w-0">
                    <span class="text-sm font-medium text-gray-700">{{ $role->name }}</span>
                    @if($role->is_admin)
                      <span class="text-xs px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-600">Admin</span>
                    @endif
                    <span class="text-xs text-gray-400">({{ $role->users_count }} users)</span>
                  </div>
                </template>
                <template x-if="editing">
                  <form method="POST" action="{{ route('admin.update-role', $role) }}" class="flex items-center gap-2 flex-1" id="edit-role-{{ $role->id }}">
                    @csrf @method('PATCH')
                    <input type="text" name="name" value="{{ $role->name }}" required
                           class="flex-1 border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    <label class="flex items-center gap-1 text-xs text-gray-500 whitespace-nowrap">
                      <input type="checkbox" name="is_admin" value="1" {{ $role->is_admin ? 'checked' : '' }} class="accent-purple-600"> Admin
                    </label>
                  </form>
                </template>

                <div class="flex items-center gap-1 ml-2">
                  <template x-if="!editing">
                    <div class="flex items-center gap-1">
                      <button @click="editing = true" class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-500 hover:bg-white transition">Edit</button>
                      @if($role->users_count === 0)
                        <form method="POST" action="{{ route('admin.destroy-role', $role) }}" onsubmit="return confirm('Delete role \'{{ $role->name }}\'?')">
                          @csrf @method('DELETE')
                          <button type="submit" class="text-xs px-2 py-1 rounded border border-red-200 text-red-400 hover:bg-red-50 transition">Del</button>
                        </form>
                      @endif
                    </div>
                  </template>
                  <template x-if="editing">
                    <div class="flex items-center gap-1">
                      <button type="submit" form="edit-role-{{ $role->id }}" class="text-xs px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700 transition">Save</button>
                      <button @click="editing = false" class="text-xs px-2 py-1 rounded border border-gray-200 text-gray-500 hover:bg-white transition">Cancel</button>
                    </div>
                  </template>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      {{-- ====== USERS SECTION ====== --}}
      <div class="lg:col-span-2 space-y-3">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
          <h2 class="font-semibold text-gray-800 text-sm mb-3">Users</h2>

          {{-- Add User Form --}}
          <form method="POST" action="{{ route('admin.store-user') }}" class="mb-4 p-3 bg-gray-50 rounded-xl space-y-2">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Add New User</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <input type="text" name="name" placeholder="Full name" required
                     class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
              <input type="email" name="email" placeholder="Email address" required
                     class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div class="relative">
                <input type="text" name="password" placeholder="Password" required
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
              </div>
              <select name="role_id" required
                      class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                <option value="">Select role...</option>
                @foreach($roles as $r)
                  <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">+ Add User</button>
          </form>

          {{-- Users Table (Desktop) --}}
          <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100 text-xs text-gray-400 uppercase tracking-wide">
                  <th class="text-left px-3 py-2">Name</th>
                  <th class="text-left px-3 py-2">Email</th>
                  <th class="text-left px-3 py-2">Password</th>
                  <th class="text-left px-3 py-2">Role</th>
                  <th class="text-center px-3 py-2">Status</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                @foreach($users as $user)
                  <tr x-data="{ editing: false, showPw: false }" class="hover:bg-gray-50/40">

                    {{-- Name --}}
                    <template x-if="!editing">
                      <td class="px-3 py-3 font-medium text-gray-700">{{ $user->name }}</td>
                    </template>
                    <template x-if="editing">
                      <td class="px-3 py-3">
                        <form id="edit-user-{{ $user->id }}" method="POST" action="{{ route('admin.update-user', $user) }}">
                          @csrf @method('PATCH')
                          <input type="text" name="name" value="{{ $user->name }}" required
                                 class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-300">
                        </form>
                      </td>
                    </template>

                    {{-- Email --}}
                    <template x-if="!editing">
                      <td class="px-3 py-3 text-gray-500">{{ $user->email }}</td>
                    </template>
                    <template x-if="editing">
                      <td class="px-3 py-3">
                        <input type="email" name="email" value="{{ $user->email }}" required form="edit-user-{{ $user->id }}"
                               class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-300">
                      </td>
                    </template>

                    {{-- Password --}}
                    <template x-if="!editing">
                      <td class="px-3 py-3">
                        <div class="flex items-center gap-1.5">
                          <span class="text-sm text-gray-500 font-mono" x-text="showPw ? '{{ $user->plain_password ?? '••••••' }}' : '•••••••'"></span>
                          @if($user->plain_password)
                            <button type="button" @click="showPw = !showPw"
                                    class="text-xs text-gray-400 hover:text-gray-600 transition"
                                    x-text="showPw ? '🙈' : '👁'">👁</button>
                          @endif
                        </div>
                      </td>
                    </template>
                    <template x-if="editing">
                      <td class="px-3 py-3">
                        <input type="text" name="password" placeholder="New password (leave blank to keep)" form="edit-user-{{ $user->id }}"
                               class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-300">
                      </td>
                    </template>

                    {{-- Role --}}
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
                      </td>
                    </template>

                    {{-- Status --}}
                    <td class="px-3 py-3 text-center">
                      @if($user->is_active)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600">Active</span>
                      @else
                        <span class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-500">Inactive</span>
                      @endif
                    </td>

                    {{-- Actions --}}
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

          {{-- Users Cards (Mobile) --}}
          <div class="md:hidden space-y-2">
            @foreach($users as $user)
              <div class="border border-gray-200 rounded-xl p-3 space-y-2" x-data="{ editing: false, showPw: false }">
                <template x-if="!editing">
                  <div>
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full {{ $user->role?->is_admin ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600' }} flex items-center justify-center text-xs font-bold flex-shrink-0">
                          {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div>
                          <p class="text-sm font-semibold text-gray-800">{{ $user->name }}</p>
                          <p class="text-xs text-gray-400">{{ $user->email }}</p>
                        </div>
                      </div>
                      <div class="flex items-center gap-1">
                        @if($user->is_active)
                          <span class="w-2 h-2 rounded-full bg-green-400"></span>
                        @else
                          <span class="w-2 h-2 rounded-full bg-red-400"></span>
                        @endif
                      </div>
                    </div>

                    <div class="flex items-center gap-2 mt-2">
                      <span class="text-xs px-2 py-0.5 rounded-full {{ $user->role?->is_admin ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600' }}">
                        {{ $user->role?->name ?? 'No Role' }}
                      </span>
                      <div class="flex items-center gap-1 text-xs">
                        <span class="text-gray-400 font-mono" x-text="showPw ? '{{ $user->plain_password ?? '••••••' }}' : '•••••••'"></span>
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
                        <form method="POST" action="{{ route('admin.destroy-user', $user) }}" onsubmit="return confirm('Delete this user?')" class="flex-1">
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
      </div>

    </div>
  </div>
</x-layout>
