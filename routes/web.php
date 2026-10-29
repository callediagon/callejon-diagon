<?php

use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// === API interna (JSON) ===
Route::prefix('api')->group(function () {
    Route::post('/login', [LoginController::class, 'login'])->name('api.login');
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('api.logout');
    Route::get('/me', [LoginController::class, 'me'])->name('api.me');

    // Banco (protegido)
    Route::middleware('auth')->group(function () {
        Route::get('/bank/users', [BankController::class, 'users'])->name('api.bank.users');
        Route::get('/bank/users/{user}/transactions', [BankController::class, 'userTransactions'])->name('api.bank.transactions');
        Route::post('/bank/account-requests', [BankController::class, 'requestAccount'])->name('api.bank.request-account');
        Route::get('/bank/account-requests', [BankController::class, 'accountRequests'])->name('api.bank.account-requests');
        Route::post('/bank/account-requests/{accountRequest}/approve', [BankController::class, 'approveAccountRequest'])->name('api.bank.approve-account');
        Route::post('/bank/account-requests/{accountRequest}/reject', [BankController::class, 'rejectAccountRequest'])->name('api.bank.reject-account');
        Route::post('/bank/users', [BankController::class, 'createUserDirect'])->name('api.bank.create-user');
        Route::post('/transfers', [BankController::class, 'createTransferRequest'])->name('api.transfers.create');
        Route::get('/transfers/mine', [BankController::class, 'myTransfers'])->name('api.transfers.mine');
        Route::post('/transfers/{transferRequest}/cancel', [BankController::class, 'cancelTransfer'])->name('api.transfers.cancel');
        Route::get('/bank/transfers/pending', [BankController::class, 'pendingTransfers'])->name('api.bank.transfers.pending');
        Route::post('/bank/transfers/{transferRequest}/approve', [BankController::class, 'approveTransfer'])->name('api.bank.transfers.approve');
        Route::post('/bank/transfers/{transferRequest}/reject', [BankController::class, 'rejectTransfer'])->name('api.bank.transfers.reject');
        Route::post('/bank/users/{user}/adjust-balance', [BankController::class, 'adjustBalance'])->name('api.bank.adjust-balance');
        Route::get('/transactions/mine', [BankController::class, 'myTransactions'])->name('api.transactions.mine');
        Route::post('/bank/transactions/{transaction}/reverse', [BankController::class, 'reverseTransaction'])->name('api.bank.reverse');
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index'])->name('api.notifications.index');
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount'])->name('api.notifications.unread');
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->name('api.notifications.read');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
        Route::post('/bank/users/{user}/toggle-active', [BankController::class, 'toggleActive'])->name('api.bank.toggle-active');
        Route::post('/bank/users/{user}/reset-password', [BankController::class, 'resetPassword'])->name('api.bank.reset-password');
        Route::delete('/bank/users/{user}', [BankController::class, 'deleteUser'])->name('api.bank.delete-user');
        Route::get('/bank/transactions/all', [BankController::class, 'allTransactions'])->name('api.bank.transactions.all');
        });
});

// Registro público (no requiere auth)
Route::post('/api/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register'])->name('api.register');

// Ping
Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'time' => now()]);
});

Route::get('/cron/expire-transfers', function (\Illuminate\Http\Request $request) {
    // Seguridad: verificar token secreto
    $expected = env('CRON_SECRET');
    if ($expected && $request->query('token') !== $expected) {
        abort(403, 'Forbidden');
    }

    $count = \App\Models\TransferRequest::where('status', 'pending')
        ->where('expires_at', '<=', now())
        ->update(['status' => 'expired']);

    return response()->json([
        'status' => 'ok',
        'expired' => $count,
        'at' => now()->toIso8601String(),
    ]);
})->name('cron.expire');

// Catch-all → SPA
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*')->name('spa');