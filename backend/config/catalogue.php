<?php

/** Comma-separated env value to a clean list of strings. */
$list = fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $v): bool => $v !== ''));

return [
    // Which ProductProviderInterface implementation to use: "local" (our own catalogue file) or
    // "fakestore" (the public Fake Store API). See CatalogueServiceProvider.
    'provider' => env('CATALOGUE_PROVIDER', 'local'),

    'local' => [
        'path' => env('CATALOGUE_FILE', resource_path('catalogue/products.json')),
    ],

    // Curated, ordered source product IDs shown as "top rated" on the home page.
    'featured_product_ids' => $list((string) env('FEATURED_PRODUCT_IDS', '')),

    // How many top-rated products lead each category page.
    'category_top_rated_count' => (int) env('CATEGORY_TOP_RATED_COUNT', 3),

    'cache' => [
        'ttl_seconds' => (int) env('CATALOGUE_CACHE_TTL_SECONDS', 600),
        // Stale copy served only while the source is failing.
        'stale_ttl_seconds' => (int) env('CATALOGUE_STALE_TTL_SECONDS', 86400),
    ],

    'fakestore' => [
        'base_url' => env('FAKESTORE_BASE_URL', 'https://fakestoreapi.com'),
        'timeout_seconds' => (int) env('FAKESTORE_TIMEOUT_SECONDS', 4),
        'retries' => (int) env('FAKESTORE_RETRIES', 1),
        'retry_sleep_ms' => (int) env('FAKESTORE_RETRY_SLEEP_MS', 200),
    ],
];
