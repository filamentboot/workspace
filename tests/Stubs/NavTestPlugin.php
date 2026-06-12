<?php

namespace Tests\Stubs;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationItem;
use Filament\Panel;

/**
 * 带导航项的测试插件 stub，用于 E2E 验证插件启停后侧边栏动态变化
 *
 * register() 向面板注入一个唯一的 navigationItems 条目，
 * AdminNavigationBuilder::resolveFromPanel() 合并这些条目后侧边栏应出现对应链接。
 */
class NavTestPlugin implements Plugin
{
    /** 导航标签，E2E 测试通过此字符串定位侧边栏条目 */
    public const NAV_LABEL = 'E2E测试导航项';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'nav-test-plugin';
    }

    /**
     * 注册一个可被 AdminNavigationBuilder 识别的导航项
     */
    public function register(Panel $panel): void
    {
        $panel->navigationItems([
            NavigationItem::make(self::NAV_LABEL)
                ->url('/admin')
                ->icon('heroicon-o-sparkles')
                ->group('测试分组'),
        ]);
    }

    public function boot(Panel $panel): void {}
}
