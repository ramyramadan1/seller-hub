<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Runs INSIDE a store's database (tenants:seed). Upserts, so provisioning can be retried safely. */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'currency' => tenant('currency'),
            'locale' => tenant('locale'),
            'tax_inclusive_prices' => false,
            'default_channel_fee_percent' => 0,
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
