<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-store key/value settings (tenant database). */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];
}
