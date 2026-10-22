<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    public function scopeAssignedToRole(Builder $query, string $role): Builder
    {
        return $query->whereHas(
            'roles',
            fn (Builder $roleQuery) => $roleQuery->where('roles.name', $role),
        );
    }
}
