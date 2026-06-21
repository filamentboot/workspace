<?php

namespace Filamentboot\FilamentbootOss;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filamentboot\FilamentbootOss\Filament\Pages\OssSettingsPage;

/**
 * 阿里云 OSS 存储 Filament 插件
 *
 * 通过 ->plugins([OssPlugin::make()]) 注册到 Filament Panel，
 * 自动挂载 OssSettingsPage 到后台导航。
 * Storage::extend('oss') 注册由 OssServiceProvider::boot() 完成。
 */
class OssPlugin implements Plugin
{
    /**
     * 创建插件实例（通过 IoC 容器）
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
        return 'filamentboot-oss';
    }

    /**
     * 向 Panel 注册 OSS 配置页面
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function register(Panel $panel): void
    {
        $panel->pages([OssSettingsPage::class]);
    }

    /**
     * 插件启动钩子（Storage::extend 已在 OssServiceProvider::boot 完成）
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function boot(Panel $panel): void
    {
        // Storage::extend('oss') 注册由 OssServiceProvider::boot() 执行，
        // 此处无需重复操作。
    }
}
