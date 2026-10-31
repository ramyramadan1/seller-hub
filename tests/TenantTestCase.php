<?php

namespace Tests;

use App\Models\Tenant;

/**
 * Base class for multi-database tests. Needs a MySQL/PostgreSQL user with CREATE DATABASE.
 * The central schema is rebuilt for every test; tenant databases are dropped in tearDown.
 */
abstract class TenantTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--seed' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        // Deleting a tenant drops its database (TenantDeleted -> DeleteDatabase).
        Tenant::query()->get()->each->delete();

        parent::tearDown();
    }

    /** @return array{token: string, tenant_id: string, email: string} */
    protected function registerStore(string $storeName, string $email, array $overrides = []): array
    {
        $response = $this->postJson('/api/register', array_merge([
            'store_name' => $storeName,
            'currency' => 'SAR',
            'locale' => 'en',
            'name' => 'Owner of '.$storeName,
            'email' => $email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides))->assertCreated();

        return [
            'token' => $response->json('token'),
            'tenant_id' => $response->json('tenant.id'),
            'email' => $email,
        ];
    }

    /** Switch the bearer token between requests inside one test (resets Sanctum's cached guard user). */
    protected function actingWithToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function databaseExists(Tenant $tenant): bool
    {
        return $tenant->database()->manager()->databaseExists($tenant->database()->getName());
    }
}
