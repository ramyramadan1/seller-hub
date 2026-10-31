<?php

namespace Tests\Feature;

use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Tests\TenantTestCase;

class ProvisioningTest extends TenantTestCase
{
    public function test_provisioning_is_idempotent(): void
    {
        $store = $this->registerStore('Twice Shop', 'twice@example.com');
        $tenant = Tenant::findOrFail($store['tenant_id']);

        // Already active: a second run is a no-op.
        ProvisionTenant::dispatchSync($tenant);
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);

        // Database exists but the store was left "provisioning" (e.g. a worker crashed): resume safely.
        $tenant->forceFill(['status' => Tenant::STATUS_PROVISIONING])->save();
        ProvisionTenant::dispatchSync($tenant->fresh());

        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
        $this->assertTrue($this->databaseExists($tenant));
    }

    public function test_a_failure_drops_the_half_built_database_and_can_be_retried(): void
    {
        // Break provisioning right after the migrations ran.
        Event::listen(DatabaseMigrated::class, fn () => throw new RuntimeException('boom'));

        $store = $this->registerStore('Broken Shop', 'broken@example.com');
        $tenant = Tenant::findOrFail($store['tenant_id']);

        $this->assertSame(Tenant::STATUS_FAILED, $tenant->status);
        $this->assertFalse($this->databaseExists($tenant), 'the half-built database must be dropped');

        // Fix the cause and retry from the API (owner only).
        Event::forget(DatabaseMigrated::class);

        $this->actingWithToken($store['token'])->postJson('/api/tenant/retry')->assertOk();

        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
        $this->assertTrue($this->databaseExists($tenant));
    }

    public function test_retry_is_refused_for_a_store_that_is_not_failed(): void
    {
        $store = $this->registerStore('Fine Shop', 'fine@example.com');

        $this->actingWithToken($store['token'])->postJson('/api/tenant/retry')->assertStatus(409);
    }
}
