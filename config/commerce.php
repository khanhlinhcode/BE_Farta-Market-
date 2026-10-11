<?php

return [
    'reservations' => [
        'max_pending_orders_per_user' => (int) env('COMMERCE_MAX_PENDING_ORDERS_PER_USER', 3),
        'max_reserved_units_per_user' => (int) env('COMMERCE_MAX_RESERVED_UNITS_PER_USER', 20),
        'max_reserved_value_per_user' => (int) env('COMMERCE_MAX_RESERVED_VALUE_PER_USER', 5_000_000),
        'max_unconfirmed_per_sku_percent' => (int) env('COMMERCE_MAX_UNCONFIRMED_PER_SKU_PERCENT', 50),
        'low_stock_threshold' => (int) env('COMMERCE_LOW_STOCK_THRESHOLD', 5),
        'low_stock_units_per_identity' => (int) env('COMMERCE_LOW_STOCK_UNITS_PER_IDENTITY', 1),
        'authenticated_cod_ttl_minutes' => (int) env('COMMERCE_AUTH_COD_TTL_MINUTES', 120),
        'guest_cod_ttl_minutes' => (int) env('COMMERCE_GUEST_COD_TTL_MINUTES', 60),
        'sepay_ttl_minutes' => (int) env('COMMERCE_SEPAY_TTL_MINUTES', 30),
        'authenticated_checkout_per_minute' => (int) env('COMMERCE_AUTH_CHECKOUT_PER_MINUTE', 10),
    ],
];
