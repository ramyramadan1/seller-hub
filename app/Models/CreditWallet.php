<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CreditWallet extends Model
{
    use CentralConnection;

    public const CREATED_AT = null;

    protected $primaryKey = 'tenant_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['tenant_id', 'balance'];
    protected $casts = ['balance' => 'integer'];
}
