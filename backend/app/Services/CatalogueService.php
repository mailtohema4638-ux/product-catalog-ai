<?php

namespace App\Services;

use App\Contracts\ProductProviderInterface;
use App\DTOs\CatalogueSettings;
use App\DTOs\Category;
use App\DTOs\CategoryPage;
use App\DTOs\Product;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\ProductNotFoundException;
use Psr\Log\LoggerInterface;

/** Catalogue use cases. Knows the business rules, nothing about where products come from. */
class CatalogueService
{
    public function __construct(
        private readonly ProductProviderInterface $products,
        private readonly SearchMatcher $matcher,
        private readonly CatalogueSettings $settings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * The curated "top rated" products, in the configured order.
     * IDs the source no longer has are skipped and logged rather than silently replaced.
     *
     * @return list<Product>
     */
    public function featured(): array
    {
        $byId = [];
        foreach ($this->products->all() as $product) {
            $byId[$product->id] = $product;
        }

        $featured = [];
        foreach ($this->settings->featuredProductIds as $id) {
            if (isset($byId[$id])) {
                $featured[] = $byId[$id];
            } else {
                $this->logger->warning('Featured product is not in the catalogue.', ['product_id' => $id]);
            }
        }

        return $featured;
    }

    /** @return list<Category> */
    public function categories(): array
    {
        $categories = $this->products->categories();
        usort($categories, fn (Category $a, Category $b): int => strcmp($a->name, $b->name));

        return $categories;
    }

    /** @throws CategoryNotFoundException */
    public function category(string $slug): CategoryPage
    {
        $category = null;
        foreach ($this->products->categories() as $candidate) {
            if ($candidate->slug === $slug) {
                $category = $candidate;
                break;
            }
        }

        if ($category === null) {
            throw CategoryNotFoundException::withSlug($slug);
        }

        $ranked = $this->products->byCategory($slug);
        usort($ranked, fn (Product $a, Product $b): int => [$b->rating->average, $b->rating->count, $a->title]
            <=> [$a->rating->average, $a->rating->count, $b->title]);

        $topRated = array_slice($ranked, 0, max(0, $this->settings->categoryTopRatedCount));
        $others = array_slice($ranked, count($topRated));
        usort($others, fn (Product $a, Product $b): int => strcmp($a->title, $b->title));

        return new CategoryPage($category, $topRated, $others);
    }

    /**
     * @return list<Product>
     *
     * @throws CategoryNotFoundException when a category filter is given but unknown
     */
    public function search(string $term, ?string $categorySlug = null): array
    {
        $candidates = $categorySlug === null
            ? $this->products->all()
            : $this->products->byCategory($categorySlug);

        return $this->matcher->filter($candidates, $term);
    }

    /** @throws ProductNotFoundException */
    public function product(string $id): Product
    {
        return $this->products->find($id);
    }
}
