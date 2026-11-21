<?php

namespace App\Http\Resources;

use App\DTOs\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Product $resource */
class ProductResource extends JsonResource
{
    public static $wrap = 'data';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $product = $this->resource;

        return [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'price' => [
                'amount_minor' => $product->price->amountMinor,
                'currency' => $product->price->currency,
            ],
            'category' => (new CategoryResource($product->category))->toArray($request),
            'image_url' => $product->imageUrl,
            'rating' => [
                'average' => $product->rating->average,
                'count' => $product->rating->count,
            ],
        ];
    }
}
