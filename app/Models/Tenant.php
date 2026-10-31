<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * One row per store (central database). Each tenant owns a separate database named tenant_{id}.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $currency
 * @property string $locale
 * @property int|null $plan_id
 * @property string $status
 * @property string $ai_source_default
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUSPENDED = 'suspended';

    /** Real columns on the tenants table; everything else goes into the `data` JSON column. */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'currency', 'locale', 'plan_id', 'status', 'ai_source_default'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
