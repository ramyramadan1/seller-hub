<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => 'free'],
            ['name' => 'Free', 'daily_ai_limit' => 5, 'max_channels' => 2],
        );
    }
}
