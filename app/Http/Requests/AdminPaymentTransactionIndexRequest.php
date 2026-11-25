<?php

namespace App\Http\Requests;

use App\Models\SePayTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPaymentTransactionIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => filled($this->input('q')) ? trim((string) $this->input('q')) : null,
            'environment' => filled($this->input('environment')) ? trim((string) $this->input('environment')) : null,
            'match_status' => filled($this->input('match_status')) ? trim((string) $this->input('match_status')) : null,
            'match_reason' => filled($this->input('match_reason')) ? trim((string) $this->input('match_reason')) : null,
            'date_from' => filled($this->input('date_from')) ? trim((string) $this->input('date_from')) : null,
            'date_to' => filled($this->input('date_to')) ? trim((string) $this->input('date_to')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'environment' => ['nullable', Rule::in(config('sepay.supported_environments', []))],
            'match_status' => ['nullable', Rule::in(SePayTransaction::MATCH_STATUSES)],
            'match_reason' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
