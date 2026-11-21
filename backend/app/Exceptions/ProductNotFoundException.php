<?php

namespace App\Exceptions;

use RuntimeException;

class ProductNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("Product [{$id}] was not found.");
    }
}
