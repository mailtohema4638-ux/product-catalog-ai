<?php

namespace App\DTOs;

/** A validated, normalised customer enquiry about one product. */
final readonly class EnquiryData
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $message = null,
    ) {}
}
