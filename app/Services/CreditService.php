<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Credits are CENTRAL data. Every balance change writes a ledger row in the same transaction,
 * under a row lock on the wallet. (reserve/charge/refund arrive with the AI phase.)
 */
class CreditService
{
    public function balance(Tenant|string $tenant): int
    {
        $id = $tenant instanceof Tenant ? $tenant->getTenantKey() : $tenant;

        return (int) (CreditWallet::query()->whereKey($id)->value('balance') ?? 0);
    }

    public function grantBonus(Tenant $tenant, int $amount, string $note = 'Free trial credits'): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($tenant, $amount, $note) {
            CreditWallet::query()->firstOrCreate(['tenant_id' => $tenant->getTenantKey()], ['balance' => 0]);

            $wallet = CreditWallet::query()->whereKey($tenant->getTenantKey())->lockForUpdate()->firstOrFail();
            $wallet->balance += $amount;
            $wallet->save();

            CreditTransaction::query()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'kind' => 'bonus',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'note' => $note,
            ]);
        });
    }
}
