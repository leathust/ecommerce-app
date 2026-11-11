<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN_ROLES = [
        self::SUPER_ADMIN,
        'admin',
        'catalog_manager',
        'order_manager',
        'customer_support',
    ];

    public const RESERVED_NAMES = self::ADMIN_ROLES;

    public $timestamps = false;

    protected $fillable = ['name', 'description', 'is_admin', 'is_system'];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->name === self::SUPER_ADMIN
            || $this->permissions()->where('permissions.name', $permission)->exists();
    }

    public function isProtectedSuperAdminRole(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }

    public function isReservedSystemRole(): bool
    {
        return in_array($this->name, self::RESERVED_NAMES, true);
    }

    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query->whereHas(
            'permissions',
            fn (Builder $permissionQuery) => $permissionQuery->where('permissions.name', $permission),
        );
    }
}
