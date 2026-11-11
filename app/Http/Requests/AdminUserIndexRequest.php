<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.view') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => filled($this->input('q')) ? trim((string) $this->input('q')) : null,
            'status' => filled($this->input('status')) ? trim((string) $this->input('status')) : null,
            'role_id' => filled($this->input('role_id')) ? $this->input('role_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(User::STATUSES))],
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where(fn (Builder $query) => $query->where('is_admin', true)),
            ],
        ];
    }
}
