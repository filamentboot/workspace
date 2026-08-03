<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * 官网插件权限点种子
 *
 * 此前官网各 Resource 的权限点（view_any_site_case 等）只在 SiteMenuSeeder 里
 * 被引用，从未被任何 seeder 创建过——结果是除超管（主包 Gate::before 放行）之外，
 * 任何角色都无从被授予官网管理权限。本 Seeder 补齐这批权限点。
 *
 * 命名与 BasePolicy 的 {action}_{resource_snake_case} 推导保持一致；
 * 设置页的 view_site_settings_page 由 Shield 按页面类名推导并带 _page 后缀。
 *
 * 幂等：firstOrCreate，可重复执行。
 */
class SitePermissionSeeder extends Seeder
{
    /**
     * 权限守卫名（后台 guard）
     */
    private const GUARD = 'admin';

    /**
     * 写入官网插件的全部权限点
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => self::GUARD,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * 官网插件权限点清单
     *
     * @return list<string>
     */
    private function permissions(): array
    {
        $resourcePermissions = [];

        // 五类内容资源共享同一套 CRUD 动作（均有软删除）
        foreach (['site_case', 'site_solution', 'site_product', 'site_page', 'news_article'] as $resource) {
            foreach ([
                'view_any',
                'view',
                'create',
                'update',
                'delete',
                'restore',
                'force_delete',
            ] as $action) {
                $resourcePermissions[] = $action.'_'.$resource;
            }
        }

        return [
            ...$resourcePermissions,

            // 资讯分类：无软删除，少 restore / force_delete 两个动作
            'view_any_news_category',
            'view_news_category',
            'create_news_category',
            'update_news_category',
            'delete_news_category',

            // 询盘：前台写入，后台只读 + 状态流转 + 跟进，无新建
            'view_any_contact_message',
            'view_contact_message',
            'update_contact_message',
            'delete_contact_message',

            // 询盘导出（A4）：PII 批量外流，独立权限点
            'export_contact_message',

            // 设置页（Shield 由页面类名推导，带 _page 后缀）
            'view_site_settings_page',

            // 站点管理（A3 的自定义前台代码块等高风险配置）
            'manage_site_settings',
        ];
    }
}
