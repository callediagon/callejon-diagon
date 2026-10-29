<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountRequest;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Notification;    

class BankController extends Controller
{
    // ========================================
    // USUARIOS
    // ========================================

    public function users(Request $request)
    {
        $this->authorizeBankStaff($request);
        $users = User::with('roles')->orderBy('name')->get()->map(fn ($u) => $this->userPayload($u));
        return response()->json(['users' => $users]);
    }

    public function userTransactions(Request $request, User $user)
    {
        $this->authorizeBankStaff($request);
        $transactions = $user->transactions()->with('createdBy:id,username,name')->limit(100)->get()->map(fn ($t) => [
            'id' => $t->id,
            'type' => $t->type,
            'amount' => $t->amount,
            'reason' => $t->reason,
            'note' => $t->note,
            'created_by' => $t->createdBy?->username,
            'created_at' => $t->created_at->toIso8601String(),
        ]);
        return response()->json([
            'user' => [
                'id' => $user->id,
                'account_number' => $user->account_number,
                'username' => $user->username,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'balance' => $user->balance,
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * Banquero da o quita galeones directamente
     */
    public function adjustBalance(Request $request, User $user)
    {
        $this->authorizeBankStaff($request);

        $data = $request->validate([
            'amount' => 'required|integer|min:1',
            'direction' => 'required|in:credit,debit',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        if ($data['reason'] === 'Otro' && empty($data['note'])) {
            throw ValidationException::withMessages([
                'note' => ['Debes explicar el motivo cuando eliges "Otro".'],
            ]);
        }

        $signedAmount = $data['direction'] === 'credit' ? $data['amount'] : -$data['amount'];

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
            'type' => $data['direction'],
            'amount' => $signedAmount,
            'reason' => $data['reason'],
            'note' => $data['note'] ?? null,
        ]);
        
        $direction = $signedAmount >= 0 ? 'recibido' : 'descontado';
        
        Notification::notify(
            $user->id,
            'balance_adjusted',
            'Ajuste de saldo',
            'Se te han ' . $direction . ' ' . abs($signedAmount) . ' 🪙. Motivo: ' . $data['reason']
        );

        return response()->json([
            'message' => 'Saldo ajustado.',
            'new_balance' => $user->fresh()->balance,
            'transaction_id' => $transaction->id,
        ]);
    }

    // ========================================
    // SOLICITUDES DE CUENTA
    // ========================================

    public function requestAccount(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['banquero', 'director', 'vice'])) {
            abort(403, 'Solo el banquero puede crear cuentas.');
        }
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
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);

        // Notificar a todos los directores y vices
        $approvers = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['director', 'vice']);
        })->get();

        foreach ($approvers as $approver) {
            Notification::notify(
                $approver->id,
                'account_request_pending',
                'Nueva solicitud de cuenta',
                '@' . $user->username . ' solicita crear la cuenta de @' . $accountRequest->username . '.'
            );
        }

        return response()->json([
            'message' => 'Solicitud creada. Pendiente de aprobación.',
            'request_id' => $accountRequest->id,
        ], 201);
    }

    public function accountRequests(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['banquero', 'director', 'vice'])) {
            abort(403, 'No autorizado.');
        }
        $requests = AccountRequest::with('requestedBy:id,username,name')->where('status', 'pending')->orderBy('created_at')->get()->map(fn ($r) => [
            'id' => $r->id,
            'username' => $r->username,
            'name' => $r->name,
            'last_name' => $r->last_name,
            'requested_by' => $r->requestedBy?->username,
            'created_at' => $r->created_at->toIso8601String(),
        ]);
        return response()->json(['requests' => $requests]);
    }

    public function approveAccountRequest(Request $request, AccountRequest $accountRequest)
    {
        $reviewer = $request->user();
        if (!$reviewer || !$reviewer->hasAnyRole(['director', 'vice'])) {
            abort(403, 'Solo director o vice pueden aprobar cuentas.');
        }
        if ($accountRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Esta solicitud ya no está pendiente.']]);
        }
        DB::transaction(function () use ($accountRequest, $reviewer) {
            $newUser = User::create([
                'account_number' => User::nextAvailableAccountNumber(),
                'username' => $accountRequest->username,
                'name' => $accountRequest->name,
                'last_name' => $accountRequest->last_name,
                'password' => $accountRequest->password_hash,
                'is_active' => true,
            ]);
            $estudiante = Role::where('slug', 'estudiante')->firstOrFail();
            $newUser->roles()->attach($estudiante->id);
            Transaction::create([
                'user_id' => $newUser->id,
                'created_by' => $reviewer->id,
                'type' => 'credit',
                'amount' => 30,
                'reason' => 'Bienvenida',
            ]);
            $accountRequest->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            Notification::notify(
                $newUser->id,
                'account_created',
                '¡Bienvenido al Callejón Diagon!',
                'Tu cuenta ha sido creada. Empiezas con 30 galeones.'
            );
        });
        return response()->json(['message' => 'Cuenta creada con 30 G de bienvenida.']);
    }

    public function rejectAccountRequest(Request $request, AccountRequest $accountRequest)
    {
        $reviewer = $request->user();
        if (!$reviewer || !$reviewer->hasAnyRole(['director', 'vice'])) {
            abort(403, 'Solo director o vice pueden rechazar cuentas.');
        }
        if ($accountRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Esta solicitud ya no está pendiente.']]);
        }
        $data = $request->validate(['reason' => 'required|string|max:500']);
                $accountRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['reason'],
        ]);

        Notification::notify(
            $accountRequest->requested_by,
            'account_rejected',
            'Solicitud rechazada',
            'La solicitud de cuenta para @' . $accountRequest->username . ' fue rechazada. Motivo: ' . $data['reason']
        );

        return response()->json(['message' => 'Solicitud rechazada.']);
    }

    public function createUserDirect(Request $request)
    {
        $reviewer = $request->user();
        if (!$reviewer || !$reviewer->hasAnyRole(['director', 'vice'])) {
            abort(403, 'Solo director o vice pueden crear cuentas directamente.');
        }
        $data = $request->validate([
            'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username',
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'password' => 'required|string|min:8|max:255',
            'role' => 'required|in:estudiante,banquero,jefe_casa,moderador,vice,director',
        ]);
                $newUser = DB::transaction(function () use ($data, $reviewer) {
            $newUser = User::create([
                'account_number' => User::nextAvailableAccountNumber(),
                'username' => $data['username'],
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $role = Role::where('slug', $data['role'])->firstOrFail();
            $newUser->roles()->attach($role->id);

            // TODOS los roles empiezan con 30 G
            Transaction::create([
                'user_id' => $newUser->id,
                'created_by' => $reviewer->id,
                'type' => 'credit',
                'amount' => 30,
                'reason' => 'Bienvenida',
            ]);

            return $newUser;
        });
        return response()->json([
            'message' => 'Usuario creado.',
            'user' => $this->userPayload($newUser->fresh('roles')),
        ], 201);
    }

    // ========================================
    // TRANSFERENCIAS
    // ========================================

        public function createTransferRequest(Request $request)
    {
        $from = $request->user();
        $data = $request->validate([
            'to_username' => 'required|string',
            'amount' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        // Detectar si es número de cuenta (3 dígitos) o username
        $input = trim($data['to_username']);
        $isAccountNumber = preg_match('/^\d{3,}$/', $input) === 1;

        $to = $isAccountNumber
            ? User::where('account_number', $input)->first()
            : User::where('username', $input)->first();

        if (!$to) {
            throw ValidationException::withMessages([
                'to_username' => ['No existe ningún usuario con ese username o número de cuenta.'],
            ]);
        }

        if ($to->id === $from->id) {
            throw ValidationException::withMessages(['to_username' => ['No puedes transferirte a ti mismo.']]);
        }
        if (!$to->is_active) {
            throw ValidationException::withMessages(['to_username' => ['El destinatario está inactivo.']]);
        }

        $existing = TransferRequest::where('from_user_id', $from->id)
            ->where('to_user_id', $to->id)
            ->where('amount', $data['amount'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages(['amount' => ['Ya tienes una solicitud idéntica pendiente.']]);
        }

        $transfer = TransferRequest::create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
        ]);

        Notification::notify(
            $to->id,
            'transfer_pending',
            'Transferencia pendiente',
            '@' . $from->username . ' quiere enviarte ' . $data['amount'] . ' G. Esperando aprobación.'
        );

        return response()->json([
            'message' => 'Solicitud de transferencia creada. Esperando aprobación del banquero.',
            'id' => $transfer->id,
        ], 201);

                // Notificar a banqueros, vices y directores
        $approvers = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['banquero', 'vice', 'director']);
        })->where('id', '!=', $from->id)->get();

        foreach ($approvers as $approver) {
            Notification::notify(
                $approver->id,
                'transfer_request_pending',
                'Transferencia por aprobar',
                '@' . $from->username . ' quiere enviar ' . $data['amount'] . ' 🪙 a @' . $to->username . '.'
            );
        }
    }

    public function myTransfers(Request $request)
    {
        $user = $request->user();
        $sent = TransferRequest::where('from_user_id', $user->id)
            ->with('toUser:id,username,name')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($t) => $this->transferPayload($t, 'sent'));
        $received = TransferRequest::where('to_user_id', $user->id)
            ->with('fromUser:id,username,name')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($t) => $this->transferPayload($t, 'received'));
        return response()->json(['sent' => $sent, 'received' => $received]);
    }

    public function cancelTransfer(Request $request, TransferRequest $transferRequest)
    {
        $user = $request->user();
        if ($transferRequest->from_user_id !== $user->id) {
            abort(403, 'Solo el emisor puede cancelar.');
        }
        if ($transferRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Esta transferencia ya no está pendiente.']]);
        }
        $transferRequest->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Transferencia cancelada.']);
    }

    public function pendingTransfers(Request $request)
    {
        $this->authorizeBankStaff($request);
        $list = TransferRequest::where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['fromUser:id,username,name', 'toUser:id,username,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($t) => $this->transferPayload($t));
        return response()->json(['transfers' => $list]);
    }

    public function approveTransfer(Request $request, TransferRequest $transferRequest)
    {
        $this->authorizeBankStaff($request);
        if ($transferRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Esta transferencia ya no está pendiente.']]);
        }
        if ($transferRequest->expires_at->isPast()) {
            throw ValidationException::withMessages(['status' => ['Esta transferencia ya expiró.']]);
        }
        DB::transaction(function () use ($transferRequest, $request) {
            Transaction::create([
                'user_id' => $transferRequest->from_user_id,
                'created_by' => $request->user()->id,
                'type' => 'debit',
                'amount' => -$transferRequest->amount,
                'reason' => 'Transferencia enviada a @' . $transferRequest->toUser->username,
            ]);
            Transaction::create([
                'user_id' => $transferRequest->to_user_id,
                'created_by' => $request->user()->id,
                'type' => 'credit',
                'amount' => $transferRequest->amount,
                'reason' => 'Transferencia recibida de @' . $transferRequest->fromUser->username,
            ]);
            $transferRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });
        Notification::notify(
            $transferRequest->from_user_id,
            'transfer_approved',
            'Transferencia aprobada',
            'Tu transferencia de ' . $transferRequest->amount . ' 🪙 a @' . $transferRequest->toUser->username . ' fue aprobada.'
        );

        Notification::notify(
            $transferRequest->to_user_id,
            'transfer_received',
            'Transferencia recibida',
            'Has recibido ' . $transferRequest->amount . ' 🪙 de @' . $transferRequest->fromUser->username . '.'
        );
        return response()->json(['message' => 'Transferencia aprobada.']);
    }

    public function rejectTransfer(Request $request, TransferRequest $transferRequest)
    {
        $this->authorizeBankStaff($request);
        if ($transferRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Esta transferencia ya no está pendiente.']]);
        }
        $transferRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        Notification::notify(
            $transferRequest->from_user_id,
            'transfer_rejected',
            'Transferencia rechazada',
            'Tu transferencia de ' . $transferRequest->amount . ' 🪙 a @' . $transferRequest->toUser->username . ' fue rechazada.'
        );
        return response()->json(['message' => 'Transferencia rechazada.']);
    }

        /**
     * Estudiante ve sus propias transacciones
     */
        public function myTransactions(Request $request)
    {
        $user = $request->user();
        $canReverse = $user->hasAnyRole(['banquero', 'director', 'vice']);

        $transactions = $user->transactions()
            ->with('createdBy:id,username,name')
            ->limit(100)
            ->get()
            ->map(function ($t) use ($canReverse) {
                $isReversed = Transaction::where('related_transaction_id', $t->id)->exists();
                return [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'reason' => $t->reason,
                    'note' => $t->note,
                    'created_by' => $t->createdBy?->username,
                    'created_at' => $t->created_at->toIso8601String(),
                    'can_reverse' => $canReverse && $t->type !== 'reversal' && !$isReversed,
                ];
            });

        return response()->json(['transactions' => $transactions]);
    }

        /**
     * Banquero revierte una transacción creando la inversa
     */
    public function reverseTransaction(Request $request, Transaction $transaction)
    {
        $this->authorizeBankStaff($request);

        // No se puede revertir una reversión
        if ($transaction->type === 'reversal') {
            throw ValidationException::withMessages([
                'transaction' => ['No puedes revertir una reversión.'],
            ]);
        }

        // No se puede revertir dos veces la misma
        $alreadyReversed = Transaction::where('related_transaction_id', $transaction->id)->exists();
        if ($alreadyReversed) {
            throw ValidationException::withMessages([
                'transaction' => ['Esta transacción ya fue revertida.'],
            ]);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $reversal = DB::transaction(function () use ($transaction, $request, $data) {
            $reversal = Transaction::create([
                'user_id' => $transaction->user_id,
                'created_by' => $request->user()->id,
                'type' => 'reversal',
                'amount' => -$transaction->amount,
                'reason' => 'Reversión: ' . $data['reason'],
                'related_transaction_id' => $transaction->id,
            ]);

            return $reversal;
        });

        Notification::notify(
            $transaction->user_id,
            'transaction_reversed',
            'Transacción revertida',
            'Se ha revertido una transacción de ' . $transaction->amount . ' 🪙. Motivo: ' . $data['reason']
        );

        return response()->json([
            'message' => 'Transacción revertida.',
            'reversal_id' => $reversal->id,
            'new_balance' => $transaction->user->fresh()->balance,
        ]);
    }

        /**
     * Activar / desactivar usuario (no se borra)
     */
    public function toggleActive(Request $request, User $user)
    {
        $this->authorizeBankStaff($request);

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => ['No puedes desactivarte a ti mismo.'],
            ]);
        }

        $user->update(['is_active' => !$user->is_active]);

        Notification::notify(
            $user->id,
            'account_status_changed',
            $user->is_active ? 'Cuenta reactivada' : 'Cuenta desactivada',
            $user->is_active
                ? 'Tu cuenta ha sido reactivada. Ya puedes ingresar.'
                : 'Tu cuenta ha sido desactivada. Contacta al banco para más información.'
        );

        return response()->json([
            'message' => $user->is_active ? 'Usuario activado.' : 'Usuario desactivado.',
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Reset de contraseña por banquero/director/vice
     */
    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeBankStaff($request);

        $data = $request->validate([
            'password' => 'required|string|min:8|max:255',
        ]);

        $user->update(['password' => $data['password']]); // el cast 'hashed' lo hashea

        Notification::notify(
            $user->id,
            'password_reset',
            'Contraseña restablecida',
            'Un administrador ha restablecido tu contraseña. Contacta al banco si no fuiste tú.'
        );

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

        /**
     * Director elimina una cuenta (soft delete)
     */
    public function deleteUser(Request $request, User $user)
    {
        $director = $request->user();

        if (!$director || !$director->hasRole('director')) {
            abort(403, 'Solo el director puede eliminar cuentas.');
        }

        if ($user->id === $director->id) {
            throw ValidationException::withMessages([
                'user' => ['No puedes eliminarte a ti mismo.'],
            ]);
        }

        if ($user->hasRole('director') && $user->id !== $director->id) {
            throw ValidationException::withMessages([
                'user' => ['No puedes eliminar a otro director.'],
            ]);
        }

        $username = $user->username;
        $user->delete();

        Notification::notify(
            $director->id,
            'user_deleted',
            'Cuenta eliminada',
            'La cuenta @' . $username . ' fue eliminada.'
        );

        return response()->json(['message' => 'Cuenta eliminada.']);
    }

        /**
     * Auditoría: todas las transacciones del colegio (solo director/vice/banquero)
     */
    public function allTransactions(Request $request)
    {
        $this->authorizeBankStaff($request);

        $transactions = Transaction::with(['user:id,username,name', 'createdBy:id,username,name'])
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'user_username' => $t->user?->username,
                'user_name' => $t->user?->name,
                'type' => $t->type,
                'amount' => $t->amount,
                'reason' => $t->reason,
                'note' => $t->note,
                'created_by' => $t->createdBy?->username,
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        return response()->json(['transactions' => $transactions]);
    }
    
    // ========================================
    // HELPERS
    // ========================================

    private function authorizeBankStaff(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['banquero', 'director', 'vice'])) {
            abort(403, 'Solo el banco puede acceder a esta información.');
        }
    }

    private function userPayload(User $u): array
    {
        return [
            'id' => $u->id,
            'account_number' => $u->account_number,
            'username' => $u->username,
            'name' => $u->name,
            'last_name' => $u->last_name,
            'is_active' => $u->is_active,
            'roles' => $u->roles->pluck('slug'),
            'balance' => $u->balance,
        ];
    }

    private function transferPayload(TransferRequest $t, ?string $direction = null): array
    {
        return [
            'id' => $t->id,
            'from_username' => $t->fromUser->username,
            'from_name' => $t->fromUser->name,
            'to_username' => $t->toUser->username,
            'to_name' => $t->toUser->name,
            'amount' => $t->amount,
            'reason' => $t->reason,
            'status' => $t->status,
            'created_at' => $t->created_at->toIso8601String(),
            'expires_at' => $t->expires_at->toIso8601String(),
            'direction' => $direction,
        ];
    }
}