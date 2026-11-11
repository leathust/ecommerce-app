<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('update', $target) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => trim((string) $this->input('username')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'first_name' => filled($this->input('first_name')) ? trim((string) $this->input('first_name')) : null,
            'last_name' => filled($this->input('last_name')) ? trim((string) $this->input('last_name')) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
            'must_change_password' => $this->boolean('must_change_password'),
        ]);
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($target)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(User::STATUSES))],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'must_change_password' => ['boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where(fn (Builder $query) => $query->where('is_admin', true)),
            ],
        ];
    }
}
