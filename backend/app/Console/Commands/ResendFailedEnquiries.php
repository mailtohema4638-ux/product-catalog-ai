<?php

namespace App\Console\Commands;

use App\Enums\EnquiryStatus;
use App\Jobs\SendEnquiryNotification;
use App\Models\Enquiry;
use Illuminate\Console\Command;

class ResendFailedEnquiries extends Command
{
    protected $signature = 'enquiries:resend-failed';

    protected $description = 'Retry the sales notification for enquiries whose email failed to send';

    public function handle(): int
    {
        $count = 0;

        Enquiry::where('status', EnquiryStatus::Failed)->each(function (Enquiry $enquiry) use (&$count): void {
            $enquiry->update(['status' => EnquiryStatus::Received]);
            SendEnquiryNotification::dispatch($enquiry->id);
            $count++;
        });

        $this->info("Re-queued {$count} failed enquiries.");

        return self::SUCCESS;
    }
}
