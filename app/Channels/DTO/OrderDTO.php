<?php

namespace App\Channels\DTO;

use Carbon\CarbonInterface;

/** Normalized order shape every driver returns (used from phase 4). */
final class OrderDTO
{
    /**
     * @param  string  $status  one of: new, paid, shipped, delivered, cancelled, refunded
     * @param  array<int, array{sku: ?string, title: string, qty: int, unit_price: float}>  $items
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
        public readonly string $currency,
        public readonly float $subtotal,
        public readonly float $shipping,
        public readonly float $discount,
        public readonly float $tax,
        public readonly float $total,
        public readonly CarbonInterface $placedAt,
        public readonly array $items,
        public readonly array $customer = [],
        public readonly array $raw = [],
    ) {
    }
}
