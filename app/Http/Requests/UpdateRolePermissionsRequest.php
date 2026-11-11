<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && $this->user()?->can('update', $role) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permission_ids' => $this->input('permission_ids', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'permission_ids.array' => 'Danh sách permission không hợp lệ.',
            'permission_ids.*.integer' => 'Permission được chọn không hợp lệ.',
            'permission_ids.*.distinct' => 'Danh sách permission có giá trị trùng lặp.',
            'permission_ids.*.exists' => 'Permission được chọn không còn tồn tại.',
        ];
    }
}
