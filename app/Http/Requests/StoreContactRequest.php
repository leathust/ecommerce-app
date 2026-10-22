<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'reason' => ['required', Rule::in(array_keys(Contact::REASONS))],
            'order_code' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Vui lòng nhập tên.',
            'last_name.required' => 'Vui lòng nhập họ.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'reason.required' => 'Vui lòng chọn nội dung cần hỗ trợ.',
            'reason.in' => 'Nội dung hỗ trợ không hợp lệ.',
            'message.required' => 'Vui lòng nhập nội dung liên hệ.',
            'message.max' => 'Nội dung liên hệ không được vượt quá 5.000 ký tự.',
            'website.max' => 'Không thể gửi biểu mẫu này.',
        ];
    }
}
