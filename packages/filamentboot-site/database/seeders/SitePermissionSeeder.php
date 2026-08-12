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

        // 八类内容资源共享同一套 CRUD 动作（均有软删除）
        foreach (['site_banner', 'site_case', 'site_solution', 'site_package', 'site_product', 'site_city_page', 'site_page', 'news_article'] as $resource) {
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

            // 分类与标签：三张表都没有软删除，各少 restore / force_delete 两个动作。
            // 案例分类 / 产品分类 / 标签三套是 3.5 期 A 段补的——在那之前它们
            // 只能靠 seeder 维护，后台连列表页都没有。
            ...$this->flatResourcePermissions(['news_category', 'site_case_category', 'site_product_category', 'site_tag']),

            // 友情链接 / 广告位（七期批次 5，可配置内容类型系统验收产物）：
            // 无软删除，但 Resource 的 toolbarActions 带 DeleteBulkAction，
            // 比上面的分类/标签多一个 delete_any，不能套 flatResourcePermissions()
            ...$this->bulkDeletableResourcePermissions(['friend_link', 'ad_slot']),

            // 询盘：前台写入，后台只读 + 状态流转 + 跟进，无新建
            'view_any_contact_message',
            'view_contact_message',
            'update_contact_message',
            'delete_contact_message',

            // 询盘导出（A4）：PII 批量外流，独立权限点
            'export_contact_message',

            // 设置页（Shield 由页面类名推导，带 _page 后缀）
            'view_site_settings_page',

            // 内容发布（#14，批次 1.5a 起覆盖全部 6 类内容）：与 update_{resource}
            // 分开，内容编辑只能提交审核。权限点由第一个需要它的任务创建——
            // #19 只做三层角色组装，不再负责建点，否则「编辑者不能直接发布」
            // 就得等 #19 才能生效。
            'publish_site_page',
            'publish_site_case',
            'publish_news_article',
            'publish_site_solution',
            'publish_site_product',
            'publish_site_package',
            'publish_site_city_page',

            // 内容版本回滚（#15，批次 1.5c 起覆盖全部 7 类内容）：整体改写正文，
            // 不等同于普通编辑
            'rollback_site_page',
            'rollback_site_case',
            'rollback_news_article',
            'rollback_site_solution',
            'rollback_site_product',
            'rollback_site_package',
            'rollback_site_city_page',

            // 前台导航（#17）：菜单与菜单项共用一个权限点——能改导航结构的人
            // 一定也能新建删除菜单项，拆开只是给角色配置添麻烦
            'manage_site_menu',

            // URL 重定向（#18）：能改跳转规则等于能把任意旧地址导去别处，独立授权
            'manage_site_redirect',

            // 站内搜索词：只读报表，给到内容编辑这一档——它直接决定下一批写什么
            'view_site_search_terms',

            // 站点管理（A3 的自定义前台代码块等高风险配置）
            'manage_site_settings',
        ];
    }

    /**
     * 无软删除资源的五个 CRUD 权限点
     *
     * @param  list<string>  $resources
     * @return list<string>
     */
    private function flatResourcePermissions(array $resources): array
    {
        $permissions = [];

        foreach ($resources as $resource) {
            foreach (['view_any', 'view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = $action.'_'.$resource;
            }
        }

        return $permissions;
    }

    /**
     * 无软删除、但列表页带批量删除动作的资源，六个权限点
     *
     * 比 flatResourcePermissions() 多一个 delete_any——对应 Resource
     * table() 里 toolbarActions() 声明的 DeleteBulkAction。
     *
     * @param  list<string>  $resources
     * @return list<string>
     */
    private function bulkDeletableResourcePermissions(array $resources): array
    {
        $permissions = [];

        foreach ($resources as $resource) {
            foreach (['view_any', 'view', 'create', 'update', 'delete', 'delete_any'] as $action) {
                $permissions[] = $action.'_'.$resource;
            }
        }

        return $permissions;
    }
}
