<?php

namespace App\Services;

use App\Contracts\ProductProviderInterface;
use App\DTOs\EnquiryData;
use App\Enums\EnquiryStatus;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ProviderUnavailableException;
use App\Jobs\SendEnquiryNotification;
use App\Models\Enquiry;

/**
 * The enquiry flow. The enquiry is saved before any email is attempted, so a mail outage can
 * never lose a lead or surface as an error to the customer.
 */
class EnquiryService
{
    public function __construct(private readonly ProductProviderInterface $products) {}

    /**
     * @throws ProductNotFoundException
     * @throws ProviderUnavailableException
     */
    public function submit(EnquiryData $data): Enquiry
    {
        $product = $this->products->find($data->productId);

        $enquiry = Enquiry::create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_price_minor' => $product->price->amountMinor,
            'product_currency' => $product->price->currency,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'message' => $data->message,
            'status' => EnquiryStatus::Received,
        ]);

        // After the response is sent: the customer never waits on, or sees errors from, email.
        SendEnquiryNotification::dispatchAfterResponse($enquiry->id);

        return $enquiry;
    }
}
