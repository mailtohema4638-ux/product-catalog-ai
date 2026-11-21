<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $term = $this->query('q');

        // Trim and collapse whitespace so " red   shirt " behaves like "red shirt".
        if (is_string($term)) {
            $this->merge(['q' => preg_replace('/\s+/u', ' ', trim($term))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'category' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
        ];
    }

    public function term(): string
    {
        return $this->validated('q');
    }

    public function categorySlug(): ?string
    {
        return $this->validated('category');
    }
}
