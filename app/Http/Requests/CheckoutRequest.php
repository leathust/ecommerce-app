<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'regex:/^[0-9+() .-]{8,30}$/'],
            'recipient_email' => [
                Rule::requiredIf(fn (): bool => $this->input('payment_method') === 'sepay' && $this->user() === null),
                'nullable',
                'email:rfc',
                'max:255',
            ],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in(['cod', 'sepay'])],
            'idempotency_key' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required' => __('frontend.checkout.name_required'),
            'recipient_phone.required' => __('frontend.checkout.phone_required'),
            'recipient_phone.regex' => __('frontend.checkout.phone_invalid'),
            'recipient_email.required' => __('frontend.checkout.email_required_for_sepay'),
            'recipient_email.email' => __('frontend.checkout.email_invalid'),
            'shipping_address.required' => __('frontend.checkout.address_required'),
            'payment_method.in' => __('frontend.checkout.payment_invalid'),
            'idempotency_key.*' => __('frontend.checkout.request_invalid'),
        ];
    }
}
