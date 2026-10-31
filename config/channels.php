<?php

use App\Channels\Drivers\WooCommerceDriver;

return [
    // driver name => class. Shopify, Salla, Zid and Amazon are added in phase 7.
    'drivers' => [
        'woocommerce' => WooCommerceDriver::class,
    ],
];
