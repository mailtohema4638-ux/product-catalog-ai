<?php

namespace App\Services;

use App\DTOs\EmailMessage;
use App\Models\Enquiry;

/**
 * Decides what the sales email says. Plain text on purpose: nothing a customer types is ever
 * interpreted as markup. Header-bound values (subject, reply-to name) are forced to one line.
 */
class EnquiryEmailComposer
{
    public function __construct(private readonly string $salesInbox) {}

    public function compose(Enquiry $enquiry): EmailMessage
    {
        $price = sprintf('%s %s', $enquiry->product_currency, number_format($enquiry->product_price_minor / 100, 2, '.', ''));

        $body = implode("\n", [
            'New product enquiry',
            '',
            "Reference: {$enquiry->id}",
            "Product:   {$this->singleLine($enquiry->product_title, 200)} (ID {$enquiry->product_id})",
            "Price:     {$price}",
            '',
            'Customer',
            "Name:      {$this->singleLine($enquiry->name, 100)}",
            "Email:     {$enquiry->email}",
            'Phone:     '.($enquiry->phone ?? 'not provided'),
            '',
            'Message',
            $enquiry->message ?? '(none)',
        ]);

        return new EmailMessage(
            to: $this->salesInbox,
            subject: 'New enquiry: '.$this->singleLine($enquiry->product_title, 100),
            body: $body,
            replyToEmail: $enquiry->email,
            replyToName: $this->singleLine($enquiry->name, 100),
        );
    }

    private function singleLine(string $value, int $max): string
    {
        $clean = trim(preg_replace('/[\p{Cc}\s]+/u', ' ', $value) ?? '');

        return mb_substr($clean, 0, $max);
    }
}
