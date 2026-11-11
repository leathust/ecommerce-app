<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SystemSettingConsistencyService
{
    /**
     * @return array{
     *     missing_keys: array<int, string>,
     *     unknown_keys: array<int, string>,
     *     invalid_keys: array<int, string>,
     *     missing_assets: array<int, string>,
     *     orphaned_assets: array<int, string>
     * }
     */
    public function inspect(): array
    {
        $definitions = config('system_settings.settings', []);
        $stored = Setting::query()->pluck('setting_value', 'setting_key');
        $definitionKeys = array_keys($definitions);
        $storedKeys = $stored->keys()->all();
        $missingKeys = array_values(array_diff($definitionKeys, $storedKeys));
        $unknownKeys = array_values(array_diff($storedKeys, $definitionKeys));
        $invalidKeys = [];
        $missingAssets = [];
        $referencedAssets = [];
        $managedDirectories = [];

        foreach ($definitions as $key => $definition) {
            if (! $stored->has($key)) {
                continue;
            }

            $value = $stored->get($key);

            if (Validator::make(['value' => $value], ['value' => $definition['rules']])->fails()) {
                $invalidKeys[] = $key;
            }

            $directory = $definition['upload']['directory'] ?? null;

            if (! is_string($directory)) {
                continue;
            }

            $managedDirectories[] = $directory;

            if (! is_string($value) || $value === '') {
                continue;
            }

            if (! $this->isManagedPath($directory, $value) || ! Storage::disk('public')->exists($value)) {
                $missingAssets[] = $key;
                continue;
            }

            $referencedAssets[] = $value;
        }

        $orphanedAssets = [];

        foreach (array_unique($managedDirectories) as $directory) {
            foreach (Storage::disk('public')->allFiles($directory) as $path) {
                if (! in_array($path, $referencedAssets, true)) {
                    $orphanedAssets[] = $path;
                }
            }
        }

        foreach ([$missingKeys, $unknownKeys, $invalidKeys, $missingAssets, $orphanedAssets] as &$items) {
            sort($items);
        }
        unset($items);

        return [
            'missing_keys' => $missingKeys,
            'unknown_keys' => $unknownKeys,
            'invalid_keys' => $invalidKeys,
            'missing_assets' => $missingAssets,
            'orphaned_assets' => $orphanedAssets,
        ];
    }

    private function isManagedPath(string $directory, string $path): bool
    {
        return ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && str_starts_with($path, $directory.'/');
    }
}
