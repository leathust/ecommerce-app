<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionMatrixService
{
    private const MODULES = [
        'dashboard' => ['label' => 'Tổng quan', 'description' => 'Truy cập dashboard quản trị.'],
        'catalog' => ['label' => 'Danh mục & sản phẩm', 'description' => 'Sản phẩm, danh mục và thương hiệu.'],
        'orders' => ['label' => 'Đơn hàng', 'description' => 'Xem và xử lý quy trình đơn hàng.'],
        'customers' => ['label' => 'Khách hàng', 'description' => 'Xem và cập nhật hồ sơ khách hàng.'],
        'contacts' => ['label' => 'Liên hệ', 'description' => 'Tiếp nhận và phản hồi liên hệ.'],
        'users' => ['label' => 'Tài khoản', 'description' => 'Quản lý tài khoản quản trị.'],
        'roles' => ['label' => 'Vai trò & phân quyền', 'description' => 'Role, permission và audit IAM.'],
        'settings' => ['label' => 'Cấu hình', 'description' => 'Xem và cập nhật cấu hình hệ thống.'],
        'menus' => ['label' => 'Menu website', 'description' => 'Quản lý điều hướng động ngoài storefront.'],
    ];

    /** @return Collection<string, array{label: string, description: string, permissions: Collection<int, Permission>}> */
    public function grouped(): Collection
    {
        $groups = collect(self::MODULES)->map(
            fn (array $module): array => [...$module, 'permissions' => collect()],
        );

        Permission::query()->orderBy('name')->get()->each(function (Permission $permission) use ($groups): void {
            $module = $this->moduleFor($permission->name);
            $groups[$module]['permissions']->push($permission);
        });

        return $groups->filter(fn (array $group): bool => $group['permissions']->isNotEmpty());
    }

    private function moduleFor(string $permission): string
    {
        $prefix = str($permission)->before('.')->toString();

        return match ($prefix) {
            'products', 'categories', 'brands' => 'catalog',
            'permissions', 'audit' => 'roles',
            default => array_key_exists($prefix, self::MODULES) ? $prefix : 'settings',
        };
    }
}
