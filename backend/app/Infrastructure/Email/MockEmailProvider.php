<?php

namespace App\Infrastructure\Email;

use App\Contracts\EmailProviderInterface;
use App\DTOs\EmailMessage;
use App\Exceptions\EmailDeliveryException;
use Psr\Log\LoggerInterface;
use Throwable;

/** Delivers nothing: records messages in memory. For tests and for running without any mail server. */
class MockEmailProvider implements EmailProviderInterface
{
    /** @var list<EmailMessage> */
    public array $sent = [];

    /** Set to make the next sends fail, to exercise error handling. */
    public ?Throwable $failWith = null;

    public function __construct(private readonly ?LoggerInterface $logger = null) {}

    public function send(EmailMessage $message): void
    {
        if ($this->failWith !== null) {
            throw new EmailDeliveryException('Mock email provider was told to fail.', 0, $this->failWith);
        }

        $this->sent[] = $message;
        $this->logger?->info('Mock email recorded (not delivered).', ['subject' => $message->subject]);
    }
}
