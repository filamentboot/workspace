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
                'title'           => '幻灯片',
                'icon'            => 'heroicon-o-photo',
                'route_name'      => 'filament.admin.resources.site-banners.index',
                'permission_name' => 'view_any_site_banner',
            ],
            [
                'title'           => '装修案例',
                'icon'            => 'heroicon-o-home-modern',
                'route_name'      => 'filament.admin.resources.site-cases.index',
                'permission_name' => 'view_any_site_case',
            ],
            [
                // 分类与标签三行是 3.5 期 A 段补的：此前这三张表只能靠 seeder 维护，
                // 后台没有列表页，运营在内容表单的下拉里只选得到已有的、建不了新的。
                'title'           => '案例分类',
                'icon'            => 'heroicon-o-rectangle-stack',
                'route_name'      => 'filament.admin.resources.site-case-categories.index',
                'permission_name' => 'view_any_site_case_category',
            ],
            [
                'title'           => '智能方案',
                'icon'            => 'heroicon-o-cube',
                'route_name'      => 'filament.admin.resources.site-solutions.index',
                'permission_name' => 'view_any_site_solution',
            ],
            [
                // 排在方案与产品之间，对着访客的决策路径：
                // 先看方案（这类需求怎么解决）→ 再看套餐（我家户型多少钱）→ 最后单品
                'title'           => '全屋套餐',
                'icon'            => 'heroicon-o-squares-2x2',
                'route_name'      => 'filament.admin.resources.site-packages.index',
                'permission_name' => 'view_any_site_package',
            ],
            [
                'title'           => '智能产品',
                'icon'            => 'heroicon-o-cpu-chip',
                'route_name'      => 'filament.admin.resources.site-products.index',
                'permission_name' => 'view_any_site_product',
            ],
            [
                'title'           => '产品分类',
                'icon'            => 'heroicon-o-rectangle-stack',
                'route_name'      => 'filament.admin.resources.site-product-categories.index',
                'permission_name' => 'view_any_site_product_category',
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
                // 标签跨五类内容，所以不挂在任何一类下面，单独一行排在内容之后。
                // 它比分类更该露出来：slug 就是 /tags/{slug} 那批公开聚合页的地址。
                'title'           => '标签',
                'icon'            => 'heroicon-o-tag',
                'route_name'      => 'filament.admin.resources.site-tags.index',
                'permission_name' => 'view_any_site_tag',
            ],
            [
                // 排在内容之后：城市页是投放，不是内容创作，日常打开频率低得多
                'title'           => '城市页',
                'icon'            => 'heroicon-o-map-pin',
                'route_name'      => 'filament.admin.resources.site-city-pages.index',
                'permission_name' => 'view_any_site_city_page',
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
                'title'           => '友情链接',
                'icon'            => 'heroicon-o-link',
                'route_name'      => 'filament.admin.resources.friend-links.index',
                'permission_name' => 'view_any_friend_link',
            ],
            [
                'title'           => '广告位',
                'icon'            => 'heroicon-o-megaphone',
                'route_name'      => 'filament.admin.resources.ad-slots.index',
                'permission_name' => 'view_any_ad_slot',
            ],
            [
                'title'           => '询盘',
                'icon'            => 'heroicon-o-envelope',
                'route_name'      => 'filament.admin.resources.contact-messages.index',
                'permission_name' => 'view_any_contact_message',
            ],
            // 站内搜索词是只读报表，权限点同样是自定义的（见 SiteSearchTermPolicy）
            [
                'title'           => '站内搜索词',
                'icon'            => 'heroicon-o-magnifying-glass',
                'route_name'      => 'filament.admin.resources.site-search-terms.index',
                'permission_name' => 'view_site_search_terms',
            ],
        ];
    }
}
