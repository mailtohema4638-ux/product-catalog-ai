<?php

namespace App\DTOs;

final readonly class Money
{
    public function __construct(
        public int $amountMinor,
        public string $currency = 'USD',
    ) {}

    /** Convert a decimal amount (e.g. 109.95) to integer minor units without float drift. */
    public static function fromDecimal(float|int|string $amount, string $currency = 'USD'): self
    {
        return new self((int) round(((float) $amount) * 100), $currency);
    }

    /** @param array{amount_minor: int, currency: string} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['amount_minor'], $data['currency']);
    }

    /** @return array{amount_minor: int, currency: string} */
    public function toArray(): array
    {
        return ['amount_minor' => $this->amountMinor, 'currency' => $this->currency];
    }
}
