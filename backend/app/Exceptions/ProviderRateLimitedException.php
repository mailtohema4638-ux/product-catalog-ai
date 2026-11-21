<?php

namespace App\Exceptions;

use Throwable;

class ProviderRateLimitedException extends ProviderUnavailableException
{
    public function __construct(public readonly ?int $retryAfterSeconds = null, ?Throwable $previous = null)
    {
        parent::__construct('The product source is rate limiting requests.', 0, $previous);
    }
}
