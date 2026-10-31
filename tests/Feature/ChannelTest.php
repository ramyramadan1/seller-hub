<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelRoute;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/** Phase 2: channels + WooCommerce connection, with all platform HTTP faked. */
class ChannelTest extends TenantTestCase
{
    private const WOO = 'https://shop.example.com/wp-json/wc/v3/*';

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->registerStore('Store A', 'a@example.com');
        $this->b = $this->registerStore('Store B', 'b@example.com');
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'driver' => 'woocommerce',
            'name' => 'My Woo shop',
            'store_url' => 'https://shop.example.com/',
            'consumer_key' => 'ck_test_key',
            'consumer_secret' => 'cs_test_secret',
        ], $override);
    }

    private function connect(string $token, array $override = [])
    {
        return $this->actingWithToken($token)->postJson('/api/channels', $this->payload($override));
    }

    public function test_owner_can_connect_a_woocommerce_store(): void
    {
        Http::fake([self::WOO => Http::response([], 200)]);

        $this->connect($this->a['token'])
            ->assertCreated()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('store_url', 'https://shop.example.com');

        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://shop.example.com/wp-json/wc/v3/products')
            && $r->hasHeader('Authorization'));
    }

    public function test_credentials_are_never_returned_and_are_encrypted_at_rest(): void
    {
        Http::fake([self::WOO => Http::response([], 200)]);

        $created = $this->connect($this->a['token'])->assertCreated();
        $list = $this->actingWithToken($this->a['token'])->getJson('/api/channels')->assertOk();

        foreach ([$created->getContent(), $list->getContent()] as $body) {
            $this->assertStringNotContainsString('ck_test_key', $body);
            $this->assertStringNotContainsString('cs_test_secret', $body);
            $this->assertStringNotContainsString('credentials', $body);
        }

        $tenant = Tenant::findOrFail($this->a['tenant_id']);
        $raw = $tenant->run(fn () => DB::table('channels')->value('credentials'));
        $this->assertStringNotContainsString('ck_test_key', $raw);
        $this->assertStringNotContainsString('cs_test_secret', $raw);
    }

    public function test_a_wrong_key_is_rejected_and_nothing_is_saved(): void
    {
        Http::fake([self::WOO => Http::response(['code' => 'woocommerce_rest_cannot_view'], 401)]);

        $this->connect($this->a['token'])->assertStatus(422)->assertJsonValidationErrors('credentials');

        $this->assertSame(0, ChannelRoute::count());
        $this->assertSame([], $this->actingWithToken($this->a['token'])->getJson('/api/channels')->json('data'));
    }

    public function test_non_https_and_private_addresses_are_rejected(): void
    {
        Http::fake();

        $this->connect($this->a['token'], ['store_url' => 'http://shop.example.com'])->assertStatus(422)->assertJsonValidationErrors('store_url');
        $this->connect($this->a['token'], ['store_url' => 'https://127.0.0.1'])->assertStatus(422)->assertJsonValidationErrors('store_url');
        $this->connect($this->a['token'], ['store_url' => 'https://169.254.169.254'])->assertStatus(422)->assertJsonValidationErrors('store_url');

        Http::assertNothingSent();
    }

    public function test_unknown_platform_is_rejected(): void
    {
        $this->connect($this->a['token'], ['driver' => 'ebay'])->assertStatus(422)->assertJsonValidationErrors('driver');
    }

    public function test_the_same_store_cannot_be_connected_twice(): void
    {
        Http::fake([self::WOO => Http::response([], 200)]);

        $this->connect($this->a['token'])->assertCreated();
        $this->connect($this->a['token'])->assertStatus(422)->assertJsonValidationErrors('store_url');
    }

    public function test_connecting_creates_a_central_route_and_disconnecting_removes_it(): void
    {
        Http::fake([self::WOO => Http::response([], 200)]);

        $id = $this->connect($this->a['token'])->assertCreated()->json('id');

        $route = ChannelRoute::where('channel_id', $id)->firstOrFail();
        $this->assertSame($this->a['tenant_id'], $route->tenant_id);
        $this->assertSame(48, strlen($route->getRawOriginal('webhook_token')));
        $this->assertSame(config('tenancy.database.central_connection'), $route->getConnectionName());

        $this->actingWithToken($this->a['token'])->deleteJson("/api/channels/{$id}")->assertOk();

        $this->assertSame(0, ChannelRoute::count());
        $this->assertSame([], $this->actingWithToken($this->a['token'])->getJson('/api/channels')->json('data'));
    }

    public function test_a_store_never_sees_or_touches_another_stores_channels(): void
    {
        Http::fake([self::WOO => Http::response([], 200)]);

        $id = $this->connect($this->a['token'])->assertCreated()->json('id');

        $this->assertSame([], $this->actingWithToken($this->b['token'])->getJson('/api/channels')->json('data'));
        $this->actingWithToken($this->b['token'])->postJson("/api/channels/{$id}/test")->assertNotFound();
        $this->actingWithToken($this->b['token'])->deleteJson("/api/channels/{$id}")->assertNotFound();

        $this->assertSame(1, ChannelRoute::count());
    }

    public function test_test_connection_updates_status_and_surfaces_needs_reauth(): void
    {
        // Http::fake() keeps the FIRST matching stub, so use one sequence: connect, then three re-tests.
        Http::fake([self::WOO => Http::sequence()
            ->push([], 200)->push([], 401)->push([], 200)->push([], 500)]);

        $id = $this->connect($this->a['token'])->assertCreated()->json('id');

        $this->actingWithToken($this->a['token'])->postJson("/api/channels/{$id}/test")
            ->assertOk()->assertJsonPath('status', 'needs_reauth');

        $this->actingWithToken($this->a['token'])->postJson("/api/channels/{$id}/test")
            ->assertOk()->assertJsonPath('status', 'connected')->assertJsonPath('last_error', null);

        $this->actingWithToken($this->a['token'])->postJson("/api/channels/{$id}/test")
            ->assertOk()->assertJsonPath('status', 'error');
    }

    public function test_staff_can_list_and_test_but_not_connect_or_disconnect(): void
    {
        Http::fake([self::WOO => Http::response([], 200)]);
        $id = $this->connect($this->a['token'])->assertCreated()->json('id');

        $staff = User::create([
            'tenant_id' => $this->a['tenant_id'], 'name' => 'Staff', 'email' => 'staff@example.com',
            'password' => 'secret-password', 'role' => User::ROLE_STAFF,
        ]);
        $token = $staff->createToken('t')->plainTextToken;

        $this->actingWithToken($token)->getJson('/api/channels')->assertOk();
        $this->actingWithToken($token)->postJson("/api/channels/{$id}/test")->assertOk();
        $this->actingWithToken($token)->postJson('/api/channels', $this->payload(['store_url' => 'https://other.example.com']))->assertForbidden();
        $this->actingWithToken($token)->deleteJson("/api/channels/{$id}")->assertForbidden();
    }

    public function test_channels_table_lives_only_in_store_databases(): void
    {
        $central = config('tenancy.database.central_connection');
        $this->assertFalse(\Illuminate\Support\Facades\Schema::connection($central)->hasTable('channels'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::connection($central)->hasTable('channel_routes'));

        $tenant = Tenant::findOrFail($this->a['tenant_id']);
        $this->assertTrue($tenant->run(fn () => \Illuminate\Support\Facades\Schema::hasTable('channels')));
        $this->assertFalse($tenant->run(fn () => \Illuminate\Support\Facades\Schema::hasTable('channel_routes')));
    }
}
