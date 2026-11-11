<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('menus.update') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->filled('parent_id') ? $this->input('parent_id') : null,
            'source_parent_id' => $this->filled('source_parent_id') ? $this->input('source_parent_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'location' => ['required', Rule::in(array_keys(config('menus.locations', [])))],
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'source_location' => ['required', Rule::in(array_keys(config('menus.locations', [])))],
            'source_parent_id' => ['nullable', 'integer', 'exists:menus,id'],
        ];
    }
}
