<?php

namespace Tests\Support;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Writes the tenant id it runs under into that tenant's own database. */
class RecordTenantProbe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        Setting::query()->updateOrCreate(['key' => 'probe'], ['value' => tenant('id')]);
    }
}
