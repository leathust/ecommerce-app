<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('brand') ? 'brands.update' : 'brands.create') === true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('name_vi'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($this->route('brand')?->id)],
            'image_url' => ['nullable', 'url:http,https', 'max:1024'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=10000,max_height=10000'],
            'remove_image' => ['sometimes', 'boolean'],
            'status' => ['required', 'boolean'],
        ];
    }
}
