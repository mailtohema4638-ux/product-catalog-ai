<?php

namespace App\DTOs;

/** Business settings for the catalogue, built from config so services never read config directly. */
final readonly class CatalogueSettings
{
    /**
     * @param  list<string>  $featuredProductIds  Curated, ordered "top rated" product IDs for the home page.
     * @param  int  $categoryTopRatedCount  How many top-rated products lead a category page.
     */
    public function __construct(
        public array $featuredProductIds,
        public int $categoryTopRatedCount = 3,
    ) {}
}
