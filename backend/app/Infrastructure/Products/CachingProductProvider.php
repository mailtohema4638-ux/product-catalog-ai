<?php

namespace App\Infrastructure\Products;

use App\Contracts\ProductProviderInterface;
use App\DTOs\Category;
use App\DTOs\Product;
use App\Exceptions\ProviderUnavailableException;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

/**
 * Caches any ProductProviderInterface with stale-if-error semantics:
 * fresh data for $ttl seconds, and a long-lived copy served only when the source fails.
 *
 * Values are stored as plain arrays, never objects: `cache.serializable_classes` is disabled,
 * so cached DTOs would come back as __PHP_Incomplete_Class.
 */
class CachingProductProvider implements ProductProviderInterface
{
    /** After a failed refresh, keep serving the stale copy this long before trying the source again. */
    private const RETRY_AFTER_FAILURE_SECONDS = 30;

    public function __construct(
        private readonly ProductProviderInterface $inner,
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
        private readonly string $namespace,
        private readonly int $ttlSeconds,
        private readonly int $staleTtlSeconds,
    ) {}

    public function all(): array
    {
        return $this->products('products', fn (): array => $this->inner->all());
    }

    public function find(string $id): Product
    {
        return Product::fromArray(
            $this->remember('product:'.$id, fn (): array => $this->inner->find($id)->toArray()),
        );
    }

    public function byCategory(string $categorySlug): array
    {
        return $this->products('category:'.$categorySlug, fn (): array => $this->inner->byCategory($categorySlug));
    }

    public function categories(): array
    {
        $rows = $this->remember('categories', fn (): array => array_map(
            fn (Category $category): array => $category->toArray(),
            $this->inner->categories(),
        ));

        return array_map(Category::fromArray(...), $rows);
    }

    /**
     * @param  Closure(): list<Product>  $load
     * @return list<Product>
     */
    private function products(string $key, Closure $load): array
    {
        $rows = $this->remember($key, fn (): array => array_map(
            fn (Product $product): array => $product->toArray(),
            $load(),
        ));

        return array_map(Product::fromArray(...), $rows);
    }

    /**
     * @param  Closure(): array<mixed>  $load
     * @return array<mixed>
     */
    private function remember(string $key, Closure $load): array
    {
        $fresh = "catalogue:v1:{$this->namespace}:{$key}";
        $stale = "{$fresh}:stale";

        $hit = $this->cache->get($fresh);
        if ($hit !== null) {
            return $hit;
        }

        $refresh = function () use ($fresh, $stale, $load): array {
            // Another request may have refreshed the value while we waited for the lock.
            $hit = $this->cache->get($fresh);
            if ($hit !== null) {
                return $hit;
            }

            try {
                $value = $load();
            } catch (ProviderUnavailableException $e) {
                $copy = $this->cache->get($stale);
                if ($copy === null) {
                    throw $e;
                }

                $this->logger->warning('Serving stale catalogue data: product source unavailable.', [
                    'key' => $fresh,
                    'reason' => $e->getMessage(),
                ]);
                $this->cache->put($fresh, $copy, self::RETRY_AFTER_FAILURE_SECONDS);

                return $copy;
            }

            $this->cache->put($fresh, $value, $this->ttlSeconds);
            $this->cache->put($stale, $value, $this->staleTtlSeconds);

            return $value;
        };

        $store = $this->cache->getStore();
        if (! $store instanceof LockProvider) {
            return $refresh();
        }

        // The lock stops a stampede of identical upstream calls on a cold or expired key.
        try {
            return $store->lock("{$fresh}:lock", 10)->block(5, $refresh);
        } catch (LockTimeoutException) {
            return $refresh();
        }
    }
}
