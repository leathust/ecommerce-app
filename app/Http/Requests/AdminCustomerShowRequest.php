<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCustomerShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_status' => filled($this->input('order_status'))
                ? trim((string) $this->input('order_status'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'order_status' => ['nullable', Rule::in(array_keys(Order::STATUSES))],
        ];
    }
}
