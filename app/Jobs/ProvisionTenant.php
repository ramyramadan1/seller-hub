<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Throwable;

/**
 * Builds a store's database: create DB -> migrate -> seed defaults -> mark active.
 *
 * Idempotent: an existing database is reused (migrations skip what already ran, the seeder upserts),
 * and an already-active tenant is left alone. On failure the half-built database is dropped and the
 * tenant is marked "failed" so the owner can retry from the UI.
 */
class ProvisionTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(public Tenant $tenant)
    {
    }

    public function handle(DatabaseManager $databaseManager): void
    {
        $tenant = $this->tenant->fresh();

        if (! $tenant || $tenant->isActive()) {
            return;
        }

        $tenant->status = Tenant::STATUS_PROVISIONING;
        $tenant->save();

        try {
            if (! $this->databaseExists($tenant)) {
                (new CreateDatabase($tenant))->handle($databaseManager);
            }

            $this->runTenantCommand('tenants:migrate', $tenant);
            $this->runTenantCommand('tenants:seed', $tenant);

            $tenant->status = Tenant::STATUS_ACTIVE;
            $tenant->save();
        } catch (Throwable $e) {
            $this->markFailed($tenant);

            throw $e;
        }
    }

    /** Also called by the queue for timeouts and killed workers. */
    public function failed(Throwable $e): void
    {
        if ($tenant = $this->tenant->fresh()) {
            $this->markFailed($tenant);
        }
    }

    private function databaseExists(Tenant $tenant): bool
    {
        return $tenant->database()->manager()->databaseExists($tenant->database()->getName());
    }

    private function runTenantCommand(string $command, Tenant $tenant): void
    {
        // Artisan::call returns an exit code instead of throwing in some failure modes: never ignore it.
        $exit = Artisan::call($command, ['--tenants' => [$tenant->getTenantKey()]]);

        if ($exit !== 0) {
            throw new RuntimeException("{$command} failed for tenant {$tenant->getTenantKey()}: ".trim(Artisan::output()));
        }
    }

    private function markFailed(Tenant $tenant): void
    {
        try {
            if ($this->databaseExists($tenant)) {
                (new DeleteDatabase($tenant))->handle();
            }
        } catch (Throwable $cleanup) {
            report($cleanup);
        }

        $tenant->status = Tenant::STATUS_FAILED;
        $tenant->save();
    }
}
