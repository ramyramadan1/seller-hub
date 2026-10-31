<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\DeleteTenantDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;

class TenancyServiceProvider extends ServiceProvider
{
    public function events(): array
    {
        return [
            // Provisioning is NOT automatic on TenantCreated: App\Jobs\ProvisionTenant runs the steps explicitly
            // (create DB, migrate, seed, mark active) so it can be idempotent, retried and cleaned up on failure.
            Events\TenantCreated::class => [],

            Events\TenantDeleted::class => [
                DeleteTenantDatabase::class,
            ],

            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
        ];
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
