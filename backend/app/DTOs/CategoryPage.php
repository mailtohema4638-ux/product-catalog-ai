<?php

namespace App\DTOs;

final readonly class CategoryPage
{
    /**
     * @param  list<Product>  $topRated
     * @param  list<Product>  $others
     */
    public function __construct(
        public Category $category,
        public array $topRated,
        public array $others,
    ) {}
}
