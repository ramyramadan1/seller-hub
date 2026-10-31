<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/** Phase 3: product CRUD, publishing to channels (queue is `sync` in phpunit.xml), per-channel status. */
class ProductPublishTest extends TenantTestCase
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

    /**
     * Fake WooCommerce: GET (connection test) -> 200; POST products -> next queued [status, body] (default 201 id 1);
     * PUT -> 200. A callback is used because URL patterns would not match the "?per_page=1" query string.
     */
    private function fakeWoo(array $posts = []): void
    {
        Http::fake(function ($request) use (&$posts) {
            if ($request->method() === 'GET') {
                return Http::response([], 200);
            }
            if ($request->method() === 'POST') {
                [$status, $body] = array_shift($posts) ?? [201, ['id' => 1]];

                return Http::response($body, $status);
            }

            return Http::response(['id' => 777], 200);
        });
    }

    private function api(string $token)
    {
        return $this->actingWithToken($token);
    }

    private function product(string $token, array $o = []): int
    {
        return $this->api($token)->postJson('/api/products', $o + ['title' => 'Mug', 'sku' => 'MUG-1', 'price' => 25, 'stock' => 4, 'description' => 'A mug'])
            ->assertCreated()->json('id');
    }

    private function connectWoo(string $token, string $host = 'shop.example.com'): int
    {
        return $this->api($token)->postJson('/api/channels', [
            'driver' => 'woocommerce', 'name' => $host, 'store_url' => "https://{$host}",
            'consumer_key' => 'ck_x', 'consumer_secret' => 'cs_x',
        ])->assertCreated()->json('id');
    }

    public function test_product_crud_update_and_delete(): void
    {
        $id = $this->product($this->a['token']);

        $this->api($this->a['token'])->putJson("/api/products/{$id}", ['title' => 'Big mug', 'price' => 30, 'sku' => 'MUG-1'])
            ->assertOk()->assertJsonPath('title', 'Big mug');
        $this->api($this->a['token'])->getJson("/api/products/{$id}")->assertOk()->assertJsonPath('price', '30.00');

        // SKU stays unique, but a product may keep its own SKU.
        $other = $this->product($this->a['token'], ['title' => 'Cup', 'sku' => 'CUP-1']);
        $this->api($this->a['token'])->putJson("/api/products/{$other}", ['title' => 'Cup', 'price' => 5, 'sku' => 'MUG-1'])
            ->assertStatus(422)->assertJsonValidationErrors('sku');

        $this->api($this->a['token'])->deleteJson("/api/products/{$id}")->assertOk();
        $this->api($this->a['token'])->getJson("/api/products/{$id}")->assertNotFound();
    }

    public function test_a_store_cannot_read_change_or_delete_another_stores_product(): void
    {
        $id = $this->product($this->a['token']);

        $this->api($this->b['token'])->getJson("/api/products/{$id}")->assertNotFound();
        $this->api($this->b['token'])->putJson("/api/products/{$id}", ['title' => 'x', 'price' => 1])->assertNotFound();
        $this->api($this->b['token'])->deleteJson("/api/products/{$id}")->assertNotFound();
    }

    public function test_publish_creates_the_product_on_woocommerce_and_records_synced(): void
    {
        $this->fakeWoo([[201, ['id' => 777]]]);

        $channel = $this->connectWoo($this->a['token']);
        $id = $this->product($this->a['token']);

        $this->api($this->a['token'])->postJson('/api/products/publish', ['product_ids' => [$id], 'channel_ids' => [$channel]])
            ->assertStatus(202)->assertJsonPath('queued', 1);

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && $r->url() === 'https://shop.example.com/wp-json/wc/v3/products'
            && $r['name'] === 'Mug' && $r['regular_price'] === '25.00' && $r['sku'] === 'MUG-1' && $r['stock_quantity'] === 4);

        $row = $this->api($this->a['token'])->getJson("/api/products/{$id}")->assertOk()->json('channels.0');
        $this->assertSame('synced', $row['sync_status']);
        $this->assertSame($channel, $row['channel_id']);

        $tenant = Tenant::findOrFail($this->a['tenant_id']);
        $this->assertSame('777', $tenant->run(fn () => DB::table('product_channel')->value('external_id')));
    }

    public function test_republishing_updates_the_existing_woocommerce_product(): void
    {
        $this->fakeWoo([[201, ['id' => 777]]]);

        $channel = $this->connectWoo($this->a['token']);
        $id = $this->product($this->a['token']);
        $body = ['product_ids' => [$id], 'channel_ids' => [$channel]];

        $this->api($this->a['token'])->postJson('/api/products/publish', $body)->assertStatus(202);
        $this->api($this->a['token'])->putJson("/api/products/{$id}", ['title' => 'Mug v2', 'price' => 26, 'sku' => 'MUG-1'])->assertOk();
        $this->api($this->a['token'])->postJson('/api/products/publish', $body)->assertStatus(202);

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->url() === 'https://shop.example.com/wp-json/wc/v3/products/777' && $r['name'] === 'Mug v2');
        $this->assertSame('synced', $this->api($this->a['token'])->getJson("/api/products/{$id}")->json('channels.0.sync_status'));
    }

    public function test_a_platform_refusal_marks_the_link_failed_with_a_readable_error(): void
    {
        $this->fakeWoo([[400, ['code' => 'product_invalid_sku', 'message' => 'Invalid or duplicated SKU.']]]);

        $channel = $this->connectWoo($this->a['token']);
        $id = $this->product($this->a['token']);

        $this->api($this->a['token'])->postJson('/api/products/publish', ['product_ids' => [$id], 'channel_ids' => [$channel]])->assertStatus(202);

        $row = $this->api($this->a['token'])->getJson("/api/products/{$id}")->json('channels.0');
        $this->assertSame('failed', $row['sync_status']);
        $this->assertStringContainsString('Invalid or duplicated SKU', $row['last_error']);
    }

    public function test_rejected_keys_during_publish_flag_the_channel_for_reauth(): void
    {
        $this->fakeWoo([[401, []]]);

        $channel = $this->connectWoo($this->a['token']);
        $id = $this->product($this->a['token']);

        $this->api($this->a['token'])->postJson('/api/products/publish', ['product_ids' => [$id], 'channel_ids' => [$channel]])->assertStatus(202);

        $this->assertSame('failed', $this->api($this->a['token'])->getJson("/api/products/{$id}")->json('channels.0.sync_status'));
        $status = collect($this->api($this->a['token'])->getJson('/api/channels')->json('data'))->firstWhere('id', $channel)['status'];
        $this->assertSame('needs_reauth', $status);

        // A channel that needs re-authorization cannot be published to until it is fixed.
        $this->api($this->a['token'])->postJson('/api/products/publish', ['product_ids' => [$id], 'channel_ids' => [$channel]])->assertStatus(422);
    }

    public function test_publish_rejects_ids_that_do_not_exist_in_this_store(): void
    {
        $this->fakeWoo();

        $channelB = $this->connectWoo($this->b['token']);   // store B's first channel (id 1 in B's database)
        $productA = $this->product($this->a['token']);       // store A has products but no channels

        // Store A has no channel with that id, so the request must fail validation, never touch B's channel.
        $this->api($this->a['token'])->postJson('/api/products/publish', ['product_ids' => [$productA], 'channel_ids' => [$channelB]])
            ->assertStatus(422)->assertJsonValidationErrors('channel_ids.0');
        $this->api($this->a['token'])->postJson('/api/products/publish', ['product_ids' => [9999], 'channel_ids' => [$channelB]])
            ->assertStatus(422);
    }

    public function test_deleting_a_product_removes_its_channel_links(): void
    {
        $this->fakeWoo([[201, ['id' => 5]]]);

        $channel = $this->connectWoo($this->a['token']);
        $id = $this->product($this->a['token']);
        $this->api($this->a['token'])->postJson('/api/products/publish', ['product_ids' => [$id], 'channel_ids' => [$channel]])->assertStatus(202);
        $this->api($this->a['token'])->deleteJson("/api/products/{$id}")->assertOk();

        $tenant = Tenant::findOrFail($this->a['tenant_id']);
        $this->assertSame(0, $tenant->run(fn () => DB::table('product_channel')->count()));
    }
}
