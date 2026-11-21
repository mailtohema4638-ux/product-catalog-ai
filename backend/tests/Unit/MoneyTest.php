<?php

use App\DTOs\Money;

it('converts decimals to integer cents without float drift', function (float|int|string $input, int $expected) {
    expect(Money::fromDecimal($input)->amountMinor)->toBe($expected);
})->with([
    'typical' => [109.95, 10995],
    'one decimal' => [22.3, 2230],
    'whole number' => [695, 69500],
    'classic float trap' => [19.99, 1999],
    'sum that is not exact in binary' => [0.1 + 0.2, 30],
    'numeric string' => ['7.95', 795],
    'zero' => [0, 0],
]);

it('round-trips through arrays for caching', function () {
    expect(Money::fromArray((new Money(1250, 'EUR'))->toArray()))->toEqual(new Money(1250, 'EUR'));
});
