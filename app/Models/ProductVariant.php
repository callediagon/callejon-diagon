<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'price_override',
        'stock',
    ];

    protected $casts = [
        'price_override' => 'integer',
        'stock' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function finalPrice(): int
    {
        return $this->price_override ?? $this->product->price;
    }

    public function isInStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }
}