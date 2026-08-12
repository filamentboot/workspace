<?php

namespace Filamentboot\FilamentbootSite;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuItemResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteRedirectResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteSearchTermResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Widgets\SiteSearchTermWidget;
use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource;
use Filamentboot\FilamentbootSite\Filament\Widgets\ContactSourceWidget;
use Filamentboot\FilamentbootSite\Filament\Widgets\UnreadContactMessagesWidget;
use Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Filament\FriendLinkResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Filament\SitePackageResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament\SiteSolutionResource;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource;

/**
 * 官网插件 Filament Plugin 类
 *
 * 通过 ->plugins([SitePlugin::make()]) 注册到 Filament Panel，
 * 自动挂载 SiteSettingsPage 及十一个内容 Resource 到后台导航（官网管理分组）。
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
     * 向 Panel 注册官网设置页面与各内容资源
     *
     * 注册的内容：
     * - SiteSettingsPage：官网设置（spatie/laravel-settings）
     * - SiteBannerResource：幻灯片 + 投放位置 CRUD（二期 B1）
     * - SiteCaseResource：装修案例 CRUD（SITE-01）
     * - SiteSolutionResource：智能方案 CRUD（SITE-01）
     * - SitePackageResource：全屋套餐 CRUD（按户型 × 档位组织，2.5 期）
     * - SiteProductResource：智能产品 CRUD（SITE-01）
     * - NewsArticleResource / NewsCategoryResource：资讯 CRUD
     * - SiteCityPageResource：城市页 CRUD（三期）。区划本身没有资源，
     *   它由 filamentboot-site:import-regions 导入
     * - SitePageResource：静态页面 CRUD（SITE-01）
     * - ContactMessageResource：询盘只读 + 状态流转（D-10-15）
     * - SiteMenuResource / SiteMenuItemResource：前台导航与菜单项树（#17，
     *   后者不进导航，入口是菜单列表的「管理菜单项」动作）
     * - SiteRedirectResource：URL 重定向 CRUD（#18）
     * - FriendLinkResource：友情链接 CRUD（七期批次 5，可配置内容类型验收）
     * - AdSlotResource：广告位 CRUD（七期批次 5，可配置内容类型验收）
     * - UnreadContactMessagesWidget：未读询盘 StatsWidget（D-10-15）
     * - ContactSourceWidget：转化来源排行（三期批次 8）
     * - SiteSearchTermWidget：站内搜索缺口（三期批次 8）
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function register(Panel $panel): void
    {
        $panel
            ->pages([SiteSettingsPage::class])
            ->resources([
                SiteBannerResource::class,
                SiteCaseResource::class,
                SiteCaseCategoryResource::class,
                SiteSolutionResource::class,
                SitePackageResource::class,
                SiteProductResource::class,
                SiteProductCategoryResource::class,
                NewsArticleResource::class,
                NewsCategoryResource::class,
                SiteTagResource::class,
                SiteCityPageResource::class,
                SitePageResource::class,
                ContactMessageResource::class,
                SiteMenuResource::class,
                SiteMenuItemResource::class,
                SiteRedirectResource::class,
                SiteSearchTermResource::class,
                FriendLinkResource::class,
                AdSlotResource::class,
            ])
            ->widgets([
                UnreadContactMessagesWidget::class,
                ContactSourceWidget::class,
                SiteSearchTermWidget::class,
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
