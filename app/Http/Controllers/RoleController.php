<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function rolesIndex()
    {
        $roles = Role::withCount('users')->orderByDesc('level')->orderBy('name')->get();

        return view('admin.roles', compact('roles'));
    }

    public function usersIndex()
    {
        $roles = Role::withCount('users')->orderByDesc('level')->orderBy('name')->get();
        $users = User::with('role')->orderBy('name')->get();

        return view('admin.users', compact('roles', 'users'));
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
            'level'    => $request->integer('level', 0),
        ]);

        return back()->with('success', 'Role added!');
    }

    public function updateRole(Request $request, Role $role)
    {
        // Protect roles with level >= auth user's level (unless auth is CEO level 100+)
        $authLevel = auth()->user()->role->level ?? 0;
        if ($role->level >= $authLevel && $authLevel < 100) {
            return back()->with('error', 'You do not have permission to edit this role.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,' . $role->id,
        ]);

        $role->update([
            'name'     => $validated['name'],
            'slug'     => Str::slug($validated['name']),
            'is_admin' => $request->boolean('is_admin'),
            'level'    => $request->integer('level', $role->level),
        ]);

        return back()->with('success', 'Role updated!');
    }

    public function destroyRole(Role $role)
    {
        // Protect roles with level >= auth user's level (unless auth is CEO level 100+)
        $authLevel = auth()->user()->role->level ?? 0;
        if ($role->level >= $authLevel && $authLevel < 100) {
            return back()->with('error', 'You do not have permission to delete this role.');
        }

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
            'username' => 'nullable|string|max:100|unique:users,username|regex:/^[a-zA-Z0-9._-]+$/',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id'  => 'required|exists:roles,id',
        ], [
            'username.regex' => 'Username can only contain letters, numbers, dots, hyphens, and underscores.',
        ]);

        // Prevent assigning a role with higher level than the auth user's role
        $authLevel = auth()->user()->role->level ?? 0;
        $targetRole = Role::find($validated['role_id']);
        if ($targetRole && $targetRole->level > $authLevel) {
            return back()->with('error', 'You cannot assign a role higher than your own.');
        }

        User::create([
            'name'           => $validated['name'],
            'username'       => $validated['username'] ?? null,
            'email'          => $validated['email'],
            'password'       => Hash::make($validated['password']),
            'plain_password' => $validated['password'],
            'role_id'        => $validated['role_id'],
            'is_active'      => true,
        ]);

        return back()->with('success', 'User added!');
    }

    public function updateUser(Request $request, User $user)
    {
        // Protect higher-level users (e.g., CEO cannot be edited by Admin)
        $authLevel = auth()->user()->role->level ?? 0;
        $targetLevel = $user->role->level ?? 0;
        if ($targetLevel > $authLevel) {
            return back()->with('error', 'You do not have permission to edit this user.');
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'nullable|string|max:100|unique:users,username,' . $user->id . '|regex:/^[a-zA-Z0-9._-]+$/',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role_id'  => 'required|exists:roles,id',
        ], [
            'username.regex' => 'Username can only contain letters, numbers, dots, hyphens, and underscores.',
        ]);

        // Prevent assigning a role with higher level than the auth user's role
        $newRole = Role::find($validated['role_id']);
        if ($newRole && $newRole->level > $authLevel) {
            return back()->with('error', 'You cannot assign a role higher than your own.');
        }

        $user->update([
            'name'     => $validated['name'],
            'username' => $validated['username'] ?? null,
            'email'    => $validated['email'],
            'role_id'  => $validated['role_id'],
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:6']);
            $user->update([
                'password'       => Hash::make($request->password),
                'plain_password' => $request->password,
            ]);
        }

        return back()->with('success', 'User updated!');
    }

    public function toggleUser(User $user)
    {
        // Protect higher-level users
        $authLevel = auth()->user()->role->level ?? 0;
        $targetLevel = $user->role->level ?? 0;
        if ($targetLevel > $authLevel) {
            return back()->with('error', 'You do not have permission to modify this user.');
        }

        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', $user->name . ' has been ' . ($user->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        // Protect higher-level users
        $authLevel = auth()->user()->role->level ?? 0;
        $targetLevel = $user->role->level ?? 0;
        if ($targetLevel > $authLevel) {
            return back()->with('error', 'You do not have permission to delete this user.');
        }

        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
