<?php

namespace App\Infrastructure\Email;

use App\Contracts\EmailProviderInterface;
use App\DTOs\EmailMessage;
use App\Exceptions\EmailDeliveryException;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Throwable;

/**
 * Sends through Laravel's mailer; the actual transport (Mailpit/SMTP now, SES later)
 * is chosen by MAIL_MAILER, so moving to SES is configuration, not code.
 */
class LaravelMailEmailProvider implements EmailProviderInterface
{
    public function __construct(private readonly Mailer $mailer) {}

    public function send(EmailMessage $message): void
    {
        try {
            $this->mailer->raw($message->body, function (Message $mail) use ($message): void {
                $mail->to($message->to)->subject($message->subject);

                if ($message->replyToEmail !== null) {
                    $mail->replyTo($message->replyToEmail, $message->replyToName);
                }
            });
        } catch (Throwable $e) {
            throw new EmailDeliveryException('The email could not be delivered.', 0, $e);
        }
    }
}
