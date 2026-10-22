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

    public $timestamps = false;

    protected $fillable = ['name', 'description'];

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

    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query->whereHas(
            'permissions',
            fn (Builder $permissionQuery) => $permissionQuery->where('permissions.name', $permission),
        );
    }
}
