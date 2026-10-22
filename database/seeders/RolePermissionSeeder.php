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
            'settings.view' => 'Xem cấu hình',
            'settings.update' => 'Cập nhật cấu hình',
        ];

        $roles = [
            'super_admin' => [
                'description' => 'Quản trị viên cấp cao, có toàn bộ quyền',
                'permissions' => array_keys($permissions),
            ],
            'admin' => [
                'description' => 'Quản trị viên hệ thống',
                'permissions' => array_keys($permissions),
            ],
            'catalog_manager' => [
                'description' => 'Quản lý sản phẩm, danh mục và thương hiệu',
                'permissions' => [
                    'dashboard.view',
                    'products.view', 'products.create', 'products.update', 'products.delete',
                    'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                    'brands.view', 'brands.create', 'brands.update', 'brands.delete',
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

        DB::transaction(function () use ($permissions, $roles): void {
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
                    ['description' => $definition['description']],
                );

                $role->permissions()->sync(
                    $permissionModels->only($definition['permissions'])->pluck('id')->all(),
                );
            }
        });
    }
}
