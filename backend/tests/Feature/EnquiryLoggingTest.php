<?php

use App\Contracts\EmailProviderInterface;
use App\Enums\EnquiryStatus;
use App\Jobs\SendEnquiryNotification;
use App\Models\Enquiry;
use Illuminate\Support\Facades\Log;

it('logs the underlying cause for operators without logging customer details', function () {
    $logged = [];
    Log::listen(function ($event) use (&$logged) {
        $logged[] = $event;
    });

    app(EmailProviderInterface::class)->failWith = new RuntimeException('Connection refused (smtp.internal:25)');
    $enquiry = Enquiry::create([
        'product_id' => '1', 'product_title' => 'Widget', 'product_price_minor' => 100, 'product_currency' => 'USD',
        'name' => 'Jane Doe', 'email' => 'jane@example.com', 'status' => EnquiryStatus::Received,
    ]);

    SendEnquiryNotification::dispatchSync($enquiry->id);

    $entry = collect($logged)->first(fn ($e) => $e->message === 'Enquiry notification failed.');

    expect($entry)->not->toBeNull()
        ->and($entry->context['enquiry_id'])->toBe($enquiry->id)
        ->and($entry->context['cause'])->toContain('smtp.internal')
        ->and(json_encode($entry->context))->not->toContain('jane@example.com')->not->toContain('Jane');
});
