<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\CatalogueService;

class FeaturedProductsController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function __invoke()
    {
        return ProductResource::collection($this->catalogue->featured());
    }
}
