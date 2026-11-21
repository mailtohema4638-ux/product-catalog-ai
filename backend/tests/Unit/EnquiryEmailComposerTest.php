<?php

use App\Models\Enquiry;
use App\Services\EnquiryEmailComposer;

function enquiryFixture(array $overrides = []): Enquiry
{
    $enquiry = new Enquiry(array_merge([
        'product_id' => '12',
        'product_title' => 'WD 4TB Gaming Drive',
        'product_price_minor' => 11400,
        'product_currency' => 'USD',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1 555 0100',
        'message' => "Do you have it in stock?\nThanks!",
    ], $overrides));
    $enquiry->id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    return $enquiry;
}

it('composes a plain text email addressed to the configured sales inbox', function () {
    $message = (new EnquiryEmailComposer('sales@catalogue.test'))->compose(enquiryFixture());

    expect($message->to)->toBe('sales@catalogue.test')
        ->and($message->subject)->toBe('New enquiry: WD 4TB Gaming Drive')
        ->and($message->replyToEmail)->toBe('jane@example.com')
        ->and($message->replyToName)->toBe('Jane Doe')
        ->and($message->body)
        ->toContain('Reference: 01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->toContain('WD 4TB Gaming Drive (ID 12)')
        ->toContain('USD 114.00')
        ->toContain('Name:      Jane Doe')
        ->toContain('jane@example.com')
        ->toContain('+1 555 0100')
        ->toContain("Do you have it in stock?\nThanks!");
});

it('copes with the optional fields being absent', function () {
    $message = (new EnquiryEmailComposer('sales@catalogue.test'))->compose(enquiryFixture(['phone' => null, 'message' => null]));

    expect($message->body)->toContain('not provided')->toContain('(none)');
});

it('keeps header-bound values on one line, so customers cannot inject headers', function () {
    $message = (new EnquiryEmailComposer('sales@catalogue.test'))->compose(enquiryFixture([
        'name' => "Eve\r\nBcc: attacker@evil.test",
        'product_title' => "Drive\nSubject: forged",
    ]));

    expect($message->subject)->not->toContain("\n")->not->toContain("\r")
        ->and($message->replyToName)->not->toContain("\n")->not->toContain("\r");
});

it('does not interpret markup in customer text', function () {
    $message = (new EnquiryEmailComposer('sales@catalogue.test'))->compose(enquiryFixture([
        'message' => '<script>alert(1)</script>',
    ]));

    expect($message->body)->toContain('<script>alert(1)</script>');
});

it('formats prices from integer cents', function (int $minor, string $expected) {
    $message = (new EnquiryEmailComposer('s@c.test'))->compose(enquiryFixture(['product_price_minor' => $minor]));

    expect($message->body)->toContain($expected);
})->with([[995, 'USD 9.95'], [69500, 'USD 695.00'], [5, 'USD 0.05']]);
