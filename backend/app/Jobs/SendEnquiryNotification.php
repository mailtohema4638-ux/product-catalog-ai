<?php

namespace App\Jobs;

use App\Contracts\EmailProviderInterface;
use App\Enums\EnquiryStatus;
use App\Exceptions\EmailDeliveryException;
use App\Models\Enquiry;
use App\Services\EnquiryEmailComposer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Psr\Log\LoggerInterface;

/**
 * Emails the sales inbox about a saved enquiry. Delivery failure is recorded on the enquiry
 * (status "failed") rather than thrown, so it is visible to operators and recoverable with
 * `php artisan enquiries:resend-failed`. Idempotent: an already-sent enquiry is skipped.
 */
class SendEnquiryNotification implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public readonly string $enquiryId) {}

    public function handle(EmailProviderInterface $email, EnquiryEmailComposer $composer, LoggerInterface $logger): void
    {
        $enquiry = Enquiry::find($this->enquiryId);

        if ($enquiry === null || $enquiry->status === EnquiryStatus::Sent) {
            return;
        }

        try {
            $email->send($composer->compose($enquiry));
        } catch (EmailDeliveryException $e) {
            $enquiry->markFailed($e->getMessage());
            // Ids only: customer details never go to the logs. The cause is for operators to diagnose.
            $logger->error('Enquiry notification failed.', [
                'enquiry_id' => $enquiry->id,
                'reason' => $e->getMessage(),
                'cause' => $e->getPrevious()?->getMessage(),
            ]);

            return;
        }

        $enquiry->markSent();
    }
}
