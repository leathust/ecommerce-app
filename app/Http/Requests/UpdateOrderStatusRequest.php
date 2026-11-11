<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'note' => filled($this->input('note')) ? trim((string) $this->input('note')) : null,
            'cancel_reason' => filled($this->input('cancel_reason')) ? trim((string) $this->input('cancel_reason')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'note' => ['nullable', 'string', 'max:1000'],
            'cancel_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái đơn hàng không hợp lệ.',
            'cancel_reason.required_if' => 'Vui lòng nhập lý do hủy đơn hàng.',
        ];
    }
}
