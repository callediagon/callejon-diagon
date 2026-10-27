<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('favoriteTeam')
            ->orderBy('created_at', 'desc')
            ->get();

        return inertia('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function edit(User $user)
    {
        $teams = Team::where('is_active', true)->orderBy('name')->get();

        return inertia('Admin/Users/Edit', [
            'user' => $user,
            'teams' => $teams,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'favorite_team_id' => 'nullable|exists:teams,id',
            'role' => 'required|in:admin,user',
            'is_active' => 'boolean',
        ]);

        $user->update([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'favorite_team_id' => $request->favorite_team_id,
            'role' => $request->role,
            'is_active' => $request->is_active,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        // No permitir eliminar al administrador principal
        if ($user->id === 1) {
            return back()->with('error', 'No puedes eliminar al administrador principal.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    public function show(User $user)
    {
        return inertia('Admin/Users/Show', [
            'user' => $user,
        ]);
    }
}