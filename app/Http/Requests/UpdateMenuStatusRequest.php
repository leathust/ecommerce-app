<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('menus.update') === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $this->merge(['status' => filter_var(
                $this->input('status'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            )]);
        }
    }

    public function rules(): array
    {
        return ['status' => ['required', 'boolean']];
    }
}
