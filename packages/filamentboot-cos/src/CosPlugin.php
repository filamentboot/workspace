<?php

namespace Filamentboot\FilamentbootCos;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filamentboot\FilamentbootCos\Filament\Pages\CosSettingsPage;

/**
 * 腾讯云 COS 存储插件
 *
 * 用户在 AdminPanelProvider 中通过 ->plugins([CosPlugin::make()]) 注册。
 * cos 驱动由 overtrue/laravel-filesystem-cos 内置 ServiceProvider 自动发现注册，
 * 本插件负责注册 CosSettingsPage 到 Filament panel。
 */
class CosPlugin implements Plugin
{
    /**
     * 创建插件实例
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * 插件唯一标识符（与 extra.filamentboot.slug 一致）
     */
    public function getId(): string
    {
        return 'filamentboot-cos';
    }

    /**
     * 向 Filament panel 注册页面
     */
    public function register(Panel $panel): void
    {
        $panel->pages([CosSettingsPage::class]);
    }

    /**
     * 插件 boot 钩子
     *
     * cos 磁盘驱动由 overtrue/laravel-filesystem-cos 包的内置 ServiceProvider
     * 自动发现并注册，无需在此处手动 Storage::extend。
     */
    public function boot(Panel $panel): void
    {
        // 驱动注册由 overtrue 包处理
    }
}
