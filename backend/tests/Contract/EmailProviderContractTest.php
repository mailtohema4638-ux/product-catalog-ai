<?php

use App\Contracts\EmailProviderInterface;
use App\DTOs\EmailMessage;
use App\Infrastructure\Email\LaravelMailEmailProvider;
use App\Infrastructure\Email\MockEmailProvider;
use Symfony\Component\Mime\Email;

/*
 * The contract every EmailProviderInterface implementation must satisfy.
 * To add a provider (SES, SendGrid, ...), add it here and make it pass.
 */
function makeEmailProvider(string $kind): EmailProviderInterface
{
    return match ($kind) {
        'mock' => new MockEmailProvider,
        'mail' => new LaravelMailEmailProvider(app('mailer')),
    };
}

/** @return list<array{to: string, subject: string, body: string, replyTo: ?string}> */
function deliveredEmails(string $kind, EmailProviderInterface $provider): array
{
    if ($kind === 'mock') {
        return array_map(fn (EmailMessage $m): array => [
            'to' => $m->to, 'subject' => $m->subject, 'body' => $m->body, 'replyTo' => $m->replyToEmail,
        ], $provider->sent);
    }

    return app('mailer')->getSymfonyTransport()->messages()->map(function ($sent): array {
        /** @var Email $email */
        $email = $sent->getOriginalMessage();

        return [
            'to' => $email->getTo()[0]->getAddress(),
            'subject' => $email->getSubject(),
            'body' => trim($email->getTextBody()),
            'replyTo' => isset($email->getReplyTo()[0]) ? $email->getReplyTo()[0]->getAddress() : null,
        ];
    })->all();
}

beforeEach(fn () => config(['mail.default' => 'array']));

it('delivers a plain-text message to the recipient', function (string $kind) {
    $provider = makeEmailProvider($kind);

    $provider->send(new EmailMessage('sales@catalogue.test', 'New enquiry: Hammer', "Hello\nWorld"));

    expect(deliveredEmails($kind, $provider))->toBe([[
        'to' => 'sales@catalogue.test',
        'subject' => 'New enquiry: Hammer',
        'body' => "Hello\nWorld",
        'replyTo' => null,
    ]]);
})->with(['mock', 'mail']);

it('sets the reply-to address so sales can answer the customer', function (string $kind) {
    $provider = makeEmailProvider($kind);

    $provider->send(new EmailMessage('sales@catalogue.test', 'Subject', 'Body', 'jane@example.com', 'Jane Doe'));

    expect(deliveredEmails($kind, $provider)[0]['replyTo'])->toBe('jane@example.com');
})->with(['mock', 'mail']);
