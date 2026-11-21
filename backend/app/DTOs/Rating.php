<?php

namespace App\DTOs;

final readonly class Rating
{
    public function __construct(
        public float $average,
        public int $count,
    ) {}

    /** @param array{average: float|int, count: int} $data */
    public static function fromArray(array $data): self
    {
        return new self((float) $data['average'], $data['count']);
    }

    /** @return array{average: float, count: int} */
    public function toArray(): array
    {
        return ['average' => $this->average, 'count' => $this->count];
    }
}
