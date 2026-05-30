<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * 后台基础管理权限点种子
 */
class AdminFoundationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'admin',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * 获取基础管理权限点
     *
     * @return list<string>
     */
    private function permissions(): array
    {
        return [
            'view_any_admin_user',
            'view_admin_user',
            'create_admin_user',
            'update_admin_user',
            'delete_admin_user',
            'restore_admin_user',
            'force_delete_admin_user',
            'reset_password_admin_user',
            'assign_role_admin_user',
            'view_any_login_log',
            'view_login_log',
            'view_any_menu',
            'view_menu',
            'create_menu',
            'update_menu',
            'delete_menu',
            'restore_menu',
            'reorder_menu',
            'view_any_department',
            'view_department',
            'create_department',
            'update_department',
            'delete_department',
            'restore_department',
            'reorder_department',
            'view_any_role_data_scope',
            'view_role_data_scope',
            'update_role_data_scope',
            'view_any_activity_log',
            'view_activity_log',
        ];
    }
}
