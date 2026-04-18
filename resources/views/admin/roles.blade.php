<x-layout>
  <x-slot name="heading">Roles</x-slot>
  <x-slot name="title">Roles</x-slot>

  <div class="p-4 max-w-2xl mx-auto space-y-4 mt-16">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <h1 class="text-xl font-bold text-gray-800">Roles</h1>
        <p class="text-sm text-gray-500">Manage role hierarchy and permissions.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.users') }}"
           class="text-sm px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium">
          <i class="fa-solid fa-users mr-1"></i> Users
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

    {{-- Add Role --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
      <h2 class="font-semibold text-gray-800 text-sm mb-3">Add New Role</h2>
      <form method="POST" action="{{ route('admin.store-role') }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs text-gray-500 mb-1">Role Name</label>
          <input type="text" name="name" placeholder="e.g. Manager" required
                 class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
        </div>
        <div class="w-24">
          <label class="block text-xs text-gray-500 mb-1">Level</label>
          <input type="number" name="level" value="0" min="0" max="{{ auth()->user()->role->level ?? 0 }}"
                 class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
        </div>
        <label class="flex items-center gap-1.5 text-sm text-gray-600 pb-1">
          <input type="checkbox" name="is_admin" value="1" class="accent-purple-600 w-4 h-4"> Admin access
        </label>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
          <i class="fa-solid fa-plus mr-1"></i> Add Role
        </button>
      </form>
      <p class="text-xs text-gray-400 mt-2">Higher level = higher authority. CEO (100) > Admin (50) > Regular (0).</p>
    </div>

    {{-- Role List --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800 text-sm">All Roles</h2>
        <span class="text-xs text-gray-400">{{ $roles->count() }} roles</span>
      </div>

      <div class="divide-y divide-gray-100">
        @foreach($roles as $role)
          <div class="px-4 py-3" x-data="{ editing: false }">

            {{-- View Mode --}}
            <div x-show="!editing" class="flex items-center justify-between">
              <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0
                  {{ $role->level >= 100 ? 'bg-amber-500' : ($role->is_admin ? 'bg-purple-500' : 'bg-blue-500') }}">
                  {{ strtoupper(substr($role->name, 0, 1)) }}
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-800">{{ $role->name }}</span>
                    @if($role->level >= 100)
                      <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 font-medium">CEO</span>
                    @elseif($role->is_admin)
                      <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-600 font-medium">Admin</span>
                    @endif
                  </div>
                  <div class="flex items-center gap-3 text-xs text-gray-400 mt-0.5">
                    <span>Level {{ $role->level }}</span>
                    <span>{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}</span>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-1.5 flex-shrink-0">
                @php
                  $authRoleLevel = auth()->user()->role->level ?? 0;
                  $canEditRole = $role->level < $authRoleLevel || ($authRoleLevel >= 100);
                @endphp
                @if($canEditRole)
                  <button @click="editing = true" class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                  </button>
                  @if($role->users_count === 0)
                    <form method="POST" action="{{ route('admin.destroy-role', $role) }}" onsubmit="return confirm('Delete role \'{{ $role->name }}\'?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-400 hover:bg-red-50 transition">
                        <i class="fa-solid fa-trash mr-1"></i> Delete
                      </button>
                    </form>
                  @endif
                @else
                  <span class="text-xs text-gray-400 font-medium"><i class="fa-solid fa-lock mr-1"></i> Protected</span>
                @endif
              </div>
            </div>

            {{-- Edit Mode --}}
            <div x-show="editing" x-cloak>
              <form method="POST" action="{{ route('admin.update-role', $role) }}" class="space-y-3">
                @csrf @method('PATCH')
                <div class="flex flex-wrap items-end gap-3">
                  <div class="flex-1 min-w-[160px]">
                    <label class="block text-xs text-gray-500 mb-1">Role Name</label>
                    <input type="text" name="name" value="{{ $role->name }}" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                  </div>
                  <div class="w-24">
                    <label class="block text-xs text-gray-500 mb-1">Level</label>
                    <input type="number" name="level" value="{{ $role->level }}" min="0" max="{{ auth()->user()->role->level ?? 0 }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                  </div>
                  <label class="flex items-center gap-1.5 text-sm text-gray-600 pb-1">
                    <input type="checkbox" name="is_admin" value="1" {{ $role->is_admin ? 'checked' : '' }} class="accent-purple-600 w-4 h-4"> Admin access
                  </label>
                </div>
                <div class="flex items-center gap-2">
                  <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
                    <i class="fa-solid fa-check mr-1"></i> Save
                  </button>
                  <button type="button" @click="editing = false" class="px-4 py-2 text-sm text-gray-500 rounded-lg border border-gray-200 hover:bg-gray-50">
                    Cancel
                  </button>
                </div>
              </form>
            </div>

          </div>
        @endforeach
      </div>
    </div>

  </div>
</x-layout>
