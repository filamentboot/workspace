<?php

namespace Filamentboot\FilamentbootSite;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuItemResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteRedirectResource;
use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource;
use Filamentboot\FilamentbootSite\Filament\Widgets\UnreadContactMessagesWidget;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament\SiteSolutionResource;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource;

/**
 * 官网插件 Filament Plugin 类
 *
 * 通过 ->plugins([SitePlugin::make()]) 注册到 Filament Panel，
 * 自动挂载 SiteSettingsPage 及七个内容 Resource 到后台导航（官网管理分组）。
 * 前台路由/视图/Livewire 组件注册由 SiteServiceProvider::boot() 完成。
 */
class SitePlugin implements Plugin
{
    /**
     * 通过 IoC 容器创建插件实例
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * 插件唯一标识符，与 extra.filamentboot.slug 保持一致
     */
    public function getId(): string
    {
        return 'filamentboot-site';
    }

    /**
     * 向 Panel 注册官网设置页面与五个内容资源
     *
     * 注册的内容：
     * - SiteSettingsPage：官网设置（spatie/laravel-settings）
     * - SiteCaseResource：装修案例 CRUD（SITE-01）
     * - SiteSolutionResource：智能方案 CRUD（SITE-01）
     * - SiteProductResource：智能产品 CRUD（SITE-01）
     * - NewsArticleResource / NewsCategoryResource：资讯 CRUD
     * - SitePageResource：静态页面 CRUD（SITE-01）
     * - ContactMessageResource：询盘只读 + 状态流转（D-10-15）
     * - SiteMenuResource / SiteMenuItemResource：前台导航与菜单项树（#17，
     *   后者不进导航，入口是菜单列表的「管理菜单项」动作）
     * - SiteRedirectResource：URL 重定向 CRUD（#18）
     * - UnreadContactMessagesWidget：未读询盘 StatsWidget（D-10-15）
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function register(Panel $panel): void
    {
        $panel
            ->pages([SiteSettingsPage::class])
            ->resources([
                SiteCaseResource::class,
                SiteSolutionResource::class,
                SiteProductResource::class,
                NewsArticleResource::class,
                NewsCategoryResource::class,
                SitePageResource::class,
                ContactMessageResource::class,
                SiteMenuResource::class,
                SiteMenuItemResource::class,
                SiteRedirectResource::class,
            ])
            ->widgets([
                UnreadContactMessagesWidget::class,
            ]);
    }

    /**
     * 插件启动钩子
     *
     * 前台路由、视图、Livewire 组件注册已在 SiteServiceProvider::boot() 完成，
     * 此处无需重复操作。
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function boot(Panel $panel): void
    {
        // 前台资源注册由 SiteServiceProvider::boot() 执行
    }
}
