<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Detectar si es número de cuenta (3 dígitos) o username
        $input = trim($request->username);
        $isAccountNumber = preg_match('/^\d{3,}$/', $input) === 1;

        $user = $isAccountNumber
            ? User::where('account_number', $input)->first()
            : User::where('username', $input)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Credenciales incorrectas.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Esta cuenta está desactivada.'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'account_number' => $user->account_number,
                'username' => $user->username,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'roles' => $user->roles->pluck('slug'),
                'balance' => $user->balance,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['user' => null], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'account_number' => $user->account_number,
                'username' => $user->username,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'roles' => $user->roles->pluck('slug'),
                'balance' => $user->balance,
            ],
        ]);
    }
}