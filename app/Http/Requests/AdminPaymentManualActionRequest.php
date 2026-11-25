<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminPaymentManualActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason', '')),
            'payment_attempt_id' => filled($this->input('payment_attempt_id')) ? (int) $this->input('payment_attempt_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'payment_attempt_id' => ['nullable', 'integer', 'exists:payment_attempts,id'],
        ];
    }
}
