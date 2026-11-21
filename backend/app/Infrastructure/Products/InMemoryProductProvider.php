<?php

namespace App\Infrastructure\Products;

use App\Contracts\ProductProviderInterface;
use App\DTOs\Category;
use App\DTOs\Product;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\ProductNotFoundException;

/** Fixed-data provider: the contract's second implementation, used by tests and as a template for new sources. */
class InMemoryProductProvider implements ProductProviderInterface
{
    /** @param list<Product> $products */
    public function __construct(private readonly array $products) {}

    public function all(): array
    {
        return $this->products;
    }

    public function find(string $id): Product
    {
        foreach ($this->products as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        throw ProductNotFoundException::withId($id);
    }

    public function byCategory(string $categorySlug): array
    {
        if (! array_key_exists($categorySlug, $this->categoriesBySlug())) {
            throw CategoryNotFoundException::withSlug($categorySlug);
        }

        return array_values(array_filter(
            $this->products,
            fn (Product $product): bool => $product->category->slug === $categorySlug,
        ));
    }

    public function categories(): array
    {
        return array_values($this->categoriesBySlug());
    }

    /** @return array<string, Category> */
    private function categoriesBySlug(): array
    {
        $categories = [];
        foreach ($this->products as $product) {
            $categories[$product->category->slug] ??= $product->category;
        }

        return $categories;
    }
}
