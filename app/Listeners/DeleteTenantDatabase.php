<?php

namespace App\Listeners;

use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Jobs\DeleteDatabase;

/**
 * Drops a store's database when the tenant row is deleted.
 * stancl's own pipeline runs a plain DROP DATABASE, which errors when the database is already gone
 * (for example a store whose provisioning failed), so check first.
 */
class DeleteTenantDatabase
{
    public function handle(TenantDeleted $event): void
    {
        $tenant = $event->tenant;

        if ($tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
            (new DeleteDatabase($tenant))->handle();
        }
    }
}
