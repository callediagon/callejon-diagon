<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'name',
        'description',
        'price',
        'validity_hours',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'validity_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}