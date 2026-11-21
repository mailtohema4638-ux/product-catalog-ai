<?php

namespace App\Http\Resources;

use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Enquiry $resource */
class EnquiryResource extends JsonResource
{
    /** Only a reference goes back to the customer: no echo of what they submitted. */
    public function toArray(Request $request): array
    {
        return ['reference' => $this->resource->id];
    }
}
