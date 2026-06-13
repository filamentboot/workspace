<?php

namespace LaravelStack\FilamentAdminSite;

use Filament\Contracts\Plugin;
use Filament\Panel;
use LaravelStack\FilamentAdminSite\Filament\Pages\SiteSettingsPage;

/**
 * 官网插件 Filament Plugin 类
 *
 * 通过 ->plugins([SitePlugin::make()]) 注册到 Filament Panel，
 * 自动挂载 SiteSettingsPage 到后台导航。
 * 前台路由/视图/Livewire 组件注册由 SiteServiceProvider::boot() 完成。
 *
 * 本 Plan 10-01 仅注册 SiteSettingsPage，五个 Resource 将由 Plan 10-03 追加：
 * $panel->resources([
 *     SiteCaseResource::class,
 *     SiteSolutionResource::class,
 *     SiteProductResource::class,
 *     SitePageResource::class,
 *     ContactMessageResource::class,
 * ]);
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
     * 插件唯一标识符，与 extra.filament-admin.slug 保持一致
     */
    public function getId(): string
    {
        return 'filament-admin-site';
    }

    /**
     * 向 Panel 注册官网设置页面
     *
     * 五个内容资源（SiteCaseResource、SiteSolutionResource、SiteProductResource、
     * SitePageResource、ContactMessageResource）将由 Plan 10-03 在此处追加注册。
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function register(Panel $panel): void
    {
        $panel->pages([SiteSettingsPage::class]);
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
