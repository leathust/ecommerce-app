<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminContactIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contacts.view') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Contact::STATUSES))],
            'reason' => ['nullable', Rule::in(array_keys(Contact::REASONS))],
        ];
    }
}
