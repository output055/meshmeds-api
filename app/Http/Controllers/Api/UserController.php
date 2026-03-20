<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $users = User::all()->map(function ($user) {
            return [
                'id'        => (string) $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role,
                'status'    => $user->status,
                'lastLogin' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : 'Never',
            ];
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'role'     => ['required', Rule::in(['Admin', 'Pharmacist', 'Attendant'])],
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'role'     => $validated['role'],
            'password' => Hash::make($validated['password'] ?? 'Password@123'),
            'status'   => 'Active',
        ]);

        $this->logActivity('create_user', "Created user {$user->name} ({$user->role})");

        return response()->json([
            'id'        => (string) $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'status'    => $user->status,
            'lastLogin' => 'Never',
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'  => ['required', Rule::in(['Admin', 'Pharmacist', 'Attendant'])],
        ]);

        $user->update($validated);

        $this->logActivity('update_user', "Updated user {$user->name}'s profile");

        return response()->json([
            'id'        => (string) $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'status'    => $user->status,
            'lastLogin' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : 'Never',
        ]);
    }

    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $user->update(['status' => $validated['status']]);

        $this->logActivity('update_user_status', "Set {$user->name}'s account to {$user->status}");

        return response()->json([
            'message' => 'User status updated successfully.',
            'status'  => $user->status,
        ]);
    }
}
