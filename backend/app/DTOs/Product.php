<?php

namespace App\DTOs;

/**
 * Source-agnostic product. The IDs are strings because a future data source may not use integers.
 * Plain-array (de)serialisation exists because cached values must not be PHP objects
 * (cache.serializable_classes is disabled).
 */
final readonly class Product
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public Money $price,
        public Category $category,
        public ?string $imageUrl,
        public Rating $rating,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'],
            price: Money::fromArray($data['price']),
            category: Category::fromArray($data['category']),
            imageUrl: $data['image_url'],
            rating: Rating::fromArray($data['rating']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price->toArray(),
            'category' => $this->category->toArray(),
            'image_url' => $this->imageUrl,
            'rating' => $this->rating->toArray(),
        ];
    }
}
