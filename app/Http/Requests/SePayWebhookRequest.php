<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SePayWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::payloadRules();
    }

    public static function payloadRules(): array
    {
        return [
            'id' => ['required'],
            'gateway' => ['required', 'string', 'max:50'],
            'transactionDate' => ['required', 'date_format:Y-m-d H:i:s'],
            'accountNumber' => ['required', 'string', 'max:100'],
            'subAccount' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:64'],
            'content' => ['required', 'string', 'max:2000'],
            'transferType' => ['required', Rule::in(['in', 'out'])],
            'description' => ['nullable', 'string', 'max:4000'],
            'transferAmount' => ['required', 'integer', 'min:1'],
            'accumulated' => ['nullable', 'integer', 'min:0'],
            'referenceCode' => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json(['success' => false, 'error' => 'invalid_payload'], 400),
        );
    }
}
