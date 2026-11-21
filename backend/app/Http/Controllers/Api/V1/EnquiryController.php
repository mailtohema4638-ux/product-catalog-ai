<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Http\Resources\EnquiryResource;
use App\Services\EnquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class EnquiryController extends Controller
{
    public function __construct(private readonly EnquiryService $enquiries) {}

    public function store(StoreEnquiryRequest $request): JsonResponse
    {
        // Bots fill the hidden field: answer as if it worked, but do nothing.
        if ($request->isSpam()) {
            return response()->json(['data' => ['reference' => (string) Str::ulid()]], 201);
        }

        return (new EnquiryResource($this->enquiries->submit($request->toData())))
            ->response()
            ->setStatusCode(201);
    }
}
