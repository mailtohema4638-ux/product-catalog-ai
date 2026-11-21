<?php

namespace App\Providers;

use App\Contracts\ProductProviderInterface;
use App\DTOs\CatalogueSettings;
use App\Infrastructure\Products\CachingProductProvider;
use App\Infrastructure\Products\FakeStoreMapper;
use App\Infrastructure\Products\FakeStoreProvider;
use App\Infrastructure\Products\JsonCatalogueProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Composition root for the product side. To add a new product source, implement
 * ProductProviderInterface and add a case to the match below.
 */
class CatalogueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CatalogueSettings::class, fn (): CatalogueSettings => new CatalogueSettings(
            featuredProductIds: config('catalogue.featured_product_ids'),
            categoryTopRatedCount: config('catalogue.category_top_rated_count'),
        ));

        $this->app->singleton(ProductProviderInterface::class, function ($app): ProductProviderInterface {
            $name = (string) config('catalogue.provider');

            // A file we own, read in-process: nothing to cache, and edits to the file show up immediately.
            if ($name === 'local') {
                return new JsonCatalogueProvider((string) config('catalogue.local.path'));
            }

            $source = match ($name) {
                'fakestore' => new FakeStoreProvider(
                    http: $app->make(HttpFactory::class),
                    mapper: $app->make(FakeStoreMapper::class),
                    logger: $app->make(LoggerInterface::class),
                    baseUrl: config('catalogue.fakestore.base_url'),
                    timeoutSeconds: config('catalogue.fakestore.timeout_seconds'),
                    retries: config('catalogue.fakestore.retries'),
                    retrySleepMs: config('catalogue.fakestore.retry_sleep_ms'),
                ),
                default => throw new InvalidArgumentException("Unknown catalogue provider [{$name}]."),
            };

            return new CachingProductProvider(
                inner: $source,
                cache: $app->make(Cache::class),
                logger: $app->make(LoggerInterface::class),
                namespace: $name,
                ttlSeconds: config('catalogue.cache.ttl_seconds'),
                staleTtlSeconds: config('catalogue.cache.stale_ttl_seconds'),
            );
        });
    }
}
