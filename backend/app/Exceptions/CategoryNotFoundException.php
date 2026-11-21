<?php

namespace App\Exceptions;

use RuntimeException;

class CategoryNotFoundException extends RuntimeException
{
    public static function withSlug(string $slug): self
    {
        return new self("Category [{$slug}] was not found.");
    }
}
