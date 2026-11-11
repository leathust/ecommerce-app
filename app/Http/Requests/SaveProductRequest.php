<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('product') ? 'products.update' : 'products.create') === true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('name_vi'))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;
        $imageIds = $this->route('product')?->images()->pluck('id')->all() ?? [];
        $primaryChoices = array_map(fn ($id) => 'existing:'.$id, $imageIds);
        foreach (array_keys($this->file('images', [])) as $index) {
            $primaryChoices[] = 'new:'.$index;
        }

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)],
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($id)],
            'description_vi' => ['nullable', 'string', 'max:60000'],
            'description_en' => ['nullable', 'string', 'max:60000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
            'quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'status' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=10000,max_height=10000'],
            'image_meta' => ['sometimes', 'array'],
            'image_meta.*' => ['array:alt_text,sort_order'],
            'image_meta.*.alt_text' => ['nullable', 'string', 'max:255'],
            'image_meta.*.sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'new_alt' => ['sometimes', 'array', 'max:10'],
            'new_alt.*' => ['nullable', 'string', 'max:255'],
            'delete_images' => ['sometimes', 'array'],
            'delete_images.*' => ['integer', Rule::in($imageIds)],
            'primary_image' => ['nullable', 'string', Rule::in($primaryChoices)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $ids = $this->route('product')?->images()->pluck('id')->map(fn ($id) => (string) $id)->all() ?? [];
            $metadata = $this->input('image_meta', []);
            if (is_array($metadata) && array_diff(array_map('strval', array_keys($metadata)), $ids)) {
                $validator->errors()->add('image_meta', 'Ảnh không thuộc sản phẩm này.');
            }
            $primary = $this->input('primary_image');
            if (is_string($primary) && str_starts_with($primary, 'existing:')
                && in_array(substr($primary, 9), array_map('strval', (array) $this->input('delete_images', [])), true)) {
                $validator->errors()->add('primary_image', 'Không thể chọn ảnh đang xóa làm ảnh đại diện.');
            }
        });
    }

    public function attributes(): array
    {
        return ['category_id' => 'danh mục', 'brand_id' => 'thương hiệu', 'name_vi' => 'tên tiếng Việt', 'name_en' => 'tên tiếng Anh', 'price' => 'giá', 'quantity' => 'số lượng'];
    }
}
