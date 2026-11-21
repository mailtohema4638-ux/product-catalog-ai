<?php

use App\DTOs\CatalogueSettings;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Infrastructure\Products\InMemoryProductProvider;
use App\Services\CatalogueService;
use App\Services\SearchMatcher;
use Psr\Log\LoggerInterface;
use Tests\Support\Make;

function catalogueService(array $products, array $featured = [], int $topN = 3, ?LoggerInterface $logger = null): CatalogueService
{
    return new CatalogueService(
        new InMemoryProductProvider($products),
        new SearchMatcher,
        new CatalogueSettings($featured, $topN),
        $logger ?? Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing(),
    );
}

function productIds(array $products): array
{
    return array_map(fn ($p) => $p->id, $products);
}

describe('featured', function () {
    it('returns the curated products in the configured order', function () {
        $service = catalogueService(
            [Make::product('1'), Make::product('2'), Make::product('3'), Make::product('4')],
            featured: ['3', '1', '4'],
        );

        expect(productIds($service->featured()))->toBe(['3', '1', '4']);
    });

    it('skips and logs curated ids the source no longer has, without backfilling', function () {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once()->with(Mockery::type('string'), ['product_id' => '999']);

        $service = catalogueService([Make::product('1'), Make::product('2')], featured: ['2', '999', '1'], logger: $logger);

        expect(productIds($service->featured()))->toBe(['2', '1']);
    });
});

describe('category page', function () {
    it('leads with the top rated products, then lists the rest alphabetically', function () {
        $service = catalogueService([
            Make::product('a', 'Zebra', 'Tools', 3.0, 10),
            Make::product('b', 'Hammer', 'Tools', 4.8, 400),
            Make::product('c', 'Saw', 'Tools', 4.8, 900),
            Make::product('d', 'Anvil', 'Tools', 2.0, 50),
            Make::product('e', 'Level', 'Tools', 4.1, 20),
            Make::product('x', 'Other', 'Garden', 5.0, 999),
        ], topN: 2);

        $page = $service->category('tools');

        expect($page->category->name)->toBe('Tools')
            ->and(productIds($page->topRated))->toBe(['c', 'b'])
            ->and(productIds($page->others))->toBe(['d', 'e', 'a']);
    });

    it('breaks rating ties by review count then title', function () {
        $service = catalogueService([
            Make::product('1', 'Beta', 'Tools', 4.5, 10),
            Make::product('2', 'Alpha', 'Tools', 4.5, 10),
            Make::product('3', 'Gamma', 'Tools', 4.5, 50),
        ], topN: 3);

        expect(productIds($service->category('tools')->topRated))->toBe(['3', '2', '1']);
    });

    it('puts everything in topRated when the category is smaller than the limit', function () {
        $page = catalogueService([Make::product('1', category: 'Tools')], topN: 3)->category('tools');

        expect($page->topRated)->toHaveCount(1)->and($page->others)->toBe([]);
    });

    it('throws for an unknown category', function () {
        catalogueService([Make::product('1')])->category('nope');
    })->throws(CategoryNotFoundException::class);
});

describe('search', function () {
    it('searches the whole catalogue, or only within a category', function () {
        $service = catalogueService([
            Make::product('1', 'Red Hammer', 'Tools'),
            Make::product('2', 'Red Rose', 'Garden'),
        ]);

        expect(productIds($service->search('red')))->toBe(['1', '2'])
            ->and(productIds($service->search('red', 'garden')))->toBe(['2']);
    });
});

it('lists categories alphabetically', function () {
    $service = catalogueService([Make::product('1', category: 'Toys'), Make::product('2', category: 'Garden')]);

    expect(array_map(fn ($c) => $c->name, $service->categories()))->toBe(['Garden', 'Toys']);
});

it('throws when a product does not exist', function () {
    catalogueService([Make::product('1')])->product('2');
})->throws(ProductNotFoundException::class);
