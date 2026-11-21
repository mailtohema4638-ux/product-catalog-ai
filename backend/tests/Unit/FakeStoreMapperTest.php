<?php

use App\Exceptions\UnexpectedProviderResponseException;
use App\Infrastructure\Products\FakeStoreMapper;

function rawProduct(array $overrides = []): array
{
    return array_merge([
        'id' => 12,
        'title' => '  WD 4TB Gaming Drive  ',
        'price' => 114,
        'description' => 'Expand your storage.',
        'category' => 'electronics',
        'image' => 'https://fakestoreapi.com/img/12.jpg',
        'rating' => ['rate' => 4.8, 'count' => 400],
    ], $overrides);
}

it('maps a Fake Store product to the domain model', function () {
    $product = (new FakeStoreMapper)->product(rawProduct());

    expect($product->id)->toBe('12')
        ->and($product->title)->toBe('WD 4TB Gaming Drive')
        ->and($product->price->amountMinor)->toBe(11400)
        ->and($product->category->slug)->toBe('electronics')
        ->and($product->imageUrl)->toBe('https://fakestoreapi.com/img/12.jpg')
        ->and($product->rating->average)->toBe(4.8)
        ->and($product->rating->count)->toBe(400);
});

it('slugifies categories that contain spaces and apostrophes', function (string $source, string $slug, string $name) {
    $category = (new FakeStoreMapper)->category($source);

    expect($category->slug)->toBe($slug)->and($category->name)->toBe($name);
})->with([
    "men's clothing" => ["men's clothing", 'mens-clothing', "Men's Clothing"],
    "women's clothing" => ["women's clothing", 'womens-clothing', "Women's Clothing"],
    'plain' => ['electronics', 'electronics', 'Electronics'],
]);

it('defaults a missing rating to zero rather than failing', function () {
    $product = (new FakeStoreMapper)->product(rawProduct(['rating' => null]));

    expect($product->rating->average)->toBe(0.0)->and($product->rating->count)->toBe(0);
});

it('drops image urls that are not https', function (mixed $image) {
    expect((new FakeStoreMapper)->product(rawProduct(['image' => $image]))->imageUrl)->toBeNull();
})->with(['http' => 'http://fakestoreapi.com/img.jpg', 'javascript' => 'javascript:alert(1)', 'missing' => null]);

it('rejects payloads it cannot trust', function (array $overrides) {
    (new FakeStoreMapper)->product(rawProduct($overrides));
})->with([
    'no id' => [['id' => null]],
    'blank title' => [['title' => '   ']],
    'non numeric price' => [['price' => 'free']],
    'negative price' => [['price' => -5]],
    'no category' => [['category' => '']],
])->throws(UnexpectedProviderResponseException::class);
