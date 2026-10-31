<?php

namespace App\Channels;

use App\Channels\DTO\OrderDTO;
use App\Models\Channel;
use App\Models\Product;
use Carbon\Carbon;

/** One implementation per platform. Controllers and jobs never call platform APIs directly. */
interface ChannelDriver
{
    /** OAuth platforms return the authorize URL; key/secret platforms return null. */
    public function authorizeUrl(Channel $channel): ?string;

    /** OAuth platforms store tokens here. */
    public function handleCallback(Channel $channel, array $query): void;

    /** @throws ChannelException with a user-safe message when the connection does not work */
    public function testConnection(Channel $channel): bool;

    /** @return string the product's external id on the platform */
    public function pushProduct(Channel $channel, Product $product): string;

    public function updateProduct(Channel $channel, Product $product, ?string $externalId = null): void;

    /** @return iterable<OrderDTO> */
    public function fetchOrders(Channel $channel, ?Carbon $since): iterable;

    public function normalizeOrder(array $raw): OrderDTO;
}
