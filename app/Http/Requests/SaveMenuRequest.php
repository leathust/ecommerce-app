<?php

namespace App\Http\Requests;

use App\Exceptions\MenuOperationException;
use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SaveMenuRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->filled('parent_id') ? $this->input('parent_id') : null,
            'route_name' => $this->filled('route_name') ? trim((string) $this->input('route_name')) : null,
            'url' => $this->filled('url') ? trim((string) $this->input('url')) : null,
            'status' => $this->boolean('status'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'location' => ['required', Rule::in(array_keys(config('menus.locations', [])))],
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'link_type' => ['required', Rule::in(array_keys(config('menus.link_types', [])))],
            'route_name' => [
                Rule::requiredIf(fn (): bool => $this->input('link_type') === 'route'),
                'nullable',
                'string',
                Rule::in(array_keys(config('menus.routes', []))),
            ],
            'url' => [
                Rule::requiredIf(fn (): bool => $this->input('link_type') === 'url'),
                'nullable',
                'string',
                'max:2048',
            ],
            'target' => ['required', Rule::in(array_keys(config('menus.targets', [])))],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['parent_id', 'location'])) {
                return;
            }

            $subject = $this->route('menu');

            try {
                app(MenuService::class)->assertParentAllowed(
                    $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
                    (string) $this->input('location'),
                    $subject instanceof Menu ? $subject : null,
                );
            } catch (MenuOperationException $exception) {
                $validator->errors()->add('parent_id', $exception->getMessage());
            }
        });
    }
}
