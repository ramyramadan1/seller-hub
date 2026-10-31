<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** CENTRAL seeder (php artisan migrate --seed). Tenant databases use TenantDatabaseSeeder. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);
    }
}
