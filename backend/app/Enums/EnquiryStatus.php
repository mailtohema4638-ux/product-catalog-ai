<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    /** Saved; the sales notification has not been delivered yet. */
    case Received = 'received';

    case Sent = 'sent';

    /** Delivery failed. The enquiry is safe in the database and can be re-sent. */
    case Failed = 'failed';
}
