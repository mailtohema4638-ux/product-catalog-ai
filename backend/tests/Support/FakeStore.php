<?php

namespace Tests\Support;

use App\Infrastructure\Products\FakeStoreMapper;
use App\Infrastructure\Products\FakeStoreProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

/** A stand-in Fake Store server that reproduces the real API's shapes and quirks. */
final class FakeStore
{
    public const BASE_URL = 'https://fakestore.test';

    /** @return list<array<string, mixed>> */
    public static function items(): array
    {
        $item = fn (int $id, string $title, string $category, float|int $price, float $rate, int $count, string $description = 'A product.'): array => [
            'id' => $id,
            'title' => $title,
            'price' => $price,
            'description' => $description,
            'category' => $category,
            'image' => "https://images.test/{$id}.jpg",
            'rating' => ['rate' => $rate, 'count' => $count],
        ];

        return [
            $item(1, 'Foldsack Backpack', "men's clothing", 109.95, 3.9, 120),
            $item(2, 'Slim Fit T-Shirt', "men's clothing", 22.3, 4.1, 259),
            $item(3, 'Cotton Jacket', "men's clothing", 55.99, 4.7, 500),
            $item(4, 'Casual Slim Fit', "men's clothing", 15.99, 2.1, 430),
            $item(5, 'Dragon Bracelet', 'jewelery', 695, 4.6, 400, 'Gold and silver, hand made.'),
            $item(6, 'Petite Micropave', 'jewelery', 168, 3.9, 70, 'Solid GOLD ring.'),
            $item(7, '4TB Gaming Drive', 'electronics', 114, 4.8, 400),
        ];
    }

    /** @param list<array<string, mixed>>|null $items */
    public static function fake(?array $items = null): void
    {
        $items ??= self::items();

        Http::fake(function (Request $request) use ($items) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/products') {
                return Http::response($items);
            }

            if ($path === '/products/categories') {
                return Http::response(array_values(array_unique(array_column($items, 'category'))));
            }

            if (str_starts_with($path, '/products/category/')) {
                $name = rawurldecode(substr($path, strlen('/products/category/')));

                return Http::response(array_values(array_filter($items, fn (array $i): bool => $i['category'] === $name)));
            }

            if (preg_match('#^/products/([^/]+)$#', $path, $m) === 1) {
                foreach ($items as $item) {
                    if ((string) $item['id'] === rawurldecode($m[1])) {
                        return Http::response($item);
                    }
                }

                // Like the real API: unknown ids are a 200 with an empty body, not a 404.
                return Http::response('', 200);
            }

            return Http::response('', 404);
        });
    }

    public static function provider(int $retries = 1): FakeStoreProvider
    {
        return new FakeStoreProvider(
            http: app(HttpFactory::class),
            mapper: new FakeStoreMapper,
            logger: app(LoggerInterface::class),
            baseUrl: self::BASE_URL,
            timeoutSeconds: 4,
            retries: $retries,
            retrySleepMs: 0,
        );
    }
}
