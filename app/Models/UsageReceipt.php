<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'user_id',
        'product_id',
        'variant_id',
        'shop_id',
        'product_name',
        'variant_name',
        'shop_name',
        'user_full_name',
        'user_account_number',
        'used_at',
        'valid_until',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    public static function generateCode(string $shopSlug): string
    {
        // Prefijo de la tienda (3 letras): criaturas -> CRI, objetos -> OBJ
        $shopPrefix = strtoupper(substr(preg_replace('/[^a-z]/', '', strtolower($shopSlug)), 0, 3));

        $maxAttempts = 10;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Consecutivo: el ID más alto + 1 (con padding a 4 dígitos)
            $lastId = self::max('id') ?? 0;
            $consecutive = str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);

            // 3 caracteres aleatorios alfanuméricos (mayúsculas + dígitos)
            // Sin caracteres confusos (0/O, 1/I/L) para que se pueda dictar por WhatsApp
            $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $random = '';
            for ($i = 0; $i < 3; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $code = "CD-{$shopPrefix}-{$consecutive}-{$random}";

            // Verificar contra la DB (incluye eliminados, por si acaso)
            if (!self::where('code', $code)->exists()) {
                return $code;
            }
            // Si colisionó, el loop reintenta con otro random
        }

        throw new \RuntimeException('No se pudo generar un código único después de 10 intentos.');
    }
}