<?php

namespace App\Http\Requests;

use App\Models\HomeBanner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHomeBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('homeBanner') ? 'banners.update' : 'banners.create') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->boolean('status'),
            'link_url' => filled($this->input('link_url')) ? ltrim(trim((string) $this->input('link_url')), '/') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', Rule::in(HomeBanner::PLACEMENTS)],
            'title_vi' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'eyebrow_vi' => ['nullable', 'string', 'max:120'],
            'eyebrow_en' => ['nullable', 'string', 'max:120'],
            'description_vi' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'button_vi' => ['nullable', 'string', 'max:80'],
            'button_en' => ['nullable', 'string', 'max:80'],
            'image_url' => ['nullable', 'url:http,https', 'max:1024'],
            'link_url' => ['nullable', 'string', 'max:512', 'regex:~^(?:shop(?:\?(?:category|brand)=[a-z0-9]+(?:-[a-z0-9]+)*)?|products/[a-z0-9]+(?:-[a-z0-9]+)*)$~'],
            'theme' => ['required', Rule::in(HomeBanner::THEMES)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'link_url.regex' => 'Liên kết chỉ được trỏ nội bộ tới shop, shop?category=slug, shop?brand=slug hoặc products/slug.',
            'ends_at.after' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
        ];
    }
}
