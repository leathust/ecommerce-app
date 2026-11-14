<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'array', 'max:20'],
            'category.*' => ['string', 'distinct', 'max:255', Rule::exists('categories', 'slug')->where('status', true)],
            'brand' => ['nullable', 'array', 'max:20'],
            'brand.*' => ['string', 'distinct', 'max:255', Rule::exists('brands', 'slug')->where('status', true)],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'name'])],
            'per_page' => ['nullable', Rule::in([12, 24, 48])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => is_string($this->q) ? trim($this->q) : $this->q,
            'category' => $this->normalizeList($this->category),
            'brand' => $this->normalizeList($this->brand),
            'min_price' => $this->filled('min_price') ? $this->min_price : null,
            'max_price' => $this->filled('max_price') ? $this->max_price : null,
        ]);
    }

    /** @return array<int, string> */
    private function normalizeList(mixed $value): array
    {
        $values = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $values,
        ), static fn (string $item): bool => $item !== ''));
    }
}
