<?php

namespace App\DTOs;

final readonly class Category
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {}

    /** @param array{slug: string, name: string} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['slug'], $data['name']);
    }

    /** @return array{slug: string, name: string} */
    public function toArray(): array
    {
        return ['slug' => $this->slug, 'name' => $this->name];
    }
}
