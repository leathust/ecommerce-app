<?php

namespace App\Policies;

use App\Models\User;

class AdminUserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.view') && $target->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.update')
            && ($actor->isSuperAdmin() || ! $target->isSuperAdmin());
    }

    public function delete(User $actor, User $target): bool
    {
        return ! $actor->is($target)
            && $actor->hasPermission('users.delete')
            && ($actor->isSuperAdmin() || ! $target->isSuperAdmin());
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return ! $actor->is($target) && $this->update($actor, $target);
    }
}
