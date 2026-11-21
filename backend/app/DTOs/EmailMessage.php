<?php

namespace App\DTOs;

final readonly class EmailMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public ?string $replyToEmail = null,
        public ?string $replyToName = null,
    ) {}
}
