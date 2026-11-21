<?php

namespace App\Http\Requests;

use App\DTOs\EnquiryData;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->singleLine($this->input('name')),
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'phone' => $this->singleLine($this->input('phone')),
            'message' => $this->multiLine($this->input('message')),
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]{5,30}$/'],
            'message' => ['nullable', 'string', 'max:1000'],
            // Honeypot: hidden from people, filled in by bots.
            'website' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function isSpam(): bool
    {
        return filled($this->input('website'));
    }

    public function toData(): EnquiryData
    {
        $data = $this->validated();

        return new EnquiryData(
            productId: $data['product_id'],
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            message: $data['message'] ?? null,
        );
    }

    /** One line, no control characters: safe to place in headers and logs. */
    private function singleLine(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $clean = trim(preg_replace('/[\p{Cc}\s]+/u', ' ', $value) ?? '');

        return $clean === '' ? null : $clean;
    }

    /** Keeps line breaks and tabs, drops other control characters. */
    private function multiLine(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $clean = trim(preg_replace('/[^\P{Cc}\n\t]/u', '', str_replace("\r\n", "\n", $value)) ?? '');

        return $clean === '' ? null : $clean;
    }
}
