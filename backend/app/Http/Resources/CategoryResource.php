<?php

namespace App\Http\Resources;

use App\DTOs\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Category $resource */
class CategoryResource extends JsonResource
{
    public static $wrap = 'data';

    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
        ];
    }
}
