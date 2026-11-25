<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the initial role and permission matrix.
     */
    public function run(): void
    {
        $addonPermissionNames = [
            'menus.view', 'menus.create', 'menus.update', 'menus.delete',
            'banners.view', 'banners.create', 'banners.update', 'banners.delete',
        ];
        $permissions = [
            'dashboard.view' => 'Xem trang tổng quan quản trị',
            'products.view' => 'Xem sản phẩm',
            'products.create' => 'Tạo sản phẩm',
            'products.update' => 'Cập nhật sản phẩm',
            'products.delete' => 'Xóa sản phẩm',
            'categories.view' => 'Xem danh mục',
            'categories.create' => 'Tạo danh mục',
            'categories.update' => 'Cập nhật danh mục',
            'categories.delete' => 'Xóa danh mục',
            'brands.view' => 'Xem thương hiệu',
            'brands.create' => 'Tạo thương hiệu',
            'brands.update' => 'Cập nhật thương hiệu',
            'brands.delete' => 'Xóa thương hiệu',
            'orders.view' => 'Xem đơn hàng',
            'orders.update' => 'Cập nhật đơn hàng',
            'orders.cancel' => 'Hủy đơn hàng',
            'payments.view' => 'Xem đối soát thanh toán',
            'payments.manage' => 'Xử lý ngoại lệ thanh toán',
            'customers.view' => 'Xem khách hàng',
            'customers.update' => 'Cập nhật khách hàng',
            'contacts.view' => 'Xem liên hệ',
            'contacts.update' => 'Xử lý liên hệ',
            'users.view' => 'Xem người dùng',
            'users.create' => 'Tạo người dùng',
            'users.update' => 'Cập nhật người dùng',
            'users.delete' => 'Xóa người dùng',
            'roles.view' => 'Xem vai trò',
            'roles.manage' => 'Quản lý vai trò',
            'permissions.manage' => 'Quản lý quyền',
            'audit.view' => 'Xem lịch sử thay đổi quản trị',
            'settings.view' => 'Xem cấu hình',
            'settings.update' => 'Cập nhật cấu hình',
            'menus.view' => 'Xem menu website',
            'menus.create' => 'Tạo menu website',
            'menus.update' => 'Cập nhật và sắp xếp menu website',
            'menus.delete' => 'Xóa menu website',
            'banners.view' => 'Xem banner trang chủ',
            'banners.create' => 'Tạo banner trang chủ',
            'banners.update' => 'Cập nhật banner trang chủ',
            'banners.delete' => 'Xóa banner trang chủ',
        ];

        $roles = [
            'super_admin' => [
                'description' => 'Quản trị viên cấp cao, có toàn bộ quyền',
                'permissions' => array_keys($permissions),
            ],
            'admin' => [
                'description' => 'Quản trị viên hệ thống',
                'permissions' => [
                    'dashboard.view',
                    'products.view', 'products.create', 'products.update', 'products.delete',
                    'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                    'brands.view', 'brands.create', 'brands.update', 'brands.delete',
                    'orders.view', 'orders.update', 'orders.cancel',
                    'payments.view', 'payments.manage',
                    'customers.view', 'customers.update',
                    'contacts.view', 'contacts.update',
                    'users.view', 'users.create', 'users.update', 'users.delete',
                    'roles.view', 'audit.view',
                    'settings.view', 'settings.update',
                    'menus.view', 'menus.create', 'menus.update', 'menus.delete',
                    'banners.view', 'banners.create', 'banners.update', 'banners.delete',
                ],
            ],
            'catalog_manager' => [
                'description' => 'Quản lý sản phẩm, danh mục và thương hiệu',
                'permissions' => [
                    'dashboard.view',
                    'products.view', 'products.create', 'products.update', 'products.delete',
                    'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                    'brands.view', 'brands.create', 'brands.update', 'brands.delete',
                    'menus.view', 'menus.create', 'menus.update', 'menus.delete',
                    'banners.view', 'banners.create', 'banners.update', 'banners.delete',
                ],
            ],
            'order_manager' => [
                'description' => 'Quản lý và xử lý đơn hàng',
                'permissions' => [
                    'dashboard.view',
                    'orders.view', 'orders.update', 'orders.cancel',
                    'customers.view',
                ],
            ],
            'customer_support' => [
                'description' => 'Nhân viên hỗ trợ khách hàng',
                'permissions' => [
                    'dashboard.view',
                    'orders.view',
                    'customers.view',
                    'contacts.view', 'contacts.update',
                ],
            ],
        ];

        DB::transaction(function () use ($permissions, $roles, $addonPermissionNames): void {
            $permissionModels = collect($permissions)->mapWithKeys(
                fn (string $description, string $name) => [
                    $name => Permission::updateOrCreate(
                        ['name' => $name],
                        ['description' => $description],
                    ),
                ],
            );

            foreach ($roles as $name => $definition) {
                $role = Role::updateOrCreate(
                    ['name' => $name],
                    [
                        'description' => $definition['description'],
                        'is_admin' => true,
                        'is_system' => true,
                    ],
                );

                if ($role->wasRecentlyCreated) {
                    $role->permissions()->sync(
                        $permissionModels->only($definition['permissions'])->pluck('id')->all(),
                    );
                } else {
                    $currentPermissions = $role->permissions()->pluck('name')->sort()->values()->all();
                    $desiredPermissions = $definition['permissions'];
                    $missingAddons = array_values(array_diff(
                        array_intersect($desiredPermissions, $addonPermissionNames),
                        $currentPermissions,
                    ));
                    $expectedBefore = array_values(array_diff($desiredPermissions, $missingAddons));
                    sort($expectedBefore);

                    if ($missingAddons !== [] && $currentPermissions === $expectedBefore) {
                        $role->permissions()->syncWithoutDetaching(
                            $permissionModels->only($missingAddons)->pluck('id')->all(),
                        );
                    }
                }
            }
        });
    }
}
