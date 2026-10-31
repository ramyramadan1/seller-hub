<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lives in the tenant database (no tenant_id column: the database is the boundary). */
class Product extends Model
{
    protected $fillable = ['sku', 'title', 'description', 'price', 'cost_price', 'stock', 'images', 'source_image', 'ai_status'];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'images' => 'array',
    ];
}
