<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class AdminRoleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Role::class) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => filled($this->input('q')) ? trim((string) $this->input('q')) : null,
        ]);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100']];
    }
}
