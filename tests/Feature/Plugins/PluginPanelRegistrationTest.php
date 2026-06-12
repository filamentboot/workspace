<?php

use App\Models\Plugin;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\Stubs\FakeFilamentPlugin;

/**
 * 插件面板注册测试（PLUGIN-01 / D-06-02）
 *
 * 验证 AdminPanelProvider::registerEnabledPlugins：
 * 1. Cache 命中分支：is_enabled=true → Panel 真实注册（hasPlugin 强断言）
 * 2. 过滤分支：is_enabled=false → Panel 无该插件
 * 3. try/catch 分支：plugins 表不存在时 Panel 启动不抛异常（静默跳过）
 * 4. SC-1 修复验证：PluginResource 路由 filament.admin.resources.plugins.index 可达
 */

it('已启用的插件 plugin_class 被接入 AdminPanel', function (): void {
    // 创建一个 is_enabled=true、plugin_class 指向 FakeFilamentPlugin 的 Plugin 记录
    Plugin::factory()->create([
        'package_name' => 'test/fake-filament-plugin',
        'slug'         => 'fake-filament-plugin',
        'plugin_class' => FakeFilamentPlugin::class,
        'is_enabled'   => true,
    ]);

    // 清除已有缓存确保走真实查库分支
    Cache::forget('plugins.enabled_list');

    // 创建一个测试 Panel 并调用 registerEnabledPlugins
    $panel    = new Panel();
    $provider = new AdminPanelProvider(app());

    // 通过反射调用私有方法（直接测试注册逻辑）
    $method = new \ReflectionMethod(AdminPanelProvider::class, 'registerEnabledPlugins');
    $method->setAccessible(true);
    $method->invoke($provider, $panel);

    // 强制断言：hasPlugin 为 true（BLOCKER 2：不允许仅断言缓存 key 含 class string）
    expect($panel->hasPlugin('fake-filament-plugin'))->toBeTrue();

    // 附加：缓存 key 已被写入（Cache::remember 分支验证）
    expect(Cache::get('plugins.enabled_list'))->toContain(FakeFilamentPlugin::class);
});

it('未启用的插件不接入 AdminPanel', function (): void {
    // 创建 is_enabled=false 的记录
    Plugin::factory()->create([
        'package_name' => 'test/fake-filament-plugin-disabled',
        'slug'         => 'fake-filament-plugin-disabled',
        'plugin_class' => FakeFilamentPlugin::class,
        'is_enabled'   => false,
    ]);

    Cache::forget('plugins.enabled_list');

    $panel    = new Panel();
    $provider = new AdminPanelProvider(app());

    $method = new \ReflectionMethod(AdminPanelProvider::class, 'registerEnabledPlugins');
    $method->setAccessible(true);
    $method->invoke($provider, $panel);

    // plugins.enabled_list 缓存不含 FakeFilamentPlugin（过滤分支）
    expect(Cache::get('plugins.enabled_list'))->not->toContain(FakeFilamentPlugin::class);

    // Panel 无该 plugin
    expect($panel->hasPlugin('fake-filament-plugin'))->toBeFalse();
});

it('PluginResource 路由 filament.admin.resources.plugins.index 已注册并可达', function (): void {
    // SC-1 修复验证：AdminPanelProvider 显式注册 PluginResource 后，路由必须存在
    // 确保面板已引导（Laravel Feature 测试环境下应用已启动，路由已注册）
    expect(Route::has('filament.admin.resources.plugins.index'))->toBeTrue();
});

it('plugins 表不存在时 Panel 启动不抛异常', function (): void {
    // 清缓存确保走查库分支
    Cache::forget('plugins.enabled_list');

    // 模拟表不存在：通过 DB 抛出 QueryException（用 Mockery/closure 不破坏数据库状态）
    // 直接测试：即使插件表查询抛 Throwable，registerEnabledPlugins 也不传播异常
    $panel    = new Panel();
    $provider = new AdminPanelProvider(app());

    $method = new \ReflectionMethod(AdminPanelProvider::class, 'registerEnabledPlugins');
    $method->setAccessible(true);

    // 用另一个测试手段：删除表后调用（try/catch Throwable 静默分支）
    \Illuminate\Support\Facades\Schema::dropIfExists('plugins');

    // 清缓存确保重新查库
    Cache::forget('plugins.enabled_list');

    // 调用不抛异常（静默跳过）
    expect(fn () => $method->invoke($provider, $panel))->not->toThrow(\Throwable::class);

    // 重建表（不影响其他测试）
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
});
