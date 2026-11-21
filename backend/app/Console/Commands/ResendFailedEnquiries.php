<?php

namespace App\Console\Commands;

use App\Enums\EnquiryStatus;
use App\Jobs\SendEnquiryNotification;
use App\Models\Enquiry;
use Illuminate\Console\Command;

class ResendFailedEnquiries extends Command
{
    /** An enquiry still "received" after this long lost its notification job (crash, timeout, restart). */
    private const STUCK_AFTER_MINUTES = 10;

    protected $signature = 'enquiries:resend-failed';

    protected $description = 'Retry the sales notification for enquiries whose email failed, or never ran';

    public function handle(): int
    {
        $count = 0;

        Enquiry::query()
            ->where(function ($query): void {
                $query->where('status', EnquiryStatus::Failed)
                    ->orWhere(function ($query): void {
                        $query->where('status', EnquiryStatus::Received)
                            ->where('created_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES));
                    });
            })
            // Keyset paging: safe even though each row's status changes while we iterate.
            ->lazyById()
            ->each(function (Enquiry $enquiry) use (&$count): void {
                $enquiry->update(['status' => EnquiryStatus::Received]);
                SendEnquiryNotification::dispatch($enquiry->id);
                $count++;
            });

        $this->info("Re-queued {$count} enquiries (failed, or unsent for over ".self::STUCK_AFTER_MINUTES.' minutes).');

        return self::SUCCESS;
    }
}
