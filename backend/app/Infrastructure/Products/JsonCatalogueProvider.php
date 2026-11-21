<?php

namespace App\Infrastructure\Products;

use App\Contracts\ProductProviderInterface;
use App\DTOs\Category;
use App\DTOs\Money;
use App\DTOs\Product;
use App\DTOs\Rating;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProviderUnavailableException;
use App\Exceptions\UnexpectedProviderResponseException;
use JsonException;

/**
 * Serves the catalogue from a JSON file we own (resources/catalogue/products.json by default).
 *
 * The file is our own data, so unlike the Fake Store provider it is validated strictly and a
 * mistake fails loudly with a message naming the offending entry, instead of being skipped.
 * No network, no cache needed: it is read once per process.
 */
class JsonCatalogueProvider implements ProductProviderInterface
{
    private const ID = '/^[A-Za-z0-9_-]{1,64}$/';

    private const SLUG = '/^[a-z0-9-]{1,64}$/';

    /** Our own static assets under /images, or an https URL. */
    private const IMAGE = '#^(https://\S+|/images/[A-Za-z0-9/_.-]+\.(svg|png|jpe?g|webp))$#';

    /** @var array{products: list<Product>, categories: list<Category>}|null */
    private ?array $catalogue = null;

    public function __construct(private readonly string $path) {}

    public function all(): array
    {
        return $this->catalogue()['products'];
    }

    public function find(string $id): Product
    {
        foreach ($this->catalogue()['products'] as $product) {
            if ($product->id === $id) {
                return $product;
            }
        }

        throw ProductNotFoundException::withId($id);
    }

    public function byCategory(string $categorySlug): array
    {
        $known = array_filter(
            $this->catalogue()['categories'],
            fn (Category $category): bool => $category->slug === $categorySlug,
        );

        if ($known === []) {
            throw CategoryNotFoundException::withSlug($categorySlug);
        }

        return array_values(array_filter(
            $this->catalogue()['products'],
            fn (Product $product): bool => $product->category->slug === $categorySlug,
        ));
    }

    public function categories(): array
    {
        return $this->catalogue()['categories'];
    }

    /** @return array{products: list<Product>, categories: list<Category>} */
    private function catalogue(): array
    {
        return $this->catalogue ??= $this->load();
    }

    /** @return array{products: list<Product>, categories: list<Category>} */
    private function load(): array
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw new ProviderUnavailableException('The catalogue file is missing or unreadable.');
        }

        try {
            $data = json_decode((string) file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedProviderResponseException('The catalogue file is not valid JSON.', 0, $e);
        }

        if (! is_array($data) || ! is_array($data['categories'] ?? null) || ! is_array($data['products'] ?? null)) {
            throw $this->invalid('it must contain "categories" and "products" lists');
        }

        $categories = [];
        foreach (array_values($data['categories']) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $slug = $this->matching(self::SLUG, $row['slug'] ?? null, "categories[{$index}].slug");

            if (isset($categories[$slug])) {
                throw $this->invalid("duplicate category slug [{$slug}]");
            }

            $categories[$slug] = new Category($slug, $this->text($row['name'] ?? null, "categories[{$index}].name"));
        }

        $products = [];
        $seen = [];
        foreach (array_values($data['products']) as $index => $row) {
            $product = $this->product(is_array($row) ? $row : [], $index, $categories);

            if (isset($seen[$product->id])) {
                throw $this->invalid("duplicate product id [{$product->id}]");
            }

            $seen[$product->id] = true;
            $products[] = $product;
        }

        return ['products' => $products, 'categories' => array_values($categories)];
    }

    /**
     * @param  array<mixed>  $row
     * @param  array<string, Category>  $categories
     */
    private function product(array $row, int $index, array $categories): Product
    {
        $at = "products[{$index}]";

        $price = $row['price_minor'] ?? null;
        if (! is_int($price) || $price < 0) {
            throw $this->invalid("{$at}.price_minor must be a non-negative integer (cents)");
        }

        $currency = $row['currency'] ?? 'USD';
        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw $this->invalid("{$at}.currency must be a 3-letter code");
        }

        $categorySlug = $row['category'] ?? null;
        if (! is_string($categorySlug) || ! isset($categories[$categorySlug])) {
            throw $this->invalid("{$at}.category must be the slug of a listed category");
        }

        $image = $row['image'] ?? null;
        if ($image !== null && (! is_string($image) || str_contains($image, '..') || preg_match(self::IMAGE, $image) !== 1)) {
            throw $this->invalid("{$at}.image must be an https URL or a /images/... path");
        }

        $rating = is_array($row['rating'] ?? null) ? $row['rating'] : [];
        $average = $rating['average'] ?? 0;
        $count = $rating['count'] ?? 0;
        if (! is_numeric($average) || $average < 0 || $average > 5 || ! is_int($count) || $count < 0) {
            throw $this->invalid("{$at}.rating must have an average between 0 and 5 and a non-negative integer count");
        }

        return new Product(
            id: $this->matching(self::ID, $row['id'] ?? null, "{$at}.id"),
            title: $this->text($row['title'] ?? null, "{$at}.title"),
            description: is_string($row['description'] ?? null) ? $row['description'] : '',
            price: new Money($price, $currency),
            category: $categories[$categorySlug],
            imageUrl: $image,
            rating: new Rating((float) $average, $count),
        );
    }

    private function matching(string $pattern, mixed $value, string $at): string
    {
        if (! is_string($value) || preg_match($pattern, $value) !== 1) {
            throw $this->invalid("{$at} is missing or has an invalid format");
        }

        return $value;
    }

    private function text(mixed $value, string $at): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw $this->invalid("{$at} must be a non-empty string");
        }

        return trim($value);
    }

    private function invalid(string $problem): UnexpectedProviderResponseException
    {
        return new UnexpectedProviderResponseException("Invalid catalogue file: {$problem}.");
    }
}
