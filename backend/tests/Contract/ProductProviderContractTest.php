<?php

use App\Contracts\ProductProviderInterface;
use App\DTOs\Product;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Infrastructure\Products\CachingProductProvider;
use App\Infrastructure\Products\FakeStoreMapper;
use App\Infrastructure\Products\InMemoryProductProvider;
use App\Infrastructure\Products\JsonCatalogueProvider;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Tests\Support\CatalogueFile;
use Tests\Support\FakeStore;

/*
 * The contract every ProductProviderInterface implementation must satisfy.
 * To add a new product source, add it to this dataset and make it pass.
 */
function catalogueProducts(): array
{
    $mapper = new FakeStoreMapper;

    return array_map(fn (array $raw): Product => $mapper->product($raw), FakeStore::items());
}

dataset('product providers', [
    'fake store' => [function (): ProductProviderInterface {
        FakeStore::fake();

        return FakeStore::provider();
    }],
    'local catalogue file' => [fn (): ProductProviderInterface => new JsonCatalogueProvider(CatalogueFile::write(catalogueProducts()))],
    'in memory' => [fn (): ProductProviderInterface => new InMemoryProductProvider(catalogueProducts())],
    'caching decorator' => [fn (): ProductProviderInterface => new CachingProductProvider(
        inner: new InMemoryProductProvider(catalogueProducts()),
        cache: Cache::build(['driver' => 'array', 'serialize' => true]),
        logger: app(LoggerInterface::class),
        namespace: 'contract',
        ttlSeconds: 60,
        staleTtlSeconds: 600,
    )],
]);

it('lists every product with string ids', function (ProductProviderInterface $provider) {
    $products = $provider->all();

    expect($products)->toHaveCount(7)->each->toBeInstanceOf(Product::class);

    $ids = array_map(fn (Product $p): string => $p->id, $products);
    sort($ids);
    expect($ids)->toBe(['1', '2', '3', '4', '5', '6', '7']);
})->with('product providers');

it('finds a product by id with price in integer minor units', function (ProductProviderInterface $provider) {
    $product = $provider->find('1');

    expect($product->title)->toBe('Foldsack Backpack')
        ->and($product->price->amountMinor)->toBe(10995)
        ->and($product->price->currency)->toBe('USD')
        ->and($product->category->slug)->toBe('mens-clothing')
        ->and($product->rating->count)->toBe(120);
})->with('product providers');

it('throws ProductNotFoundException for an unknown id', function (ProductProviderInterface $provider) {
    $provider->find('9999');
})->with('product providers')->throws(ProductNotFoundException::class);

it('lists the distinct categories', function (ProductProviderInterface $provider) {
    $slugs = array_map(fn ($c): string => $c->slug, $provider->categories());
    sort($slugs);

    expect($slugs)->toBe(['electronics', 'jewelery', 'mens-clothing']);
})->with('product providers');

it('returns only the products of the requested category', function (ProductProviderInterface $provider) {
    $products = $provider->byCategory('jewelery');

    expect($products)->toHaveCount(2)
        ->and(array_map(fn (Product $p): string => $p->category->slug, $products))->each->toBe('jewelery');
})->with('product providers');

it('throws CategoryNotFoundException for an unknown category', function (ProductProviderInterface $provider) {
    $provider->byCategory('nope');
})->with('product providers')->throws(CategoryNotFoundException::class);
