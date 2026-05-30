<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * 后台基础管理菜单种子
 */
class AdminFoundationMenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->menus() as $index => $menu) {
            Menu::query()->updateOrCreate(
                [
                    'title'  => $menu['title'],
                    'source' => 'core',
                ],
                [
                    ...$menu,
                    'sort'      => ($index + 1) * 10,
                    'is_active' => true,
                    'source'    => 'core',
                ],
            );
        }
    }

    /**
     * 获取基础管理菜单定义
     *
     * @return list<array<string, mixed>>
     */
    private function menus(): array
    {
        return [
            [
                'title'           => '管理员管理',
                'icon'            => 'heroicon-o-users',
                'route_name'      => 'filament.admin.resources.admin-users.index',
                'url'             => null,
                'permission_name' => 'view_any_admin_user',
                'target'          => 'self',
            ],
            [
                'title'           => '管理员日志',
                'icon'            => 'heroicon-o-clipboard-document-list',
                'route_name'      => 'filament.admin.resources.login-logs.index',
                'url'             => null,
                'permission_name' => 'view_any_login_log',
                'target'          => 'self',
            ],
            [
                'title'           => '角色管理',
                'icon'            => 'heroicon-o-shield-check',
                'route_name'      => null,
                'url'             => '/admin/shield/roles',
                'permission_name' => 'view_any_role',
                'target'          => 'self',
            ],
            [
                'title'           => '菜单规则',
                'icon'            => 'heroicon-o-bars-3',
                'route_name'      => 'filament.admin.resources.menus.index',
                'url'             => null,
                'permission_name' => 'view_any_menu',
                'target'          => 'self',
            ],
            [
                'title'           => '部门管理',
                'icon'            => 'heroicon-o-building-office',
                'route_name'      => 'filament.admin.resources.departments.index',
                'url'             => null,
                'permission_name' => 'view_any_department',
                'target'          => 'self',
            ],
            [
                'title'           => '数据权限',
                'icon'            => 'heroicon-o-adjustments-horizontal',
                'route_name'      => 'filament.admin.resources.role-data-scopes.index',
                'url'             => null,
                'permission_name' => 'view_any_role_data_scope',
                'target'          => 'self',
            ],
            [
                'title'           => '操作日志',
                'icon'            => 'heroicon-o-clock',
                'route_name'      => null,
                'url'             => '/admin/activity-logs',
                'permission_name' => 'view_any_activity_log',
                'target'          => 'self',
            ],
        ];
    }
}
