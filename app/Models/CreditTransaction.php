<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Append-only ledger. Never update or delete rows. */
class CreditTransaction extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'kind', 'amount', 'balance_after', 'ai_job_ref', 'payment_ref', 'note'];
    protected $casts = ['amount' => 'integer', 'balance_after' => 'integer'];
}
