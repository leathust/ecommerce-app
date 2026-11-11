<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && $this->user()?->can('update', $role) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::snake(trim((string) $this->input('name'))),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'permission_ids' => $this->input('permission_ids', []),
        ]);
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        $nameRules = $role->isReservedSystemRole()
            ? ['required', 'string', Rule::in([$role->name])]
            : [
                'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(Role::RESERVED_NAMES),
                Rule::unique('roles', 'name')->ignore($role),
            ];

        return [
            'name' => $nameRules,
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.in' => 'Tên của vai trò hệ thống không thể thay đổi.',
            'permission_ids.array' => 'Danh sách permission không hợp lệ.',
            'permission_ids.*.integer' => 'Permission được chọn không hợp lệ.',
            'permission_ids.*.distinct' => 'Danh sách permission có giá trị trùng lặp.',
            'permission_ids.*.exists' => 'Permission được chọn không còn tồn tại.',
        ];
    }
}
