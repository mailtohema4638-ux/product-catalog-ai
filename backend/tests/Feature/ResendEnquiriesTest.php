<?php

use App\Contracts\EmailProviderInterface;
use App\Enums\EnquiryStatus;
use App\Models\Enquiry;

function enquiryAt(EnquiryStatus $status, int $minutesAgo): Enquiry
{
    $enquiry = Enquiry::create([
        'product_id' => '1', 'product_title' => 'Widget', 'product_price_minor' => 100, 'product_currency' => 'USD',
        'name' => 'Jane Doe', 'email' => 'jane@example.com', 'status' => $status,
    ]);
    $enquiry->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

    return $enquiry;
}

describe('enquiries:resend-failed', function () {
    it('re-sends failed enquiries', function () {
        $failed = enquiryAt(EnquiryStatus::Failed, 1);

        $this->artisan('enquiries:resend-failed')->expectsOutputToContain('Re-queued 1')->assertSuccessful();

        expect($failed->fresh()->status)->toBe(EnquiryStatus::Sent);
    });

    it('also recovers enquiries whose notification never ran', function () {
        $stuck = enquiryAt(EnquiryStatus::Received, 30);

        $this->artisan('enquiries:resend-failed')->assertSuccessful();

        expect($stuck->fresh()->status)->toBe(EnquiryStatus::Sent);
    });

    it('leaves recent, in-flight enquiries and already-sent ones alone', function () {
        $recent = enquiryAt(EnquiryStatus::Received, 2);
        $sent = enquiryAt(EnquiryStatus::Sent, 60);

        $this->artisan('enquiries:resend-failed')->expectsOutputToContain('Re-queued 0')->assertSuccessful();

        expect($recent->fresh()->status)->toBe(EnquiryStatus::Received)
            ->and($sent->fresh()->status)->toBe(EnquiryStatus::Sent)
            ->and(app(EmailProviderInterface::class)->sent)->toBe([]);
    });

    it('does not skip rows when many are processed', function () {
        foreach (range(1, 25) as $i) {
            enquiryAt(EnquiryStatus::Failed, 5);
        }

        $this->artisan('enquiries:resend-failed')->expectsOutputToContain('Re-queued 25')->assertSuccessful();

        expect(Enquiry::where('status', EnquiryStatus::Sent)->count())->toBe(25)
            ->and(app(EmailProviderInterface::class)->sent)->toHaveCount(25);
    });
});
