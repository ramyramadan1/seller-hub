<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Central user. Never resolved through a tenant connection. */
class User extends Authenticatable
{
    use CentralConnection, HasApiTokens;

    public const ROLE_OWNER = 'owner';
    public const ROLE_STAFF = 'staff';

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
