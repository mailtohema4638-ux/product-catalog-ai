<?php

use App\Services\SearchMatcher;
use Tests\Support\Make;

function searchIds(string $term): array
{
    $products = [
        Make::product('1', 'Gold Bracelet', 'Jewelery', description: 'Hand made in silver.'),
        Make::product('2', 'Rain Jacket', "Women's Clothing", description: 'Waterproof, with GOLD zips.'),
        Make::product('3', 'Slim T-Shirt', "Men's Clothing", description: 'Cotton.'),
        Make::product('4', 'Café Table (outdoor)', 'Home', description: 'Wood.'),
    ];

    return array_map(fn ($p) => $p->id, (new SearchMatcher)->filter($products, $term));
}

it('matches case-insensitively across title and description', function () {
    expect(searchIds('gold'))->toBe(['1', '2'])
        ->and(searchIds('GOLD'))->toBe(['1', '2']);
});

it('matches the category name', function () {
    expect(searchIds('jewelery'))->toBe(['1']);
});

it('requires every term to match', function () {
    expect(searchIds('gold jacket'))->toBe(['2'])
        ->and(searchIds('gold  zips'))->toBe(['2']);
});

it('returns nothing when there is no match', function () {
    expect(searchIds('banana'))->toBe([]);
});

it('returns nothing for a blank term', function () {
    expect(searchIds('   '))->toBe([]);
});

it('treats regex and special characters literally', function () {
    expect(searchIds('(outdoor)'))->toBe(['4'])
        ->and(searchIds('.*'))->toBe([])
        ->and(searchIds('[a-z]+'))->toBe([]);
});

it('handles non-ascii text', function () {
    expect(searchIds('CAFÉ'))->toBe(['4']);
});
