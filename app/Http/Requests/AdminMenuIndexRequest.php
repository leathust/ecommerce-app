<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminMenuIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('menus.view') === true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', Rule::in(array_keys(config('menus.locations', [])))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
