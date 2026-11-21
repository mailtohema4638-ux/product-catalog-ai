<?php

use App\Contracts\EmailProviderInterface;
use App\Enums\EnquiryStatus;
use App\Infrastructure\Email\LaravelMailEmailProvider;
use App\Infrastructure\Email\MockEmailProvider;
use App\Jobs\SendEnquiryNotification;
use App\Models\Enquiry;
use Tests\Support\FakeStore;

function savedEnquiry(array $overrides = []): Enquiry
{
    return Enquiry::create(array_merge([
        'product_id' => '12',
        'product_title' => 'WD 4TB Gaming Drive',
        'product_price_minor' => 11400,
        'product_currency' => 'USD',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'status' => EnquiryStatus::Received,
    ], $overrides));
}

describe('notification job', function () {
    it('emails sales and marks the enquiry sent', function () {
        $enquiry = savedEnquiry();

        SendEnquiryNotification::dispatchSync($enquiry->id);

        expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Sent)
            ->and(app(EmailProviderInterface::class)->sent)->toHaveCount(1);
    });

    it('records a failure on the enquiry instead of throwing', function () {
        app(EmailProviderInterface::class)->failWith = new RuntimeException('smtp down');
        $enquiry = savedEnquiry();

        SendEnquiryNotification::dispatchSync($enquiry->id);

        expect($enquiry->fresh())
            ->status->toBe(EnquiryStatus::Failed)
            ->failure_reason->toBe('Mock email provider was told to fail.');
    });

    it('is idempotent: an enquiry that was already sent is not emailed again', function () {
        $enquiry = savedEnquiry(['status' => EnquiryStatus::Sent]);

        SendEnquiryNotification::dispatchSync($enquiry->id);

        expect(app(EmailProviderInterface::class)->sent)->toBe([]);
    });

    it('ignores an enquiry that no longer exists', function () {
        SendEnquiryNotification::dispatchSync('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        expect(app(EmailProviderInterface::class)->sent)->toBe([]);
    });
});

describe('provider selection', function () {
    it('binds the configured email provider', function (string $name, string $class) {
        config(['enquiry.email_provider' => $name]);

        expect(app(EmailProviderInterface::class))->toBeInstanceOf($class);
    })->with([['mock', MockEmailProvider::class], ['mail', LaravelMailEmailProvider::class]]);

    it('fails fast on an unknown provider name', function () {
        config(['enquiry.email_provider' => 'carrier-pigeon']);

        app(EmailProviderInterface::class);
    })->throws(InvalidArgumentException::class, 'Unknown email provider [carrier-pigeon]');
});

it('delivers a real email end to end through Laravel mail with a faked network and mailer', function () {
    config([
        'enquiry.email_provider' => 'mail',
        'mail.default' => 'array',
        'enquiry.sales_inbox' => 'inbox@sales.test',
        'catalogue.provider' => 'fakestore',
        'catalogue.fakestore.base_url' => FakeStore::BASE_URL,
        'catalogue.fakestore.retry_sleep_ms' => 0,
    ]);
    FakeStore::fake();

    $this->postJson('/api/v1/enquiries', [
        'product_id' => '5',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'message' => 'Is it real gold?',
    ])->assertCreated();

    $sent = app('mailer')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1);

    $email = $sent[0]->getOriginalMessage();
    expect($email->getTo()[0]->getAddress())->toBe('inbox@sales.test')
        ->and($email->getSubject())->toBe('New enquiry: Dragon Bracelet')
        ->and($email->getReplyTo()[0]->getAddress())->toBe('jane@example.com')
        ->and($email->getTextBody())->toContain('USD 695.00')->toContain('Is it real gold?');

    expect(Enquiry::sole()->status)->toBe(EnquiryStatus::Sent);
});
