<?php

namespace App\Contracts;

use App\DTOs\EmailMessage;
use App\Exceptions\EmailDeliveryException;

/** Transport-only: delivers a message composed elsewhere. SES, SendGrid etc. implement this too. */
interface EmailProviderInterface
{
    /** @throws EmailDeliveryException */
    public function send(EmailMessage $message): void;
}
