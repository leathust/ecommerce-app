<?php

namespace App\Http\Controllers;

use App\Exceptions\IamOperationException;
use App\Http\Requests\AdminUserIndexRequest;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\IamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(private readonly IamService $iam) {}

    public function index(AdminUserIndexRequest $request): View
    {
        $filters = $request->validated();
        $users = User::query()
            ->administrators()
            ->searchDirectory($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(
                $filters['role_id'] ?? null,
                fn ($query, $roleId) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($roleId)),
            )
            ->with(['roles' => fn ($query) => $query->orderBy('name')])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::query()->where('is_admin', true)->orderBy('name')->get(),
            'statuses' => User::STATUSES,
        ]);
    }

    public function show(User $user): View
    {
        abort_unless($user->isAdmin(), 404);

        $user->load(['roles' => fn ($query) => $query->with('permissions')->orderBy('name')]);
        $effectivePermissions = $user->effectivePermissionNames();
        $permissionGroups = $effectivePermissions
            ->groupBy(fn (string $permission): string => Str::before($permission, '.'))
            ->sortKeys();
        $recentAudits = AdminAuditLog::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->getKey())
            ->with('actor:id,name,email')
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.users.show', [
            'managedUser' => $user,
            'effectivePermissions' => $effectivePermissions,
            'permissionGroups' => $permissionGroups,
            'recentAudits' => $recentAudits,
            'statuses' => User::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', [
            'managedUser' => new User(['status' => 'active', 'must_change_password' => true]),
            'roles' => $this->assignableRoles($request->user()),
            'statuses' => User::STATUSES,
        ]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roleIds = $validated['role_ids'];
        unset($validated['role_ids'], $validated['password_confirmation']);
        $validated['status'] = 'active';

        try {
            $user = $this->iam->createAdminUser($request->user(), $validated, $roleIds, $request);
        } catch (IamOperationException $exception) {
            throw ValidationException::withMessages(['role_ids' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Đã tạo tài khoản quản trị và gán vai trò.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($user->isAdmin(), 404);
        $this->authorize('update', $user);
        $user->load('roles');

        return view('admin.users.form', [
            'managedUser' => $user,
            'roles' => $this->assignableRoles($request->user(), $user),
            'statuses' => User::STATUSES,
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);
        $validated = $request->validated();
        $roleIds = $validated['role_ids'];
        unset($validated['role_ids'], $validated['password_confirmation']);

        try {
            $user = $this->iam->updateAdminUser($request->user(), $user, $validated, $roleIds, $request);
        } catch (IamOperationException $exception) {
            throw ValidationException::withMessages(['role_ids' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Đã cập nhật tài khoản quản trị.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);
        $this->authorize('delete', $user);

        try {
            $result = $this->iam->deleteAdminUser($request->user(), $user, $request);
        } catch (IamOperationException $exception) {
            return back()->withErrors(['delete' => $exception->getMessage()]);
        }

        if ($result['outcome'] === 'blocked') {
            return redirect()
                ->route('admin.users.show', $result['user_id'])
                ->with('warning', 'Tài khoản có dữ liệu nghiệp vụ liên quan nên không bị xóa. Hệ thống đã khóa tài khoản để giữ nguyên lịch sử.');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Đã xóa tài khoản quản trị không có dữ liệu nghiệp vụ liên quan.');
    }

    private function assignableRoles(User $actor, ?User $target = null): Collection
    {
        $roles = Role::query()
            ->where('is_admin', true)
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get();

        if ($actor->isSuperAdmin()) {
            return $roles;
        }

        $actorPermissions = $actor->effectivePermissionNames();

        return $roles
            ->reject(fn (Role $role): bool => $role->name === Role::SUPER_ADMIN
                || $role->permissions->pluck('name')->contains(fn (string $permission): bool => in_array($permission, ['roles.manage', 'permissions.manage'], true))
                || $role->permissions->pluck('name')->diff($actorPermissions)->isNotEmpty())
            ->when(
                $target,
                fn ($collection) => $collection->merge($target->roles)->unique('id')->sortBy('name')->values(),
            )
            ->values();
    }
}
