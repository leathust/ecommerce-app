<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteProductsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:15'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng chọn ít nhất một sản phẩm.',
            'ids.max' => 'Mỗi lần chỉ được xóa tối đa 15 sản phẩm trên trang hiện tại.',
            'ids.*.distinct' => 'Danh sách sản phẩm không được trùng lặp.',
        ];
    }
}
