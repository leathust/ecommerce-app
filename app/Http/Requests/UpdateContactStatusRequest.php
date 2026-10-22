<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contacts.update') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Contact::STATUSES))],
        ];
    }
}
