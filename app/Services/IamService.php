<?php

namespace App\Services;

use App\Exceptions\IamOperationException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IamService
{
    private const GOVERNANCE_PERMISSIONS = ['roles.manage', 'permissions.manage'];

    public function __construct(private readonly AdminAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $roleIds
     */
    public function createAdminUser(
        User $actor,
        array $attributes,
        array $roleIds,
        ?Request $request = null,
    ): User {
        if (! $actor->hasPermission('users.create')) {
            throw IamOperationException::forbidden();
        }

        $ids = $this->normalizeIds($roleIds);

        return DB::transaction(function () use ($actor, $attributes, $ids, $request): User {
            $roles = $this->resolveAdminRoles($ids);
            $this->assertRolesAssignableBy($actor, $roles, false);

            $user = User::create($attributes);
            $user->roles()->sync($ids->all());

            $this->audit->record(
                $actor,
                'user.created',
                $user,
                [],
                [
                    'profile' => $this->profileSnapshot($user),
                    'roles' => $roles->sortBy('name')->pluck('name')->values()->all(),
                    'password_set' => true,
                ],
                $request,
            );

            return $user->load('roles.permissions');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $roleIds
     */
    public function updateAdminUser(
        User $actor,
        User $target,
        array $attributes,
        array $roleIds,
        ?Request $request = null,
    ): User {
        $ids = $this->normalizeIds($roleIds);

        return DB::transaction(function () use ($actor, $target, $attributes, $ids, $request): User {
            $lockedTarget = User::query()->whereKey($target)->lockForUpdate()->firstOrFail();
            $lockedTarget->load('roles');
            $this->assertCanManageUser($actor, $lockedTarget, 'users.update');

            $roles = $this->resolveAdminRoles($ids);
            $currentRoleIds = $lockedTarget->roles->pluck('id')->sort()->values();
            $rolesChanged = $currentRoleIds->all() !== $ids->sort()->values()->all();
            $nextStatus = (string) ($attributes['status'] ?? $lockedTarget->status);

            if ($actor->is($lockedTarget)) {
                if ($rolesChanged) {
                    throw IamOperationException::selfRoleChange();
                }

                if ($nextStatus !== 'active') {
                    throw IamOperationException::selfDeactivation();
                }
            }

            $this->assertRolesAssignableBy($actor, $roles, $lockedTarget->isSuperAdmin());

            if ($lockedTarget->isSuperAdmin()
                && $lockedTarget->isActive()
                && ($nextStatus !== 'active' || ! $roles->contains('name', Role::SUPER_ADMIN))) {
                $this->assertAnotherActiveSuperAdminExists($lockedTarget);
            }

            $before = [
                'profile' => $this->profileSnapshot($lockedTarget),
                'roles' => $lockedTarget->roles->sortBy('name')->pluck('name')->values()->all(),
            ];
            $passwordChanged = filled($attributes['password'] ?? null);

            if (! $passwordChanged) {
                unset($attributes['password']);
            }

            $lockedTarget->update($attributes);
            $lockedTarget->roles()->sync($ids->all());
            $lockedTarget->load('roles.permissions');

            $this->audit->record(
                $actor,
                'user.updated',
                $lockedTarget,
                $before,
                [
                    'profile' => $this->profileSnapshot($lockedTarget),
                    'roles' => $roles->sortBy('name')->pluck('name')->values()->all(),
                    'password_changed' => $passwordChanged,
                ],
                $request,
            );

            return $lockedTarget;
        });
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    public function syncUserRoles(
        User $actor,
        User $target,
        array $roleIds,
        ?Request $request = null,
    ): User {
        $ids = $this->normalizeIds($roleIds);

        return DB::transaction(function () use ($actor, $target, $ids, $request): User {
            $lockedTarget = User::query()->whereKey($target)->lockForUpdate()->firstOrFail();
            $roles = Role::query()->whereKey($ids)->lockForUpdate()->get();

            if ($ids->isEmpty() || $roles->count() !== $ids->count() || $roles->contains('is_admin', false)) {
                throw IamOperationException::invalidRoles();
            }

            $roles->load('permissions');

            $this->assertCanManageUser($actor, $lockedTarget, 'users.update');

            if ($actor->is($lockedTarget)) {
                throw IamOperationException::selfRoleChange();
            }

            $before = $lockedTarget->roles()->orderBy('name')->pluck('name')->all();
            $wasSuperAdmin = in_array(Role::SUPER_ADMIN, $before, true);
            $willBeSuperAdmin = $roles->contains('name', Role::SUPER_ADMIN);

            if (! $actor->isSuperAdmin()) {
                if ($wasSuperAdmin || $willBeSuperAdmin || $this->containsGovernanceRole($roles)) {
                    throw IamOperationException::forbidden('Chỉ super admin được quản lý role đặc quyền.');
                }

                $actorPermissions = $actor->effectivePermissionNames();
                $assignedPermissions = $roles
                    ->flatMap->permissions
                    ->pluck('name')
                    ->unique();

                if ($assignedPermissions->diff($actorPermissions)->isNotEmpty()) {
                    throw IamOperationException::forbidden('Bạn không thể gán quyền cao hơn quyền hiệu lực của mình.');
                }
            }

            if ($wasSuperAdmin && ! $willBeSuperAdmin && $lockedTarget->isActive()) {
                $this->assertAnotherActiveSuperAdminExists($lockedTarget);
            }

            $lockedTarget->roles()->sync($ids->all());
            $after = $roles->sortBy('name')->pluck('name')->values()->all();

            $this->audit->record(
                $actor,
                'user.roles_synced',
                $lockedTarget,
                ['roles' => $before],
                ['roles' => $after],
                $request,
            );

            return $lockedTarget->load('roles.permissions');
        });
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    public function syncRolePermissions(
        User $actor,
        Role $role,
        array $permissionIds,
        ?Request $request = null,
    ): Role {
        if (! $actor->isSuperAdmin()) {
            throw IamOperationException::forbidden('Chỉ super admin được thay đổi ma trận quyền.');
        }

        if ($role->isProtectedSuperAdminRole()) {
            throw IamOperationException::systemRoleReadOnly();
        }

        $ids = $this->normalizeIds($permissionIds);

        return DB::transaction(function () use ($actor, $role, $ids, $request): Role {
            $lockedRole = Role::query()->whereKey($role)->lockForUpdate()->firstOrFail();

            if ($lockedRole->isProtectedSuperAdminRole()) {
                throw IamOperationException::systemRoleReadOnly();
            }

            $this->assertPermissionSyncWillNotLockActor($actor, $lockedRole);

            $permissions = Permission::query()->whereKey($ids)->lockForUpdate()->get();

            if ($permissions->count() !== $ids->count()) {
                throw IamOperationException::forbidden('Danh sách permission không hợp lệ.');
            }

            $before = $lockedRole->permissions()->orderBy('name')->pluck('name')->all();
            $lockedRole->permissions()->sync($ids->all());
            $after = $permissions->sortBy('name')->pluck('name')->values()->all();

            $this->audit->record(
                $actor,
                'role.permissions_synced',
                $lockedRole,
                ['permissions' => $before],
                ['permissions' => $after],
                $request,
            );

            return $lockedRole->load('permissions');
        });
    }

    /**
     * @param  array{name: string, description?: string|null}  $attributes
     * @param  array<int, int|string>  $permissionIds
     */
    public function createAdminRole(
        User $actor,
        array $attributes,
        array $permissionIds,
        ?Request $request = null,
    ): Role {
        $this->assertCanManageRoles($actor);
        $ids = $this->normalizeIds($permissionIds);

        return DB::transaction(function () use ($actor, $attributes, $ids, $request): Role {
            $this->assertRoleNameIsAvailable($attributes['name']);
            $permissions = $this->resolvePermissions($ids);
            $role = Role::create([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'is_admin' => true,
                'is_system' => false,
            ]);
            $role->permissions()->sync($ids->all());

            $this->audit->record(
                $actor,
                'role.created',
                $role,
                [],
                [
                    'role' => $this->roleSnapshot($role),
                    'permissions' => $permissions->pluck('name')->sort()->values()->all(),
                ],
                $request,
            );

            return $role->load('permissions');
        });
    }

    /**
     * @param  array{name: string, description?: string|null}  $attributes
     * @param  array<int, int|string>  $permissionIds
     */
    public function updateAdminRole(
        User $actor,
        Role $role,
        array $attributes,
        array $permissionIds,
        ?Request $request = null,
    ): Role {
        $this->assertCanManageRoles($actor);
        $ids = $this->normalizeIds($permissionIds);

        return DB::transaction(function () use ($actor, $role, $attributes, $ids, $request): Role {
            $lockedRole = Role::query()->whereKey($role)->lockForUpdate()->firstOrFail();

            if ($lockedRole->isProtectedSuperAdminRole()) {
                throw IamOperationException::systemRoleReadOnly();
            }

            if ($lockedRole->isReservedSystemRole() && $attributes['name'] !== $lockedRole->name) {
                throw IamOperationException::systemRoleNameReadOnly();
            }

            if (! $lockedRole->isReservedSystemRole()) {
                $this->assertRoleNameIsAvailable($attributes['name'], $lockedRole);
            }
            $this->assertPermissionSyncWillNotLockActor($actor, $lockedRole);
            $permissions = $this->resolvePermissions($ids);
            $before = [
                'role' => $this->roleSnapshot($lockedRole),
                'permissions' => $lockedRole->permissions()->orderBy('name')->pluck('name')->all(),
            ];
            $lockedRole->update([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
            ]);
            $lockedRole->permissions()->sync($ids->all());

            $this->audit->record(
                $actor,
                'role.updated',
                $lockedRole,
                $before,
                [
                    'role' => $this->roleSnapshot($lockedRole),
                    'permissions' => $permissions->pluck('name')->sort()->values()->all(),
                ],
                $request,
            );

            return $lockedRole->load('permissions');
        });
    }

    public function deleteAdminRole(User $actor, Role $role, ?Request $request = null): void
    {
        $this->assertCanManageRoles($actor);

        DB::transaction(function () use ($actor, $role, $request): void {
            $lockedRole = Role::query()
                ->whereKey($role)
                ->lockForUpdate()
                ->with('permissions:id,name')
                ->firstOrFail();

            if ($lockedRole->isProtectedSuperAdminRole()) {
                throw IamOperationException::systemRoleReadOnly();
            }

            if (DB::table('user_roles')->where('role_id', $lockedRole->id)->lockForUpdate()->exists()) {
                throw IamOperationException::roleInUse();
            }

            $before = [
                'role' => $this->roleSnapshot($lockedRole),
                'permissions' => $lockedRole->permissions->pluck('name')->sort()->values()->all(),
            ];

            $this->audit->record(
                $actor,
                'role.deleted',
                $lockedRole,
                $before,
                ['deleted' => true],
                $request,
            );
            $lockedRole->delete();
        });
    }

    public function updateUserStatus(
        User $actor,
        User $target,
        string $status,
        ?Request $request = null,
    ): User {
        if (! array_key_exists($status, User::STATUSES)) {
            throw IamOperationException::forbidden('Trạng thái tài khoản không hợp lệ.');
        }

        return DB::transaction(function () use ($actor, $target, $status, $request): User {
            $lockedTarget = User::query()->whereKey($target)->lockForUpdate()->firstOrFail();
            $this->assertCanManageUser($actor, $lockedTarget, 'users.update');

            if ($actor->is($lockedTarget) && $status !== 'active') {
                throw IamOperationException::selfDeactivation();
            }

            if ($lockedTarget->isSuperAdmin() && $lockedTarget->isActive() && $status !== 'active') {
                $this->assertAnotherActiveSuperAdminExists($lockedTarget);
            }

            $before = $lockedTarget->status;
            $lockedTarget->update(['status' => $status]);

            $this->audit->record(
                $actor,
                'user.status_updated',
                $lockedTarget,
                ['status' => $before],
                ['status' => $status],
                $request,
            );

            return $lockedTarget;
        });
    }

    /**
     * Delete an unused administrator or block it when business traces must be preserved.
     *
     * @return array{outcome: 'deleted'|'blocked', user_id: int}
     */
    public function deleteAdminUser(
        User $actor,
        User $target,
        ?Request $request = null,
    ): array {
        return DB::transaction(function () use ($actor, $target, $request): array {
            $lockedTarget = User::query()->whereKey($target)->lockForUpdate()->firstOrFail();
            $lockedTarget->load('roles');

            if (! $lockedTarget->isAdmin()) {
                throw IamOperationException::forbidden('Tài khoản được chọn không thuộc phạm vi quản trị IAM.');
            }

            $this->assertCanManageUser($actor, $lockedTarget, 'users.delete');

            if ($actor->is($lockedTarget)) {
                throw IamOperationException::selfDeletion();
            }

            if ($lockedTarget->isSuperAdmin()) {
                $this->assertAnotherActiveSuperAdminExists($lockedTarget);
            }

            $userId = (int) $lockedTarget->getKey();
            $before = [
                'profile' => $this->profileSnapshot($lockedTarget),
                'roles' => $lockedTarget->roles->sortBy('name')->pluck('name')->values()->all(),
            ];
            $traces = $this->businessTraceNames($lockedTarget);

            if ($traces->isNotEmpty()) {
                $lockedTarget->update(['status' => 'blocked']);

                $this->audit->record(
                    $actor,
                    'user.blocked_instead_of_deleted',
                    $lockedTarget,
                    $before,
                    [
                        'profile' => $this->profileSnapshot($lockedTarget),
                        'roles' => $before['roles'],
                        'preserved_relations' => $traces->all(),
                    ],
                    $request,
                );

                return ['outcome' => 'blocked', 'user_id' => $userId];
            }

            $this->audit->record(
                $actor,
                'user.deleted',
                $lockedTarget,
                $before,
                ['deleted' => true],
                $request,
            );
            $lockedTarget->delete();

            return ['outcome' => 'deleted', 'user_id' => $userId];
        });
    }

    private function assertCanManageUser(User $actor, User $target, string $permission): void
    {
        if (! $actor->isSuperAdmin() && ! $actor->hasPermission($permission)) {
            throw IamOperationException::forbidden();
        }

        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw IamOperationException::forbidden('Bạn không thể thay đổi tài khoản super admin.');
        }
    }

    private function assertCanManageRoles(User $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw IamOperationException::forbidden('Chỉ super admin được quản lý cấu trúc vai trò.');
        }
    }

    private function assertPermissionSyncWillNotLockActor(User $actor, Role $role): void
    {
        if (! $role->users()->whereKey($actor)->exists()) {
            return;
        }

        $keepsSuperAdminRole = $actor->roles()
            ->whereKeyNot($role->getKey())
            ->where('roles.name', Role::SUPER_ADMIN)
            ->exists();

        if (! $keepsSuperAdminRole) {
            throw IamOperationException::selfPermissionLockout();
        }
    }

    private function assertRoleNameIsAvailable(string $name, ?Role $currentRole = null): void
    {
        if (in_array($name, Role::RESERVED_NAMES, true)) {
            throw IamOperationException::reservedRoleName();
        }

        $exists = Role::query()
            ->where('name', $name)
            ->when($currentRole, fn ($query) => $query->whereKeyNot($currentRole->getKey()))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw IamOperationException::forbidden('Tên vai trò đã tồn tại.');
        }
    }

    /** @param Collection<int, Role> $roles */
    private function assertRolesAssignableBy(User $actor, Collection $roles, bool $targetWasSuperAdmin): void
    {
        $willBeSuperAdmin = $roles->contains('name', Role::SUPER_ADMIN);

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($targetWasSuperAdmin || $willBeSuperAdmin || $this->containsGovernanceRole($roles)) {
            throw IamOperationException::forbidden('Chỉ super admin được quản lý role đặc quyền.');
        }

        $assignedPermissions = $roles->flatMap->permissions->pluck('name')->unique();

        if ($assignedPermissions->diff($actor->effectivePermissionNames())->isNotEmpty()) {
            throw IamOperationException::forbidden('Bạn không thể gán quyền cao hơn quyền hiệu lực của mình.');
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Role>
     */
    private function resolveAdminRoles(Collection $ids): Collection
    {
        $roles = Role::query()->whereKey($ids)->lockForUpdate()->with('permissions')->get();

        if ($ids->isEmpty() || $roles->count() !== $ids->count() || $roles->contains('is_admin', false)) {
            throw IamOperationException::invalidRoles();
        }

        return $roles;
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(Collection $ids): Collection
    {
        $permissions = Permission::query()
            ->whereKey($ids)
            ->lockForUpdate()
            ->get();

        if ($permissions->count() !== $ids->count()) {
            throw IamOperationException::forbidden('Danh sách permission không hợp lệ.');
        }

        return $permissions;
    }

    /** @return array<string, mixed> */
    private function profileSnapshot(User $user): array
    {
        return $user->only([
            'name',
            'username',
            'email',
            'first_name',
            'last_name',
            'phone',
            'status',
            'must_change_password',
        ]);
    }

    /** @return array<string, mixed> */
    private function roleSnapshot(Role $role): array
    {
        return $role->only(['name', 'description', 'is_admin', 'is_system']);
    }

    private function assertAnotherActiveSuperAdminExists(User $target): void
    {
        $hasAnother = User::query()
            ->whereKeyNot($target->getKey())
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('roles.name', Role::SUPER_ADMIN))
            ->lockForUpdate()
            ->get(['users.id'])
            ->isNotEmpty();

        if (! $hasAnother) {
            throw IamOperationException::lastSuperAdmin();
        }
    }

    /** @return Collection<int, string> */
    private function businessTraceNames(User $target): Collection
    {
        return collect([
            'orders' => $target->orders()->exists(),
            'order_status_histories' => $target->orderStatusChanges()->exists(),
            'settings' => $target->updatedSettings()->exists(),
            'admin_audit_logs' => $target->adminAuditLogs()->exists(),
            'carts' => $target->carts()->exists(),
            'wishlists' => $target->wishlistItems()->exists(),
        ])->filter()->keys()->values();
    }

    /** @param Collection<int, Role> $roles */
    private function containsGovernanceRole(Collection $roles): bool
    {
        return $roles->contains(fn (Role $role): bool => $role->permissions
            ->pluck('name')
            ->intersect(self::GOVERNANCE_PERMISSIONS)
            ->isNotEmpty());
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, int>
     */
    private function normalizeIds(array $ids): Collection
    {
        return collect($ids)
            ->filter(fn ($id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }
}
