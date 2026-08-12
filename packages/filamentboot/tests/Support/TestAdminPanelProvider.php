<?php

namespace Filamentboot\Tests\Support;

use Filament\Panel;
use Filament\PanelProvider;
use Filamentboot\FilamentbootPlugin;

/**
 * 测试专用最小 Filament 管理面板 Provider
 *
 * 包内没有真实宿主 AdminPanelProvider，供需要真实 Panel 注册环境
 * （路由存在性断言、Livewire 页面测试）的测试复用：只挂载 FilamentbootPlugin，
 * 不附带宿主生产环境的登录页 / 品牌 / 中间件等定制。
 */
class TestAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->login()
            ->plugin(FilamentbootPlugin::make());
    }
}
