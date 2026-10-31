<?php

namespace App\Channels\Drivers;

use App\Channels\ChannelDriver;
use App\Channels\ChannelException;
use App\Channels\DTO\OrderDTO;
use App\Channels\UrlGuard;
use App\Models\Channel;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * WooCommerce REST API v3. The store owner pastes the store URL plus a consumer key/secret
 * (HTTP Basic over HTTPS, per the WooCommerce REST authentication docs).
 */
class WooCommerceDriver implements ChannelDriver
{
    public function authorizeUrl(Channel $channel): ?string
    {
        return null; // key/secret platform
    }

    public function handleCallback(Channel $channel, array $query): void
    {
        // Not an OAuth platform.
    }

    public function testConnection(Channel $channel): bool
    {
        try {
            $response = $this->client($channel)->get('products', ['per_page' => 1]);
        } catch (ConnectionException) {
            throw new ChannelException('Could not reach the store. Check the address and that the site is online.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new ChannelException('WooCommerce rejected the keys. Check the consumer key and secret have Read/Write access.', needsReauth: true);
        }

        if ($response->status() === 404) {
            throw new ChannelException('The WooCommerce REST API was not found at that address. Check the URL and that pretty permalinks are enabled.');
        }

        if (! $response->successful()) {
            throw new ChannelException("WooCommerce answered with an error (HTTP {$response->status()}).");
        }

        return true;
    }

    public function pushProduct(Channel $channel, Product $product): string
    {
        throw new ChannelException('Publishing products to WooCommerce is added in phase 3.');
    }

    public function updateProduct(Channel $channel, Product $product): void
    {
        throw new ChannelException('Updating products on WooCommerce is added in phase 3.');
    }

    public function fetchOrders(Channel $channel, ?Carbon $since): iterable
    {
        throw new ChannelException('Order sync for WooCommerce is added in phase 4.');
    }

    public function normalizeOrder(array $raw): OrderDTO
    {
        throw new ChannelException('Order sync for WooCommerce is added in phase 4.');
    }

    private function client(Channel $channel): PendingRequest
    {
        $c = $channel->credentials ?? [];

        if (empty($c['store_url']) || empty($c['consumer_key']) || empty($c['consumer_secret'])) {
            throw new ChannelException('This channel is missing its store address or keys.');
        }

        $base = UrlGuard::normalize($c['store_url']);

        return Http::baseUrl($base.'/wp-json/wc/v3/')
            ->withBasicAuth($c['consumer_key'], $c['consumer_secret'])
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
