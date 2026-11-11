<?php

namespace App\Http\Controllers;

use App\Exceptions\IamOperationException;
use App\Http\Requests\AdminRoleIndexRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\IamService;
use App\Services\PermissionMatrixService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminRoleController extends Controller
{
    public function __construct(
        private readonly IamService $iam,
        private readonly PermissionMatrixService $permissionMatrix,
    ) {}

    public function index(AdminRoleIndexRequest $request): View
    {
        $filters = $request->validated();
        $roles = Role::query()
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where('name', 'like', $like)->orWhere('description', 'like', $like);
                });
            })
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.roles.index', compact('roles', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.form', [
            'role' => new Role,
            'permissionGroups' => $this->permissionMatrix->grouped(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $permissionIds = $validated['permission_ids'];
            unset($validated['permission_ids']);
            $role = $this->iam->createAdminRole($request->user(), $validated, $permissionIds, $request);
        } catch (IamOperationException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Đã tạo vai trò {$role->name} và gán permission.");
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);
        $role->load('permissions');

        return view('admin.roles.form', [
            'role' => $role,
            'permissionGroups' => $this->permissionMatrix->grouped(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $permissionIds = $validated['permission_ids'];
            unset($validated['permission_ids']);
            $role = $this->iam->updateAdminRole($request->user(), $role, $validated, $permissionIds, $request);
        } catch (IamOperationException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Đã cập nhật vai trò {$role->name} và ma trận permission.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        try {
            $this->iam->deleteAdminRole($request->user(), $role, $request);
        } catch (IamOperationException $exception) {
            return back()->withErrors(['delete' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Đã xóa vai trò tùy chỉnh.');
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        try {
            $this->iam->syncRolePermissions(
                $request->user(),
                $role,
                $request->validated('permission_ids'),
                $request,
            );
        } catch (IamOperationException $exception) {
            throw ValidationException::withMessages(['permission_ids' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('success', "Đã cập nhật ma trận quyền cho vai trò {$role->name}.");
    }
}
