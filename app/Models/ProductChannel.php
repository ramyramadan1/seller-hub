<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tenant database. One row per (product, channel): where a product is published and how it went. */
class ProductChannel extends Model
{
    public const PENDING = 'pending';
    public const SYNCED = 'synced';
    public const FAILED = 'failed';

    protected $table = 'product_channel';

    protected $fillable = ['product_id', 'channel_id', 'external_id', 'sync_status', 'last_error', 'synced_at'];

    protected $casts = ['synced_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
