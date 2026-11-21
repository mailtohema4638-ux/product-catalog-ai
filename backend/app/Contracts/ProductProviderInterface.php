<?php

namespace App\Contracts;

use App\DTOs\Category;
use App\DTOs\Product;
use App\Exceptions\CategoryNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProviderUnavailableException;

/**
 * The application's only view of a product data source. Implementations map their own
 * payloads to DTOs and translate every transport failure into the typed exceptions below.
 */
interface ProductProviderInterface
{
    /**
     * @return list<Product>
     *
     * @throws ProviderUnavailableException
     */
    public function all(): array;

    /**
     * @throws ProductNotFoundException
     * @throws ProviderUnavailableException
     */
    public function find(string $id): Product;

    /**
     * @return list<Product>
     *
     * @throws CategoryNotFoundException
     * @throws ProviderUnavailableException
     */
    public function byCategory(string $categorySlug): array;

    /**
     * @return list<Category>
     *
     * @throws ProviderUnavailableException
     */
    public function categories(): array;
}
