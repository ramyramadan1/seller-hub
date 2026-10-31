<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditService;
use Tests\TenantTestCase;

class RegistrationTest extends TenantTestCase
{
    public function test_registering_creates_an_active_store_with_its_own_database(): void
    {
        $store = $this->registerStore('Noor Fashion', 'noor@example.com', ['currency' => 'EGP', 'locale' => 'ar']);

        $tenant = Tenant::findOrFail($store['tenant_id']);

        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertTrue($this->databaseExists($tenant));
        $this->assertStringStartsWith('tenant_', $tenant->database()->getName());
        $this->assertSame('owner', User::where('email', 'noor@example.com')->value('role'));

        // Default settings were seeded INSIDE the store database.
        $currency = $tenant->run(fn () => Setting::where('key', 'currency')->value('value'));
        $this->assertSame('EGP', $currency);
    }

    public function test_trial_credits_are_granted_in_the_central_ledger(): void
    {
        config(['seller.trial_credits' => 10]);

        $store = $this->registerStore('Credit Shop', 'credit@example.com');

        $this->assertSame(10, app(CreditService::class)->balance($store['tenant_id']));
        $this->assertDatabaseHas('credit_transactions', [
            'tenant_id' => $store['tenant_id'],
            'kind' => 'bonus',
            'amount' => 10,
            'balance_after' => 10,
        ]);
    }

    public function test_status_endpoint_reports_the_store_state(): void
    {
        $store = $this->registerStore('Status Shop', 'status@example.com');

        $this->actingWithToken($store['token'])
            ->getJson('/api/tenant/status')
            ->assertOk()
            ->assertJson(['status' => 'active', 'name' => 'Status Shop']);
    }

    public function test_duplicate_email_and_weak_password_are_rejected(): void
    {
        $this->registerStore('First', 'dup@example.com');

        $this->postJson('/api/register', [
            'store_name' => 'Second', 'currency' => 'SAR', 'locale' => 'en', 'name' => 'X',
            'email' => 'dup@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/register', [
            'store_name' => 'Third', 'currency' => 'SAR', 'locale' => 'en', 'name' => 'X',
            'email' => 'weak@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_returns_a_token_and_wrong_password_fails(): void
    {
        $this->registerStore('Login Shop', 'login@example.com');

        $this->postJson('/api/login', ['email' => 'login@example.com', 'password' => 'wrong'])
            ->assertStatus(422);

        $this->postJson('/api/login', ['email' => 'login@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user', 'tenant']);
    }

    public function test_tenant_routes_are_blocked_until_the_store_is_active(): void
    {
        $store = $this->registerStore('Slow Shop', 'slow@example.com');

        Tenant::findOrFail($store['tenant_id'])->forceFill(['status' => Tenant::STATUS_PROVISIONING])->save();

        $this->actingWithToken($store['token'])
            ->getJson('/api/products')
            ->assertStatus(409)
            ->assertJson(['status' => 'provisioning']);
    }
}
