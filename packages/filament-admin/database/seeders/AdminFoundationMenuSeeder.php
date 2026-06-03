<?php

namespace FilamentAdmin\Database\Seeders;

use FilamentAdmin\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * 后台基础管理菜单种子
 */
class AdminFoundationMenuSeeder extends Seeder
{
    public function run(): void
    {
        // 第一步：确保顶层分组"系统管理"存在（parent_id=0，type=menu，无路由）
        $systemGroup = Menu::query()->updateOrCreate(
            ['title' => '系统管理', 'source' => 'core', 'type' => 'menu', 'parent_id' => 0],
            [
                'icon'            => 'heroicon-o-cog-6-tooth',
                'route_name'      => null,
                'url'             => null,
                'permission_name' => null,
                'sort'            => 10,
                'is_active'       => true,
                'target'          => 'self',
                'source'          => 'core',
                'type'            => 'menu',
                'parent_id'       => 0,
            ],
        );

        // 第二步：在"系统管理"下插入或更新导航菜单项，并记录 id 供 action 节点使用
        $menuIds = [];

        foreach ($this->menus() as $index => $menu) {
            $record = Menu::query()->updateOrCreate(
                [
                    'title'     => $menu['title'],
                    'source'    => 'core',
                    'type'      => 'menu',
                    'parent_id' => $systemGroup->id,
                ],
                [
                    ...$menu,
                    'parent_id' => $systemGroup->id,
                    'sort'      => ($index + 1) * 10,
                    'is_active' => true,
                    'source'    => 'core',
                    'type'      => 'menu',
                ],
            );

            $menuIds[$menu['title']] = $record->id;
        }

        // 第三步：再插入或更新操作权限节点（type=action）
        foreach ($this->actions() as $action) {
            $parentId = $menuIds[$action['_parent_title']] ?? null;

            if (! $parentId) {
                continue;
            }

            Menu::query()->updateOrCreate(
                [
                    'title'     => $action['title'],
                    'source'    => 'core',
                    'type'      => 'action',
                    'parent_id' => $parentId,
                ],
                [
                    'parent_id'       => $parentId,
                    'title'           => $action['title'],
                    'icon'            => null,
                    'route_name'      => null,
                    'url'             => null,
                    'permission_name' => $action['permission_name'],
                    'sort'            => $action['sort'],
                    'is_active'       => true,
                    'target'          => 'self',
                    'source'          => 'core',
                    'type'            => 'action',
                ],
            );
        }

        // 第四步：确保顶层分组"系统配置"存在
        $configGroup = Menu::query()->updateOrCreate(
            ['title' => '系统配置', 'source' => 'core', 'type' => 'menu', 'parent_id' => 0],
            [
                'icon'            => 'heroicon-o-adjustments-horizontal',
                'route_name'      => null,
                'url'             => null,
                'permission_name' => null,
                'sort'            => 20,
                'is_active'       => true,
                'target'          => 'self',
                'source'          => 'core',
                'type'            => 'menu',
                'parent_id'       => 0,
            ],
        );

        // 第五步：在"系统配置"下插入四个配置子菜单
        foreach ($this->configMenus() as $index => $menu) {
            Menu::query()->updateOrCreate(
                [
                    'title'     => $menu['title'],
                    'source'    => 'core',
                    'type'      => 'menu',
                    'parent_id' => $configGroup->id,
                ],
                [
                    ...$menu,
                    'parent_id' => $configGroup->id,
                    'sort'      => ($index + 1) * 10,
                    'is_active' => true,
                    'source'    => 'core',
                    'type'      => 'menu',
                ],
            );
        }

        // 第六步：在"系统管理"组下添加媒体库菜单
        $this->seedMediaMenu($systemGroup->id);
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
                'title'           => '操作日志',
                'icon'            => 'heroicon-o-clock',
                'route_name'      => null,
                'url'             => '/admin/activity-logs',
                'permission_name' => 'view_any_activity_log',
                'target'          => 'self',
            ],
        ];
    }

    /**
     * 获取操作权限节点定义
     * _parent_title 用于在运行时查找父菜单 id，不写入数据库
     *
     * @return list<array<string, mixed>>
     */
    private function actions(): array
    {
        return [
            // 管理员管理
            ['_parent_title' => '管理员管理', 'title' => '列表', 'permission_name' => 'view_any_admin_user', 'sort' => 10],
            ['_parent_title' => '管理员管理', 'title' => '查看', 'permission_name' => 'view_admin_user', 'sort' => 20],
            ['_parent_title' => '管理员管理', 'title' => '新增', 'permission_name' => 'create_admin_user', 'sort' => 30],
            ['_parent_title' => '管理员管理', 'title' => '编辑', 'permission_name' => 'update_admin_user', 'sort' => 40],
            ['_parent_title' => '管理员管理', 'title' => '删除', 'permission_name' => 'delete_admin_user', 'sort' => 50],
            ['_parent_title' => '管理员管理', 'title' => '恢复', 'permission_name' => 'restore_admin_user', 'sort' => 60],
            ['_parent_title' => '管理员管理', 'title' => '强制删除', 'permission_name' => 'force_delete_admin_user', 'sort' => 70],
            ['_parent_title' => '管理员管理', 'title' => '重置密码', 'permission_name' => 'reset_password_admin_user', 'sort' => 80],
            ['_parent_title' => '管理员管理', 'title' => '分配角色', 'permission_name' => 'assign_role_admin_user', 'sort' => 90],
            // 管理员日志
            ['_parent_title' => '管理员日志', 'title' => '列表', 'permission_name' => 'view_any_login_log', 'sort' => 10],
            ['_parent_title' => '管理员日志', 'title' => '查看', 'permission_name' => 'view_login_log', 'sort' => 20],
            // 角色管理
            ['_parent_title' => '角色管理', 'title' => '列表', 'permission_name' => 'view_any_role', 'sort' => 10],
            // 菜单规则
            ['_parent_title' => '菜单规则', 'title' => '列表', 'permission_name' => 'view_any_menu', 'sort' => 10],
            ['_parent_title' => '菜单规则', 'title' => '查看', 'permission_name' => 'view_menu', 'sort' => 20],
            ['_parent_title' => '菜单规则', 'title' => '新增', 'permission_name' => 'create_menu', 'sort' => 30],
            ['_parent_title' => '菜单规则', 'title' => '编辑', 'permission_name' => 'update_menu', 'sort' => 40],
            ['_parent_title' => '菜单规则', 'title' => '删除', 'permission_name' => 'delete_menu', 'sort' => 50],
            ['_parent_title' => '菜单规则', 'title' => '恢复', 'permission_name' => 'restore_menu', 'sort' => 60],
            ['_parent_title' => '菜单规则', 'title' => '排序', 'permission_name' => 'reorder_menu', 'sort' => 70],
            // 部门管理
            ['_parent_title' => '部门管理', 'title' => '列表', 'permission_name' => 'view_any_department', 'sort' => 10],
            ['_parent_title' => '部门管理', 'title' => '查看', 'permission_name' => 'view_department', 'sort' => 20],
            ['_parent_title' => '部门管理', 'title' => '新增', 'permission_name' => 'create_department', 'sort' => 30],
            ['_parent_title' => '部门管理', 'title' => '编辑', 'permission_name' => 'update_department', 'sort' => 40],
            ['_parent_title' => '部门管理', 'title' => '删除', 'permission_name' => 'delete_department', 'sort' => 50],
            ['_parent_title' => '部门管理', 'title' => '恢复', 'permission_name' => 'restore_department', 'sort' => 60],
            ['_parent_title' => '部门管理', 'title' => '排序', 'permission_name' => 'reorder_department', 'sort' => 70],
            // 操作日志
            ['_parent_title' => '操作日志', 'title' => '列表', 'permission_name' => 'view_any_activity_log', 'sort' => 10],
            ['_parent_title' => '操作日志', 'title' => '查看', 'permission_name' => 'view_activity_log', 'sort' => 20],
        ];
    }

    /**
     * 获取系统配置菜单定义
     *
     * @return list<array<string, mixed>>
     */
    private function configMenus(): array
    {
        return [
            [
                'title'           => '基础配置',
                'icon'            => 'heroicon-o-cog-6-tooth',
                'route_name'      => 'filament.admin.pages.settings.general',
                'url'             => null,
                'permission_name' => 'view_general_settings',
                'target'          => 'self',
            ],
            [
                'title'           => '上传配置',
                'icon'            => 'heroicon-o-arrow-up-tray',
                'route_name'      => 'filament.admin.pages.settings.upload',
                'url'             => null,
                'permission_name' => 'view_upload_settings',
                'target'          => 'self',
            ],
            [
                'title'           => '安全配置',
                'icon'            => 'heroicon-o-shield-check',
                'route_name'      => 'filament.admin.pages.settings.security',
                'url'             => null,
                'permission_name' => 'view_security_settings',
                'target'          => 'self',
            ],
            [
                'title'           => '日志配置',
                'icon'            => 'heroicon-o-document-text',
                'route_name'      => 'filament.admin.pages.settings.log',
                'url'             => null,
                'permission_name' => 'view_log_settings',
                'target'          => 'self',
            ],
        ];
    }

    /**
     * 在"系统管理"组下添加媒体库菜单条目
     */
    private function seedMediaMenu(int $systemGroupId): void
    {
        Menu::query()->updateOrCreate(
            [
                'title'     => '媒体库',
                'source'    => 'core',
                'type'      => 'menu',
                'parent_id' => $systemGroupId,
            ],
            [
                'icon'            => 'heroicon-o-photo',
                'route_name'      => 'filament.admin.resources.media.index',
                'url'             => null,
                'sort'            => 70,
                'permission_name' => 'view_any_media',
                'is_active'       => true,
                'target'          => 'self',
                'source'          => 'core',
                'type'            => 'menu',
                'parent_id'       => $systemGroupId,
            ],
        );
    }
}
