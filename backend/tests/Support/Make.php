<?php

namespace Tests\Support;

use App\DTOs\Category;
use App\DTOs\Money;
use App\DTOs\Product;
use App\DTOs\Rating;
use Illuminate\Support\Str;

final class Make
{
    public static function product(
        string $id,
        string $title = 'Widget',
        string $category = 'Tools',
        float $rating = 4.0,
        int $reviews = 10,
        int $priceMinor = 1000,
        string $description = 'A product.',
    ): Product {
        return new Product(
            id: $id,
            title: $title,
            description: $description,
            price: new Money($priceMinor),
            category: new Category(Str::slug($category), $category),
            imageUrl: "https://images.test/{$id}.jpg",
            rating: new Rating($rating, $reviews),
        );
    }
}
