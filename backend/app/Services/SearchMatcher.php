<?php

namespace App\Services;

use App\DTOs\Product;

/**
 * Source-independent text search: case-insensitive, every whitespace-separated term must
 * appear in the title, description or category name. Plain substring matching (no regex),
 * so user input can never alter the match logic.
 */
class SearchMatcher
{
    /**
     * @param  list<Product>  $products
     * @return list<Product>
     */
    public function filter(array $products, string $term): array
    {
        $tokens = preg_split('/\s+/u', mb_strtolower(trim($term)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return [];
        }

        return array_values(array_filter(
            $products,
            function (Product $product) use ($tokens): bool {
                $haystack = mb_strtolower("{$product->title} {$product->description} {$product->category->name}");

                foreach ($tokens as $token) {
                    if (! str_contains($haystack, $token)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }
}
