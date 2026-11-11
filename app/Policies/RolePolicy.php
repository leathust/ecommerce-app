<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->isSuperAdmin() && ! $role->isProtectedSuperAdminRole();
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->isSuperAdmin() && ! $role->isProtectedSuperAdminRole();
    }
}
