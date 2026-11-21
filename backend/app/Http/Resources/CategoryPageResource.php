<?php

namespace App\Http\Resources;

use App\DTOs\CategoryPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property CategoryPage $resource */
class CategoryPageResource extends JsonResource
{
    public static $wrap = 'data';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'category' => (new CategoryResource($this->resource->category))->toArray($request),
            'top_rated' => ProductResource::collection($this->resource->topRated)->resolve($request),
            'others' => ProductResource::collection($this->resource->others)->resolve($request),
        ];
    }
}
