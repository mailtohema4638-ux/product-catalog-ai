<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryPageResource;
use App\Http\Resources\CategoryResource;
use App\Services\CatalogueService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->catalogue->categories());
    }

    public function show(string $slug): CategoryPageResource
    {
        return new CategoryPageResource($this->catalogue->category($slug));
    }
}
