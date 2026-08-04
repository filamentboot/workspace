<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Filamentboot\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * 官网插件后台菜单种子
 *
 * 后台侧边栏由 AdminNavigationBuilder 从 menus 表构建，Filament 基于 Resource
 * 静态属性（$navigationGroup / $navigationSort）自动生成导航的机制被 Panel 的
 * ->navigation() 回调整体旁路。因此 SitePlugin 注册的资源即使路由已就绪，
 * 未在 menus 表登记也不会出现在导航中——本 Seeder 负责补齐这些登记行。
 *
 * 幂等：以 (title, source, type, parent_id) 为唯一键 updateOrCreate，可重复执行。
 */
class SiteMenuSeeder extends Seeder
{
    /**
     * 菜单来源标记，与 plugins.slug 体系区分开的 menus.source 取值
     */
    private const SOURCE = 'plugin';

    /**
     * 顶级分组标题
     */
    private const GROUP_TITLE = '官网管理';

    /**
     * 写入官网管理分组及其子菜单
     */
    public function run(): void
    {
        $group = Menu::query()->updateOrCreate(
            [
                'title'     => self::GROUP_TITLE,
                'source'    => self::SOURCE,
                'type'      => 'menu',
                'parent_id' => Menu::defaultParentKey(),
            ],
            [
                'icon'            => 'heroicon-o-globe-alt',
                'route_name'      => null,
                'url'             => null,
                'link_type'       => 'route',
                'permission_name' => null,
                // 排在仪表盘（5）之后、系统管理（10）之前：内容管理是日常高频入口，
                // 系统管理 / 系统配置 / 插件市场 沉到下方
                'sort'      => 8,
                'is_active' => true,
                'target'    => 'self',
            ],
        );

        foreach ($this->menus() as $index => $menu) {
            Menu::query()->updateOrCreate(
                [
                    'title'     => $menu['title'],
                    'source'    => self::SOURCE,
                    'type'      => 'menu',
                    'parent_id' => $group->id,
                ],
                [
                    ...$menu,
                    'url'       => null,
                    'link_type' => 'route',
                    'sort'      => ($index + 1) * 10,
                    'is_active' => true,
                    'target'    => 'self',
                ],
            );
        }
    }

    /**
     * 官网管理分组下的子菜单定义
     *
     * 顺序与各 Resource / Page 的 $navigationSort 保持一致（设置页在前，询盘在末）。
     *
     * permission_name 命名来源有两套，不能混用：
     * - Resource：BasePolicy 由 Policy 类名推导，如 SiteCasePolicy -> view_any_site_case
     * - Page：Shield 由页面类名推导并带 _page 后缀，如 SiteSettingsPage -> view_site_settings_page
     *
     * @return list<array{title: string, icon: string, route_name: string, permission_name: string}>
     */
    private function menus(): array
    {
        return [
            [
                'title'           => '网站设置',
                'icon'            => 'heroicon-o-globe-alt',
                'route_name'      => 'filament.admin.pages.settings.site',
                'permission_name' => 'view_site_settings_page',
            ],
            [
                'title'           => '装修案例',
                'icon'            => 'heroicon-o-home-modern',
                'route_name'      => 'filament.admin.resources.site-cases.index',
                'permission_name' => 'view_any_site_case',
            ],
            [
                'title'           => '智能方案',
                'icon'            => 'heroicon-o-cube',
                'route_name'      => 'filament.admin.resources.site-solutions.index',
                'permission_name' => 'view_any_site_solution',
            ],
            [
                'title'           => '智能产品',
                'icon'            => 'heroicon-o-cpu-chip',
                'route_name'      => 'filament.admin.resources.site-products.index',
                'permission_name' => 'view_any_site_product',
            ],
            [
                'title'           => '资讯文章',
                'icon'            => 'heroicon-o-newspaper',
                'route_name'      => 'filament.admin.resources.news-articles.index',
                'permission_name' => 'view_any_news_article',
            ],
            [
                'title'           => '资讯分类',
                'icon'            => 'heroicon-o-rectangle-stack',
                'route_name'      => 'filament.admin.resources.news-categories.index',
                'permission_name' => 'view_any_news_category',
            ],
            [
                'title'           => '静态页面',
                'icon'            => 'heroicon-o-document',
                'route_name'      => 'filament.admin.resources.site-pages.index',
                'permission_name' => 'view_any_site_page',
            ],
            // 导航菜单与重定向随 #17 / #18 交付，当时漏了这两行登记：
            // 侧边栏由本表驱动，没有登记行的资源即使路由已注册也只能靠直链访问。
            // 两者的 Policy 不走 BasePolicy，权限点是自定义的 manage_*（见 SiteMenuPolicy）。
            [
                'title'           => '导航菜单',
                'icon'            => 'heroicon-o-bars-3',
                'route_name'      => 'filament.admin.resources.site-menus.index',
                'permission_name' => 'manage_site_menu',
            ],
            [
                'title'           => '重定向',
                'icon'            => 'heroicon-o-arrow-uturn-right',
                'route_name'      => 'filament.admin.resources.site-redirects.index',
                'permission_name' => 'manage_site_redirect',
            ],
            [
                'title'           => '询盘',
                'icon'            => 'heroicon-o-envelope',
                'route_name'      => 'filament.admin.resources.contact-messages.index',
                'permission_name' => 'view_any_contact_message',
            ],
        ];
    }
}
