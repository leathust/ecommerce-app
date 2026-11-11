<?php

namespace App\Services;

use App\Exceptions\IamOperationException;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SystemSettingService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return config('system_settings.settings', []);
    }

    /** @return array<string, array<string, string>> */
    public function groups(): array
    {
        return config('system_settings.groups', []);
    }

    /** @return array<string, array<string, mixed>> */
    public function definitionsForGroup(string $group): array
    {
        $this->assertKnownGroup($group);

        return array_filter(
            $this->definitions(),
            static fn (array $definition): bool => $definition['group'] === $group,
        );
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::rememberForever($this->cacheKey(), function (): array {
            $stored = Setting::query()->pluck('setting_value', 'setting_key');
            $values = [];

            foreach ($this->definitions() as $key => $definition) {
                $values[$key] = $this->castStoredValue(
                    $definition,
                    $stored->has($key) ? $stored->get($key) : null,
                );
            }

            return $values;
        });
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        if (! array_key_exists($key, $this->definitions())) {
            if (func_num_args() >= 2) {
                return $fallback;
            }

            throw new InvalidArgumentException("Unknown system setting [{$key}].");
        }

        return $this->all()[$key];
    }

    /** @param array<int, string> $keys
     *  @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        $unknown = array_diff($keys, array_keys($this->definitions()));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown system settings: '.implode(', ', $unknown));
        }

        return Arr::only($this->all(), $keys);
    }

    /** @return array<string, mixed> */
    public function publicValues(): array
    {
        $publicKeys = array_keys(array_filter(
            $this->definitions(),
            static fn (array $definition): bool => $definition['public'] === true,
        ));

        return Arr::only($this->all(), $publicKeys);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updateGroup(
        User $actor,
        string $group,
        array $values,
        ?Request $request = null,
    ): array {
        if (! $actor->hasPermission('settings.update')) {
            throw IamOperationException::forbidden('Bạn không có quyền cập nhật cài đặt hệ thống.');
        }

        $definitions = $this->definitionsForGroup($group);
        $unknown = array_diff(array_keys($values), array_keys($definitions));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => 'Cài đặt không hợp lệ hoặc không thuộc nhóm đang cập nhật: '.implode(', ', $unknown),
            ]);
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            $definition = $definitions[$key];
            Validator::make(
                ['value' => $value],
                ['value' => $definition['rules']],
                [],
                ['value' => $definition['label']],
            )->validate();
            $normalized[$key] = $this->normalizeInputValue($definition, $value);
        }

        $changed = DB::transaction(function () use ($actor, $group, $normalized, $definitions, $request): array {
            $rows = Setting::query()
                ->whereIn('setting_key', array_keys($normalized))
                ->lockForUpdate()
                ->get()
                ->keyBy('setting_key');
            $before = [];
            $after = [];

            foreach ($normalized as $key => $value) {
                $current = $this->castStoredValue(
                    $definitions[$key],
                    $rows->get($key)?->setting_value,
                );

                if ($current === $value) {
                    continue;
                }

                $before[$key] = $current;
                $after[$key] = $value;

                Setting::query()->updateOrCreate(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $this->serializeValue($definitions[$key], $value),
                        'updated_by' => $actor->getKey(),
                    ],
                );
            }

            if ($after !== []) {
                ksort($before);
                ksort($after);

                $this->audit->recordSystemEvent(
                    $actor,
                    'settings.group_updated',
                    Setting::class,
                    ['group' => $group, 'values' => $before],
                    ['group' => $group, 'values' => $after],
                    $request,
                );
            }

            return $after;
        });

        if ($changed !== []) {
            Cache::forget($this->cacheKey());
        }

        return Arr::only($this->all(), array_keys($definitions));
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function cacheKey(): string
    {
        return (string) config('system_settings.cache_key', 'system_settings.typed_values.v1');
    }

    private function assertKnownGroup(string $group): void
    {
        if (! array_key_exists($group, $this->groups())) {
            throw new InvalidArgumentException("Unknown system setting group [{$group}].");
        }
    }

    private function castStoredValue(array $definition, mixed $value): mixed
    {
        if ($value === null || ($value === '' && $definition['empty_behavior'] === 'fallback')) {
            return $definition['default'];
        }

        return match ($definition['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? $definition['default'],
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? (int) $value
                : $definition['default'],
            'decimal' => is_numeric($value)
                ? $this->normalizeDecimal((string) $value)
                : $definition['default'],
            'enum' => array_key_exists((string) $value, $definition['options'])
                ? (string) $value
                : $definition['default'],
            default => (string) $value,
        };
    }

    private function normalizeInputValue(array $definition, mixed $value): mixed
    {
        if ($value === null) {
            return $definition['empty_behavior'] === 'fallback' ? $definition['default'] : null;
        }

        return match ($definition['type']) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'decimal' => $this->normalizeDecimal((string) $value),
            'string', 'textarea', 'email', 'url', 'image', 'enum' => trim((string) $value),
            default => throw new InvalidArgumentException("Unsupported system setting type [{$definition['type']}]."),
        };
    }

    private function serializeValue(array $definition, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $definition['type'] === 'bool'
            ? ($value ? '1' : '0')
            : (string) $value;
    }

    private function normalizeDecimal(string $value): string
    {
        $value = trim($value);
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim(substr($fraction, 0, 2), '0');

        return $fraction === '' ? $integer.'.00' : $integer.'.'.str_pad($fraction, 2, '0');
    }
}
