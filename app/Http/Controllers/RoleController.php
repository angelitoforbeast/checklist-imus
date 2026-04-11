<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('id')->get();
        $users = User::with('role')->orderBy('name')->get();

        return view('admin.roles', compact('roles', 'users'));
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
        ]);

        Role::create([
            'name'     => $validated['name'],
            'slug'     => Str::slug($validated['name']),
            'is_admin' => $request->boolean('is_admin'),
        ]);

        return back()->with('success', 'Role added!');
    }

    public function updateRole(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,' . $role->id,
        ]);

        $role->update([
            'name'     => $validated['name'],
            'slug'     => Str::slug($validated['name']),
            'is_admin' => $request->boolean('is_admin'),
        ]);

        return back()->with('success', 'Role updated!');
    }

    public function destroyRole(Role $role)
    {
        if ($role->users()->count() > 0) {
            return back()->with('error', 'Cannot delete a role that has users assigned to it. Reassign users first.');
        }

        $role->delete();
        return back()->with('success', 'Role deleted.');
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id'  => 'required|exists:roles,id',
        ]);

        User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'role_id'   => $validated['role_id'],
            'is_active' => true,
        ]);

        return back()->with('success', 'User added!');
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:users,email,' . $user->id,
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update($validated);

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:6']);
            $user->update(['password' => Hash::make($request->password)]);
        }

        return back()->with('success', 'User updated!');
    }

    public function toggleUser(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', $user->name . ' has been ' . ($user->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
