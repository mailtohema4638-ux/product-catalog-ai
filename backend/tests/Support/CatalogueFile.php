<?php

namespace Tests\Support;

use App\DTOs\Product;

/** Writes products to a temporary catalogue file in the JSON format JsonCatalogueProvider reads. */
final class CatalogueFile
{
    /** @param list<Product> $products */
    public static function write(array $products): string
    {
        $categories = [];
        foreach ($products as $product) {
            $categories[$product->category->slug] = $product->category->toArray();
        }

        return self::writeRaw([
            'categories' => array_values($categories),
            'products' => array_map(fn (Product $p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'price_minor' => $p->price->amountMinor,
                'currency' => $p->price->currency,
                'category' => $p->category->slug,
                'image' => $p->imageUrl,
                'rating' => $p->rating->toArray(),
            ], $products),
        ]);
    }

    /** @param array<string, mixed>|string $content Array is JSON-encoded; a string is written as is. */
    public static function writeRaw(array|string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalogue-');
        file_put_contents($path, is_string($content) ? $content : json_encode($content));
        register_shutdown_function(fn () => @unlink($path));

        return $path;
    }
}
