<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        foreach (['q', 'status', 'payment_status', 'payment_method', 'date_from', 'date_to'] as $field) {
            $value = $this->input($field);
            $normalized[$field] = filled($value) ? trim((string) $value) : null;
        }
        $this->merge($normalized ?? []);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Order::STATUSES))],
            'payment_status' => ['nullable', Rule::in(array_keys(Order::PAYMENT_STATUSES))],
            'payment_method' => ['nullable', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
