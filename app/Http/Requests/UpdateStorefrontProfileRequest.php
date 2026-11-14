<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStorefrontProfileRequest extends FormRequest
{
    protected $errorBag = 'profile';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->normalizeNullableText($this->input('first_name')),
            'last_name' => $this->normalizeNullableText($this->input('last_name')),
            'phone' => $this->normalizeNullableText($this->input('phone')),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.string' => __('frontend.profile.first_name_string'),
            'first_name.max' => __('frontend.profile.first_name_max'),
            'last_name.string' => __('frontend.profile.last_name_string'),
            'last_name.max' => __('frontend.profile.last_name_max'),
            'phone.string' => __('frontend.profile.phone_string'),
            'phone.max' => __('frontend.profile.phone_max'),
        ];
    }

    private function normalizeNullableText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value === null ? null : $value;
        }

        $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $normalized === '' ? null : $normalized;
    }
}
