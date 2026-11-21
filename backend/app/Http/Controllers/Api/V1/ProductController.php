<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchProductsRequest;
use App\Http\Resources\ProductResource;
use App\Services\CatalogueService;

class ProductController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(SearchProductsRequest $request)
    {
        return ProductResource::collection(
            $this->catalogue->search($request->term(), $request->categorySlug()),
        );
    }

    public function show(string $id)
    {
        return new ProductResource($this->catalogue->product($id));
    }
}
