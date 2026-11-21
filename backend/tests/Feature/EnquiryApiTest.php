<?php

use App\Contracts\EmailProviderInterface;
use App\Contracts\ProductProviderInterface;
use App\Enums\EnquiryStatus;
use App\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Email\MockEmailProvider;
use App\Infrastructure\Products\InMemoryProductProvider;
use App\Jobs\SendEnquiryNotification;
use App\Models\Enquiry;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Make;

function validEnquiry(array $overrides = []): array
{
    return array_merge([
        'product_id' => '12',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1 555 0100',
        'message' => 'Is this in stock?',
    ], $overrides);
}

function mockEmail(): MockEmailProvider
{
    return app(EmailProviderInterface::class);
}

beforeEach(function () {
    $this->app->instance(ProductProviderInterface::class, new InMemoryProductProvider([
        Make::product('12', 'WD 4TB Gaming Drive', 'Electronics', 4.8, 400, 11400),
    ]));
});

describe('submitting an enquiry', function () {
    it('saves it, thanks the customer, and emails the sales inbox', function () {
        $response = $this->postJson('/api/v1/enquiries', validEnquiry())->assertCreated();

        $enquiry = Enquiry::sole();
        $response->assertExactJson(['data' => ['reference' => $enquiry->id]]);

        expect($enquiry->status)->toBe(EnquiryStatus::Sent)
            ->and(mockEmail()->sent)->toHaveCount(1);

        $email = mockEmail()->sent[0];
        expect($email->to)->toBe('sales@catalogue.test')
            ->and($email->subject)->toBe('New enquiry: WD 4TB Gaming Drive')
            ->and($email->replyToEmail)->toBe('jane@example.com')
            ->and($email->body)->toContain('Jane Doe')->toContain('Is this in stock?')->toContain('USD 114.00');
    });

    it('snapshots the product so later catalogue changes do not rewrite history', function () {
        $this->postJson('/api/v1/enquiries', validEnquiry())->assertCreated();

        expect(Enquiry::sole())
            ->product_id->toBe('12')
            ->product_title->toBe('WD 4TB Gaming Drive')
            ->product_price_minor->toBe(11400)
            ->product_currency->toBe('USD');
    });

    it('only needs name, email and a product', function () {
        $this->postJson('/api/v1/enquiries', ['product_id' => '12', 'name' => 'Jo', 'email' => 'jo@example.com'])
            ->assertCreated();

        expect(Enquiry::sole())->phone->toBeNull()->message->toBeNull();
    });

    it('queues the email for after the response is sent', function () {
        Bus::fake();

        $this->postJson('/api/v1/enquiries', validEnquiry())->assertCreated();

        Bus::assertDispatchedAfterResponse(SendEnquiryNotification::class, fn ($job) => $job->enquiryId === Enquiry::sole()->id);
        expect(mockEmail()->sent)->toBe([]);
    });

    it('never lets the customer choose the recipient', function () {
        $this->postJson('/api/v1/enquiries', validEnquiry(['to' => 'victim@example.com', 'sales_inbox' => 'victim@example.com', 'bcc' => 'victim@example.com']))
            ->assertCreated();

        expect(mockEmail()->sent[0]->to)->toBe('sales@catalogue.test');
    });

    it('stores names as a single clean line', function () {
        $this->postJson('/api/v1/enquiries', validEnquiry(['name' => "Jane\r\n  \x00Doe"]))->assertCreated();

        expect(Enquiry::sole()->name)->toBe('Jane Doe');
    });

    it('keeps line breaks in the message but drops control characters', function () {
        $this->postJson('/api/v1/enquiries', validEnquiry(['message' => "Line one\r\nLine\x07 two"]))->assertCreated();

        expect(Enquiry::sole()->message)->toBe("Line one\nLine two");
    });
});

describe('when the email provider fails', function () {
    it('still saves the enquiry and thanks the customer, and records the failure', function () {
        mockEmail()->failWith = new RuntimeException('smtp down');

        $this->postJson('/api/v1/enquiries', validEnquiry())->assertCreated();

        expect(Enquiry::sole())
            ->status->toBe(EnquiryStatus::Failed)
            ->failure_reason->not->toBeNull();
    });

    it('can be re-sent once the provider recovers', function () {
        mockEmail()->failWith = new RuntimeException('smtp down');
        $this->postJson('/api/v1/enquiries', validEnquiry())->assertCreated();

        mockEmail()->failWith = null;
        $this->artisan('enquiries:resend-failed')->expectsOutputToContain('Re-queued 1')->assertSuccessful();

        expect(Enquiry::sole()->status)->toBe(EnquiryStatus::Sent)
            ->and(mockEmail()->sent)->toHaveCount(1);
    });
});

describe('validation', function () {
    it('rejects bad input with field errors and saves and sends nothing', function (array $overrides, string $field) {
        $this->postJson('/api/v1/enquiries', validEnquiry($overrides))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrors([$field], 'error.details');

        expect(Enquiry::count())->toBe(0)->and(mockEmail()->sent)->toBe([]);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'one letter name' => [['name' => 'J'], 'name'],
        'name too long' => [['name' => str_repeat('a', 101)], 'name'],
        'missing email' => [['email' => ''], 'email'],
        'malformed email' => [['email' => 'not-an-email'], 'email'],
        'header injection in email' => [['email' => "jane@example.com\r\nBcc: evil@example.com"], 'email'],
        'phone with letters' => [['phone' => 'call me maybe'], 'phone'],
        'message too long' => [['message' => str_repeat('a', 1001)], 'message'],
        'missing product' => [['product_id' => ''], 'product_id'],
        'malformed product id' => [['product_id' => '../../etc/passwd'], 'product_id'],
        'array instead of string' => [['name' => ['a', 'b']], 'name'],
    ]);

    it('returns 404 for a product that does not exist and saves nothing', function () {
        $this->postJson('/api/v1/enquiries', validEnquiry(['product_id' => '999']))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        expect(Enquiry::count())->toBe(0)->and(mockEmail()->sent)->toBe([]);
    });

    it('returns 503 and saves nothing when the product source is down', function () {
        $this->app->instance(ProductProviderInterface::class, Mockery::mock(ProductProviderInterface::class, function ($m) {
            $m->shouldReceive('find')->andThrow(new ProviderUnavailableException('down'));
        }));

        $this->postJson('/api/v1/enquiries', validEnquiry())->assertStatus(503);

        expect(Enquiry::count())->toBe(0);
    });
});

describe('abuse protection', function () {
    it('pretends to succeed for bots that fill the honeypot, but does nothing', function () {
        $this->postJson('/api/v1/enquiries', validEnquiry(['website' => 'http://spam.example']))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['reference']]);

        expect(Enquiry::count())->toBe(0)->and(mockEmail()->sent)->toBe([]);
    });

    it('throttles to 5 enquiries a minute per client', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/enquiries', validEnquiry())->assertCreated();
        }

        $this->postJson('/api/v1/enquiries', validEnquiry())
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'too_many_requests');

        expect(Enquiry::count())->toBe(5);
    });

    it('does not echo submitted personal data back', function () {
        $body = $this->postJson('/api/v1/enquiries', validEnquiry())->getContent();

        expect($body)->not->toContain('jane@example.com')->not->toContain('Jane');
    });
});
