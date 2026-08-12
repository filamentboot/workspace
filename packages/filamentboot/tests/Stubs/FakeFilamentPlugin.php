<?php

namespace Filamentboot\Tests\Stubs;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * 最小 Filament Plugin 存根，用于测试中验证 Panel 动态注册
 *
 * 供 PluginPanelRegistrationTest 中作为 plugin_class 指向的可注册插件，
 * 确保 $panel->plugin(app($class)) 能真实注册并被 hasPlugin('fake-filament-plugin') 命中。
 */
class FakeFilamentPlugin implements Plugin
{
    /**
     * 创建插件实例
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * 返回插件唯一标识
     */
    public function getId(): string
    {
        return 'fake-filament-plugin';
    }

    /**
     * 注册插件到 Panel（空实现，仅供测试）
     */
    public function register(Panel $panel): void {}

    /**
     * 启动插件（空实现，仅供测试）
     */
    public function boot(Panel $panel): void {}
}
