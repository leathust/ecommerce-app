<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.update') === true;
    }

    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');

        if (! is_array($settings)) {
            return;
        }

        foreach ($this->groupDefinitions() as $key => $definition) {
            if ($definition['type'] === 'bool') {
                $settings[$key] = $settings[$key] ?? false;
            }
        }

        $this->merge(['settings' => $settings]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
            'assets' => ['nullable', 'array'],
            'remove_assets' => ['nullable', 'array'],
            'remove_assets.*' => ['boolean'],
        ];
    }

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $group = (string) $this->route('group');
            $groups = config('system_settings.groups', []);

            if (! array_key_exists($group, $groups)) {
                $validator->errors()->add('settings', 'Nhóm cài đặt không hợp lệ.');

                return;
            }

            $definitions = $this->groupDefinitions();
            $settings = $this->input('settings', []);
            $assetDefinitions = array_filter(
                $definitions,
                static fn (array $definition): bool => $definition['type'] === 'image' && is_array($definition['upload']),
            );

            if (! is_array($settings)) {
                return;
            }

            $unknown = array_diff(array_keys($settings), array_keys($definitions));

            if ($unknown !== []) {
                $validator->errors()->add(
                    'settings',
                    'Có cài đặt không hợp lệ hoặc không thuộc nhóm này: '.implode(', ', $unknown),
                );
            }

            foreach (array_intersect_key($settings, $definitions) as $key => $value) {
                $definition = $definitions[$key];
                $fieldValidator = Validator::make(
                    ['value' => $value],
                    ['value' => $definition['rules']],
                    [],
                    ['value' => $definition['label']],
                );

                foreach ($fieldValidator->errors()->all() as $message) {
                    $validator->errors()->add('settings.'.$key, $message);
                }
            }

            $uploads = $this->file('assets', []);
            $uploads = is_array($uploads) ? $uploads : [];
            $removeAssets = $this->input('remove_assets', []);
            $removeAssets = is_array($removeAssets) ? $removeAssets : [];
            $unknownAssetKeys = array_unique(array_merge(
                array_diff(array_keys($uploads), array_keys($assetDefinitions)),
                array_diff(array_keys($removeAssets), array_keys($assetDefinitions)),
            ));

            if ($unknownAssetKeys !== []) {
                $validator->errors()->add(
                    'assets',
                    'Tài sản không hợp lệ hoặc không thuộc nhóm này: '.implode(', ', $unknownAssetKeys),
                );
            }

            foreach (array_intersect_key($uploads, $assetDefinitions) as $key => $file) {
                $assetValidator = Validator::make(
                    ['file' => $file],
                    ['file' => $assetDefinitions[$key]['upload']['rules']],
                    [],
                    ['file' => $assetDefinitions[$key]['label']],
                );

                foreach ($assetValidator->errors()->all() as $message) {
                    $validator->errors()->add('assets.'.$key, $message);
                }

                if ($key === 'site.favicon' && strtolower($file->getClientOriginalExtension()) === 'png') {
                    $dimensionValidator = Validator::make(
                        ['file' => $file],
                        ['file' => ['dimensions:max_width=512,max_height=512']],
                        [],
                        ['file' => $assetDefinitions[$key]['label']],
                    );

                    foreach ($dimensionValidator->errors()->all() as $message) {
                        $validator->errors()->add('assets.'.$key, $message);
                    }
                }
            }

            $requestedRemovals = array_keys(array_filter($removeAssets));

            foreach (array_intersect(array_keys($uploads), $requestedRemovals) as $key) {
                $validator->errors()->add(
                    'assets.'.$key,
                    'Không thể đồng thời tải lên và xóa cùng một tài sản.',
                );
            }
        });
    }

    /** @return array<string, array<string, mixed>> */
    private function groupDefinitions(): array
    {
        $group = (string) $this->route('group');

        return array_filter(
            config('system_settings.settings', []),
            static fn (array $definition): bool => $definition['group'] === $group,
        );
    }
}
