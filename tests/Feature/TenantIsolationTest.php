<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RecordTenantProbe;
use Tests\TenantTestCase;

/** Phase 1 acceptance tests: two stores must never see each other's data. */
class TenantIsolationTest extends TenantTestCase
{
    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->registerStore('Store A', 'a@example.com');
        $this->b = $this->registerStore('Store B', 'b@example.com');
    }

    private function createProduct(string $token, string $title): void
    {
        $this->actingWithToken($token)->postJson('/api/products', ['title' => $title, 'price' => 10])->assertCreated();
    }

    private function productTitles(string $token, array $headers = [], string $query = ''): array
    {
        return collect($this->actingWithToken($token)->withHeaders($headers)->getJson('/api/products'.$query)->assertOk()->json('data'))
            ->pluck('title')->all();
    }

    public function test_each_store_gets_a_separate_database(): void
    {
        $tenantA = Tenant::findOrFail($this->a['tenant_id']);
        $tenantB = Tenant::findOrFail($this->b['tenant_id']);

        $this->assertNotSame($tenantA->database()->getName(), $tenantB->database()->getName());
        $this->assertTrue($this->databaseExists($tenantA));
        $this->assertTrue($this->databaseExists($tenantB));
    }

    public function test_a_product_created_in_store_a_is_absent_in_store_b(): void
    {
        $this->createProduct($this->a['token'], 'Only in A');

        $this->assertSame(['Only in A'], $this->productTitles($this->a['token']));
        $this->assertSame([], $this->productTitles($this->b['token']));
    }

    public function test_the_tenant_cannot_be_chosen_from_headers_query_or_body(): void
    {
        $this->createProduct($this->a['token'], 'Only in A');
        $this->createProduct($this->b['token'], 'Only in B');

        // Store A's token, pointing at store B in every way a client could try.
        $titles = $this->productTitles(
            $this->a['token'],
            ['X-Tenant' => $this->b['tenant_id'], 'X-Tenant-ID' => $this->b['tenant_id']],
            '?tenant_id='.$this->b['tenant_id'].'&tenant='.$this->b['tenant_id'],
        );

        $this->assertSame(['Only in A'], $titles);
    }

    public function test_requests_without_a_valid_token_are_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/products')->assertUnauthorized();
        $this->withToken('not-a-real-token')->getJson('/api/products')->assertUnauthorized();
    }

    public function test_store_tables_and_central_tables_are_not_mixed(): void
    {
        $central = config('tenancy.database.central_connection');
        $this->assertFalse(Schema::connection($central)->hasTable('products'), 'products must not exist in the central DB');

        $tenantA = Tenant::findOrFail($this->a['tenant_id']);
        $this->assertFalse($tenantA->run(fn () => Schema::hasTable('users')), 'users must not exist in a store DB');
        $this->assertFalse($tenantA->run(fn () => Schema::hasTable('personal_access_tokens')));
        $this->assertTrue($tenantA->run(fn () => Schema::hasTable('products')));
    }

    public function test_tenancy_is_ended_after_a_tenant_request(): void
    {
        $this->createProduct($this->a['token'], 'Only in A');

        $this->assertFalse(tenancy()->initialized);
        $this->assertSame(config('tenancy.database.central_connection'), config('database.default'));
    }

    public function test_a_queued_job_runs_inside_the_store_that_dispatched_it(): void
    {
        config(['queue.default' => 'database']);

        $tenantA = Tenant::findOrFail($this->a['tenant_id']);
        $tenantB = Tenant::findOrFail($this->b['tenant_id']);

        tenancy()->initialize($tenantA);
        RecordTenantProbe::dispatch();
        tenancy()->end();

        // The queue table is central, not inside the store database.
        $this->assertSame(1, DB::connection(config('tenancy.database.central_connection'))->table('jobs')->count());

        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertSame($tenantA->getTenantKey(), $tenantA->run(fn () => Setting::where('key', 'probe')->value('value')));
        $this->assertNull($tenantB->run(fn () => Setting::where('key', 'probe')->value('value')));
    }
}
