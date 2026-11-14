<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAuditService
{
    private const DISPLAY_ALLOWED_ROOT_KEYS = [
        'profile',
        'role',
        'roles',
        'permissions',
        'status',
        'dependencies',
        'outcome',
        'user_id',
        'password_set',
        'password_changed',
        'group',
        'values',
        'menu',
        'items',
        'location',
        'parent_id',
        'banner',
    ];

    private const SENSITIVE_KEY_PARTS = [
        'password',
        'token',
        'remember_token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
    ];

    public function record(
        User $actor,
        string $action,
        Model $subject,
        array $before = [],
        array $after = [],
        ?Request $request = null,
    ): AdminAuditLog {
        return $this->recordEvent(
            $actor,
            $action,
            $subject->getMorphClass(),
            $subject->getKey(),
            $before,
            $after,
            $request,
        );
    }

    public function recordSystemEvent(
        User $actor,
        string $action,
        string $subjectType,
        array $before = [],
        array $after = [],
        ?Request $request = null,
    ): AdminAuditLog {
        return $this->recordEvent(
            $actor,
            $action,
            $subjectType,
            null,
            $before,
            $after,
            $request,
        );
    }

    private function recordEvent(
        User $actor,
        string $action,
        string $subjectType,
        int|string|null $subjectId,
        array $before,
        array $after,
        ?Request $request,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before_values' => $this->sanitize($before),
            'after_values' => $this->sanitize($after),
            'ip_address' => $request?->ip(),
            'user_agent' => filled($request?->userAgent())
                ? Str::limit((string) $request->userAgent(), 1024, '')
                : null,
        ]);
    }

    public function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            $normalizedKey = Str::lower((string) $key);
            $isSensitive = collect(self::SENSITIVE_KEY_PARTS)
                ->contains(fn (string $part): bool => Str::contains($normalizedKey, $part));

            $sanitized[$key] = $isSensitive
                ? '[REDACTED]'
                : (is_array($value) ? $this->sanitize($value) : $value);
        }

        return $sanitized;
    }

    public function safeForDisplay(?array $values): array
    {
        if (empty($values)) {
            return [];
        }

        return $this->sanitize(
            collect($values)->only(self::DISPLAY_ALLOWED_ROOT_KEYS)->all(),
        );
    }
}
