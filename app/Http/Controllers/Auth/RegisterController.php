<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username|unique:account_requests,username',
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'password' => 'required|string|min:8|max:255',
        ]);

        $accountRequest = AccountRequest::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'last_name' => $data['last_name'] ?? null,
            'password_hash' => Hash::make($data['password']),
            'requested_by' => null, // autorregistro
            'status' => 'pending',
        ]);

        // Notificar a directores, vices y banqueros
        $approvers = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['director', 'vice', 'banquero']);
        })->get();

        foreach ($approvers as $approver) {
            Notification::notify(
                $approver->id,
                'account_request_pending',
                'Nueva solicitud de cuenta',
                '@' . $data['username'] . ' solicita registrarse (autorregistro).'
            );
        }

        return response()->json([
            'message' => 'Registro enviado. Espera aprobación del banco.',
            'request_id' => $accountRequest->id,
        ], 201);
    }
}