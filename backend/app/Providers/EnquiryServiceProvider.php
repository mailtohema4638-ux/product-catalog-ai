<?php

namespace App\Providers;

use App\Contracts\EmailProviderInterface;
use App\Infrastructure\Email\LaravelMailEmailProvider;
use App\Infrastructure\Email\MockEmailProvider;
use App\Services\EnquiryEmailComposer;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Composition root for the enquiry side. To add an email provider (SES, SendGrid, ...),
 * implement EmailProviderInterface and add a case to the match below.
 */
class EnquiryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailProviderInterface::class, function ($app): EmailProviderInterface {
            $name = (string) config('enquiry.email_provider');

            return match ($name) {
                'mail' => new LaravelMailEmailProvider($app->make(Mailer::class)),
                'mock' => new MockEmailProvider($app->make(LoggerInterface::class)),
                default => throw new InvalidArgumentException("Unknown email provider [{$name}]."),
            };
        });

        $this->app->singleton(EnquiryEmailComposer::class, fn (): EnquiryEmailComposer => new EnquiryEmailComposer(
            (string) config('enquiry.sales_inbox'),
        ));
    }
}
