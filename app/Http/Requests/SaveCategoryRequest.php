<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('category') ? 'categories.update' : 'categories.create') === true;
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
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($this->route('category')?->id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image_url' => ['nullable', 'url:http,https', 'max:1024'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=10000,max_height=10000'],
            'remove_image' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('parent_id') || ! $this->filled('parent_id')) {
                return;
            }

            $category = $this->route('category');
            $parentId = (int) $this->input('parent_id');
            $visited = [];

            while ($parentId) {
                if (($category && $parentId === $category->id) || isset($visited[$parentId])) {
                    $validator->errors()->add('parent_id', 'Danh mục cha không được là chính nó hoặc tạo vòng lặp cha–con.');

                    return;
                }
                $visited[$parentId] = true;
                $parentId = (int) Category::whereKey($parentId)->value('parent_id');
            }
        });
    }
}
