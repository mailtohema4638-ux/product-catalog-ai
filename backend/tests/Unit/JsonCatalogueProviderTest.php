<?php

use App\Exceptions\ProviderUnavailableException;
use App\Exceptions\UnexpectedProviderResponseException;
use App\Infrastructure\Products\JsonCatalogueProvider;
use Tests\Support\CatalogueFile;

const BUNDLED_CATALOGUE = __DIR__.'/../../resources/catalogue/products.json';

function bundledCatalogue(): JsonCatalogueProvider
{
    return new JsonCatalogueProvider(BUNDLED_CATALOGUE);
}

/** @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>} */
function validCatalogueData(): array
{
    return [
        'categories' => [['slug' => 'tools', 'name' => 'Tools']],
        'products' => [[
            'id' => 'hammer-1', 'title' => 'Hammer', 'description' => 'Hits things.', 'price_minor' => 1999,
            'currency' => 'USD', 'category' => 'tools', 'image' => '/images/categories/tools.svg',
            'rating' => ['average' => 4.5, 'count' => 10],
        ]],
    ];
}

describe('the bundled catalogue', function () {
    it('has the four expected categories, in order', function () {
        expect(array_map(fn ($c) => [$c->slug, $c->name], bundledCatalogue()->categories()))->toBe([
            ['office-supplies-stationery', 'Office Supplies & Stationery'],
            ['tech-electronics', 'Tech & Electronics'],
            ['office-furniture', 'Office Furniture'],
            ['print-photo-services', 'Print & Photo Services'],
        ]);
    });

    it('has enough products in every category for a top-rated section plus more', function () {
        foreach (bundledCatalogue()->categories() as $category) {
            expect(count(bundledCatalogue()->byCategory($category->slug)))->toBeGreaterThan(config('catalogue.category_top_rated_count'));
        }
    });

    it('has sensible data for every product', function () {
        foreach (bundledCatalogue()->all() as $product) {
            expect($product->price->amountMinor)->toBeGreaterThan(0)
                ->and($product->rating->average)->toBeBetween(0, 5)
                ->and($product->rating->count)->toBeGreaterThan(0)
                ->and(mb_strlen($product->title))->toBeGreaterThan(5)
                ->and($product->description)->not->toBe('');
        }
    });

    it('points every product image at a file that really ships with the frontend', function () {
        foreach (bundledCatalogue()->all() as $product) {
            expect($product->imageUrl)->not->toBeNull()
                ->and(file_exists(base_path('../frontend/public'.$product->imageUrl)))->toBeTrue("missing image {$product->imageUrl}");
        }
    });

    it('contains every product the default featured list points at', function () {
        preg_match('/^FEATURED_PRODUCT_IDS=(.+)$/m', file_get_contents(base_path('.env.example')), $matches);
        $ids = array_map('trim', explode(',', $matches[1]));
        $catalogueIds = array_map(fn ($p) => $p->id, bundledCatalogue()->all());

        expect($ids)->toHaveCount(5)->and(array_unique($ids))->toHaveCount(5)
            ->and(array_diff($ids, $catalogueIds))->toBe([]);
    });

    it('is used by default', function () {
        expect(config('catalogue.provider'))->toBe('local');
    });
});

describe('strict validation of our own catalogue file', function () {
    it('accepts a well-formed file', function () {
        $provider = new JsonCatalogueProvider(CatalogueFile::writeRaw(validCatalogueData()));

        expect($provider->find('hammer-1')->price->amountMinor)->toBe(1999);
    });

    it('fails loudly, naming the problem, for a malformed entry', function (Closure $break, string $message) {
        $data = validCatalogueData();
        $break($data);

        try {
            (new JsonCatalogueProvider(CatalogueFile::writeRaw($data)))->all();
            $this->fail('Expected UnexpectedProviderResponseException');
        } catch (UnexpectedProviderResponseException $e) {
            expect($e->getMessage())->toContain($message);
        }
    })->with([
        'duplicate product id' => [function (array &$d) {
            $d['products'][] = $d['products'][0];
        }, 'duplicate product id [hammer-1]'],
        'duplicate category' => [function (array &$d) {
            $d['categories'][] = $d['categories'][0];
        }, 'duplicate category slug [tools]'],
        'unknown category' => [function (array &$d) {
            $d['products'][0]['category'] = 'nope';
        }, 'products[0].category'],
        'negative price' => [function (array &$d) {
            $d['products'][0]['price_minor'] = -1;
        }, 'products[0].price_minor'],
        'price in dollars as a float' => [function (array &$d) {
            $d['products'][0]['price_minor'] = 19.99;
        }, 'products[0].price_minor'],
        'id with spaces' => [function (array &$d) {
            $d['products'][0]['id'] = 'a b';
        }, 'products[0].id'],
        'blank title' => [function (array &$d) {
            $d['products'][0]['title'] = '  ';
        }, 'products[0].title'],
        'script image' => [function (array &$d) {
            $d['products'][0]['image'] = 'javascript:alert(1)';
        }, 'products[0].image'],
        'path traversal image' => [function (array &$d) {
            $d['products'][0]['image'] = '/images/../secret.svg';
        }, 'products[0].image'],
        'http image' => [function (array &$d) {
            $d['products'][0]['image'] = 'http://insecure.test/a.png';
        }, 'products[0].image'],
        'rating above 5' => [function (array &$d) {
            $d['products'][0]['rating']['average'] = 6;
        }, 'products[0].rating'],
        'bad currency' => [function (array &$d) {
            $d['products'][0]['currency'] = 'dollars';
        }, 'products[0].currency'],
        'bad category slug' => [function (array &$d) {
            $d['categories'][0]['slug'] = 'Has Spaces';
        }, 'categories[0].slug'],
    ]);

    it('rejects a file that is not JSON', function () {
        (new JsonCatalogueProvider(CatalogueFile::writeRaw('<html>nope')))->all();
    })->throws(UnexpectedProviderResponseException::class, 'not valid JSON');

    it('rejects a file without the expected lists', function () {
        (new JsonCatalogueProvider(CatalogueFile::writeRaw(['products' => []])))->all();
    })->throws(UnexpectedProviderResponseException::class, '"categories" and "products"');

    it('reports a missing file as the source being unavailable', function () {
        (new JsonCatalogueProvider('/no/such/file.json'))->all();
    })->throws(ProviderUnavailableException::class);
});
