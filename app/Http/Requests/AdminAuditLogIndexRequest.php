<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminAuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('permissions.manage') === true;
    }

    public function rules(): array
    {
        return [
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100', 'exists:admin_audit_logs,action'],
            'subject_type' => ['nullable', Rule::in(['user', 'role'])],
            'subject_id' => ['nullable', 'integer', 'min:1', 'required_with:subject_type'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
