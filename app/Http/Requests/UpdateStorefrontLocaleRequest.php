<?php

namespace App\Http\Requests;

use App\Support\StorefrontLocaleResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorefrontLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(StorefrontLocaleResolver $resolver): array
    {
        return [
            'locale' => ['required', 'string', Rule::in($resolver->supportedLocales())],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'locale.required' => 'Vui lòng chọn ngôn ngữ.',
            'locale.in' => 'Ngôn ngữ được chọn không được hỗ trợ.',
            'return_url.max' => 'Đường dẫn quay lại quá dài.',
        ];
    }
}
