<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contacts.update') === true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'reply_message' => ['required', 'string', 'max:10000'],
        ];
    }
}
