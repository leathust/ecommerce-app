<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SystemSettingAssetService
{
    public function __construct(private readonly SystemSettingService $settings) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, UploadedFile>  $uploads
     * @param  array<int, string>  $removeKeys
     * @return array<string, mixed>
     */
    public function updateGroup(
        User $actor,
        string $group,
        array $values,
        array $uploads = [],
        array $removeKeys = [],
        ?Request $request = null,
    ): array {
        $definitions = $this->settings->definitionsForGroup($group);
        $assetDefinitions = array_filter(
            $definitions,
            static fn (array $definition): bool => $definition['type'] === 'image' && is_array($definition['upload']),
        );
        $assetKeys = array_keys($assetDefinitions);

        $unknownUploads = array_diff(array_keys($uploads), $assetKeys);
        $unknownRemovals = array_diff($removeKeys, $assetKeys);

        if ($unknownUploads !== [] || $unknownRemovals !== []) {
            throw new InvalidArgumentException('System setting asset key is not allowed for this group.');
        }

        if (array_intersect(array_keys($uploads), $removeKeys) !== []) {
            throw new InvalidArgumentException('An asset cannot be uploaded and removed in the same request.');
        }

        $newPaths = [];
        $oldPaths = [];

        try {
            foreach ($uploads as $key => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    throw new RuntimeException("Invalid uploaded asset for [{$key}].");
                }

                $oldPaths[$key] = $this->settings->get($key);
                $path = $file->store($assetDefinitions[$key]['upload']['directory'], 'public');

                if (! is_string($path) || $path === '') {
                    throw new RuntimeException("Unable to store system setting asset [{$key}].");
                }

                $newPaths[$key] = $path;
                $values[$key] = $path;
            }

            foreach ($removeKeys as $key) {
                $oldPaths[$key] = $this->settings->get($key);
                $values[$key] = null;
            }

            $updated = $this->settings->updateGroup($actor, $group, $values, $request);
        } catch (Throwable $exception) {
            foreach ($newPaths as $key => $path) {
                if ($this->isManagedPath($key, $path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            throw $exception;
        }

        foreach ($oldPaths as $key => $path) {
            if (is_string($path)
                && $path !== ($newPaths[$key] ?? null)
                && $this->isManagedPath($key, $path)) {
                Storage::disk('public')->delete($path);
            }
        }

        return $updated;
    }

    public function publicUrl(string $key, mixed $path): ?string
    {
        return is_string($path) && $this->isManagedPath($key, $path)
            ? asset('storage/'.ltrim($path, '/'))
            : null;
    }

    public function isManagedPath(string $key, string $path): bool
    {
        $definition = $this->settings->definitions()[$key] ?? null;
        $directory = $definition['upload']['directory'] ?? null;

        return is_string($directory)
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && str_starts_with($path, $directory.'/');
    }
}
