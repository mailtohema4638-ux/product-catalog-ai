<?php

namespace Tests\Support;

use App\Contracts\ProductProviderInterface;
use App\DTOs\Product;
use Throwable;

/** Wraps a provider, counting calls and optionally failing, to observe the caching decorator. */
final class SpyProvider implements ProductProviderInterface
{
    public int $calls = 0;

    public ?Throwable $failWith = null;

    public function __construct(private readonly ProductProviderInterface $inner) {}

    public function all(): array
    {
        $this->enter();

        return $this->inner->all();
    }

    public function find(string $id): Product
    {
        $this->enter();

        return $this->inner->find($id);
    }

    public function byCategory(string $categorySlug): array
    {
        $this->enter();

        return $this->inner->byCategory($categorySlug);
    }

    public function categories(): array
    {
        $this->enter();

        return $this->inner->categories();
    }

    private function enter(): void
    {
        $this->calls++;

        if ($this->failWith !== null) {
            throw $this->failWith;
        }
    }
}
