<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $username
 * @property string $name
 * @property string|null $last_name
 * @property string $password
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'account_number',
        'username',
        'name',
        'last_name',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ========================================
    // RELACIONES
    // ========================================

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderBy('created_at', 'desc');
    }

    public function transferRequestsSent()
    {
        return $this->hasMany(TransferRequest::class, 'from_user_id');
    }

    public function transferRequestsReceived()
    {
        return $this->hasMany(TransferRequest::class, 'to_user_id');
    }

    public function accountRequestsMade()
    {
        return $this->hasMany(AccountRequest::class, 'requested_by');
    }

    public function assignmentRequestsMade()
    {
        return $this->hasMany(AssignmentRequest::class, 'requested_by');
    }

    public function assignmentRequestsReceived()
    {
        return $this->hasMany(AssignmentRequest::class, 'target_user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('is_read', false);
    }

    // ========================================
    // HELPERS DE ROLES
    // ========================================

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    public function assignRole(string $slug): void
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function removeRole(string $slug): void
    {
        $role = Role::where('slug', $slug)->first();
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    // ========================================
    // HELPERS DE NEGOCIO
    // ========================================

    public function getBalanceAttribute(): int
    {
        return (int) $this->transactions()->sum('amount');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->last_name);
    }

        /**
     * Asigna el siguiente número de cuenta disponible.
     * - 1-999: 001, 002, ..., 999 (3 dígitos con padding)
     * - 1000+: 1000, 1001, ... (numérico natural, sin padding)
     */
    public static function nextAvailableAccountNumber(): string
    {
        $used = self::whereNotNull('account_number')
            ->pluck('account_number')
            ->map(fn ($n) => (int) $n)
            ->flip() // [num => index] para búsqueda O(1)
            ->all();

        for ($i = 1; $i <= 999999999; $i++) {
            if (!isset($used[$i])) {
                return $i <= 999
                    ? str_pad((string) $i, 3, '0', STR_PAD_LEFT)
                    : (string) $i;
            }
        }

        throw new \RuntimeException('No hay números de cuenta disponibles.');
    }
}