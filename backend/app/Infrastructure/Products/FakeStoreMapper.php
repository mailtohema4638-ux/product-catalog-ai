<?php

namespace App\Infrastructure\Products;

use App\DTOs\Category;
use App\DTOs\Money;
use App\DTOs\Product;
use App\DTOs\Rating;
use App\Exceptions\UnexpectedProviderResponseException;
use Illuminate\Support\Str;

/** The only place that knows the Fake Store JSON shape. */
class FakeStoreMapper
{
    /**
     * @param  array<string, mixed>  $raw
     *
     * @throws UnexpectedProviderResponseException
     */
    public function product(array $raw): Product
    {
        $id = $raw['id'] ?? null;
        $title = $raw['title'] ?? null;
        $price = $raw['price'] ?? null;
        $category = $raw['category'] ?? null;

        if (! is_int($id) && ! (is_string($id) && $id !== '')) {
            throw new UnexpectedProviderResponseException('Product is missing a valid id.');
        }
        if (! is_string($title) || trim($title) === '') {
            throw new UnexpectedProviderResponseException("Product [{$id}] is missing a title.");
        }
        if (! is_numeric($price) || $price < 0) {
            throw new UnexpectedProviderResponseException("Product [{$id}] has an invalid price.");
        }
        if (! is_string($category) || trim($category) === '') {
            throw new UnexpectedProviderResponseException("Product [{$id}] is missing a category.");
        }

        $rating = is_array($raw['rating'] ?? null) ? $raw['rating'] : [];

        return new Product(
            id: (string) $id,
            title: trim($title),
            description: is_string($raw['description'] ?? null) ? $raw['description'] : '',
            price: Money::fromDecimal($price),
            category: $this->category($category),
            imageUrl: $this->secureUrl($raw['image'] ?? null),
            rating: new Rating(
                average: is_numeric($rating['rate'] ?? null) ? (float) $rating['rate'] : 0.0,
                count: is_numeric($rating['count'] ?? null) ? (int) $rating['count'] : 0,
            ),
        );
    }

    /** "men's clothing" becomes slug "mens-clothing" and name "Men's Clothing". */
    public function category(string $sourceName): Category
    {
        $name = trim($sourceName);

        return new Category(Str::slug($name), Str::title($name));
    }

    /** Only https image URLs are trusted; anything else is dropped. */
    private function secureUrl(mixed $url): ?string
    {
        return is_string($url) && str_starts_with(strtolower($url), 'https://') ? $url : null;
    }
}
