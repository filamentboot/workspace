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
                'sort'            => 40,
                'is_active'       => true,
                'target'          => 'self',
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
     * permission_name 对齐 BasePolicy 推导出的权限点；设置页无 Policy，
     * 与 OSS / COS 配置页先例一致留空。
     *
     * @return list<array{title: string, icon: string, route_name: string, permission_name: string|null}>
     */
    private function menus(): array
    {
        return [
            [
                'title'           => '网站设置',
                'icon'            => 'heroicon-o-globe-alt',
                'route_name'      => 'filament.admin.pages.settings.site',
                'permission_name' => null,
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
                'title'           => '静态页面',
                'icon'            => 'heroicon-o-document',
                'route_name'      => 'filament.admin.resources.site-pages.index',
                'permission_name' => 'view_any_site_page',
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
