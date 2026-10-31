<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lives in the tenant database. Credentials are encrypted and never serialized to the API. */
class Channel extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_NEEDS_REAUTH = 'needs_reauth';
    public const STATUS_ERROR = 'error';

    protected $fillable = ['driver', 'name', 'credentials', 'external_store_id', 'status', 'last_error', 'last_synced_at'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_synced_at' => 'datetime',
        ];
    }
}
