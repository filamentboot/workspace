<?php

namespace Filamentboot\Tests\Feature\Plugins;

use AlizHarb\ActivityLog\ActivityLogServiceProvider as FilamentActivityLogServiceProvider;
use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use Filamentboot\FilamentbootPlugin;
use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\Plugin;
use Filamentboot\Tests\Stubs\FakeFilamentPlugin;
use Filamentboot\Tests\Support\TestAdminPanelProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\LivewireServiceProvider;
use Mockery;
use Orchestra\Testbench\TestCase;
use PDOException;
use ReflectionMethod;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationServiceProvider;
use Throwable;

/**
 * 插件面板注册测试（PLUGIN-01 / D-06-02）
 *
 * 验证 FilamentbootPlugin::registerEnabledPlugins（七期批次 4c 从
 * AdminPanelProvider 搬进 FilamentbootPlugin）：
 * 1. Cache 命中分支：is_enabled=true → Panel 真实注册（hasPlugin 强断言）
 * 2. 过滤分支：is_enabled=false → Panel 无该插件
 * 3. try/catch 分支：plugins 表不存在时 Panel 启动不抛异常（静默跳过）
 * 4. SC-1 修复验证：PluginResource 路由 filament.admin.resources.plugins.index 可达
 *
 * 包内没有真实宿主 AdminPanelProvider，后两个用例需要真实 Panel 注册环境
 * 才能验证路由确实被注册，借助 Filamentboot\Tests\Support\TestAdminPanelProvider
 * （最小 PanelProvider，只挂 FilamentbootPlugin）+ 显式注册 Filament /
 * Livewire ServiceProvider 搭建（Testbench 默认 skeleton 无真实 vendor 目录，
 * 包自动发现在此环境下失效）。FilamentbootPlugin::register() 还会挂载
 * FilamentShield / 2FA / ActivityLog 三个面板插件，它们各自的路由/资源在
 * 注册阶段就会读取自己的 config，因此对应三个 ServiceProvider 也必须显式注册，
 * 否则报 "config 未合并" 类的 TypeError。
 */
class PluginPanelRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentbootServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            LaravelSettingsServiceProvider::class,
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            FilamentShieldServiceProvider::class,
            TwoFactorAuthenticationServiceProvider::class,
            FilamentActivityLogServiceProvider::class,
            TestAdminPanelProvider::class,
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 已启用的插件 plugin_class 被接入 AdminPanel
     */
    public function test_enabled_plugin_class_is_wired_into_admin_panel(): void
    {
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
        $panel  = new Panel;
        $plugin = FilamentbootPlugin::make();

        // 通过反射调用私有方法（直接测试注册逻辑）
        $method = new ReflectionMethod(FilamentbootPlugin::class, 'registerEnabledPlugins');
        $method->setAccessible(true);
        $method->invoke($plugin, $panel);

        // 强制断言：hasPlugin 为 true（BLOCKER 2：不允许仅断言缓存 key 含 class string）
        $this->assertTrue($panel->hasPlugin('fake-filament-plugin'));

        // 附加：缓存 key 已被写入（Cache::remember 分支验证）
        $this->assertContains(FakeFilamentPlugin::class, Cache::get('plugins.enabled_list'));
    }

    /**
     * 未启用的插件不接入 AdminPanel
     */
    public function test_disabled_plugin_is_not_wired_into_admin_panel(): void
    {
        // 创建 is_enabled=false 的记录
        Plugin::factory()->create([
            'package_name' => 'test/fake-filament-plugin-disabled',
            'slug'         => 'fake-filament-plugin-disabled',
            'plugin_class' => FakeFilamentPlugin::class,
            'is_enabled'   => false,
        ]);

        Cache::forget('plugins.enabled_list');

        $panel  = new Panel;
        $plugin = FilamentbootPlugin::make();

        $method = new ReflectionMethod(FilamentbootPlugin::class, 'registerEnabledPlugins');
        $method->setAccessible(true);
        $method->invoke($plugin, $panel);

        // plugins.enabled_list 缓存不含 FakeFilamentPlugin（过滤分支）
        $this->assertNotContains(FakeFilamentPlugin::class, Cache::get('plugins.enabled_list'));

        // Panel 无该 plugin
        $this->assertFalse($panel->hasPlugin('fake-filament-plugin'));
    }

    /**
     * PluginResource 路由 filament.admin.resources.plugins.index 已注册并可达
     */
    public function test_plugin_resource_route_is_registered_and_reachable(): void
    {
        // SC-1 修复验证：FilamentbootPlugin 显式注册 PluginResource 后，路由必须存在
        // 确保面板已引导（TestAdminPanelProvider 已在 getPackageProviders 里注册，
        // 应用启动时路由已注册）
        $this->assertTrue(Route::has('filament.admin.resources.plugins.index'));
    }

    /**
     * plugins 表不存在时 Panel 启动不抛异常
     */
    public function test_panel_boot_does_not_throw_when_plugins_table_missing(): void
    {
        // WR-05 修复：不再使用 Schema::dropIfExists 实际删表
        // MySQL DDL 语句会自动提交事务，破坏 RefreshDatabase 隔离。
        // 改用 Cache Facade Mock，在 Cache::remember 回调中直接抛出 QueryException，
        // 模拟 plugins 表不存在场景，完全不触碰数据库 Schema。
        $queryException = new QueryException(
            'mysql',
            'select `plugin_class` from `plugins` where `is_enabled` = ?',
            [],
            new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'plugins' doesn't exist")
        );

        Cache::shouldReceive('get')
            ->withAnyArgs()
            ->andReturnNull()
            ->byDefault();

        Cache::shouldReceive('remember')
            ->once()
            ->with('plugins.enabled_list', 30, Mockery::type(\Closure::class))
            ->andThrow($queryException);

        $panel  = new Panel;
        $plugin = FilamentbootPlugin::make();

        $method = new ReflectionMethod(FilamentbootPlugin::class, 'registerEnabledPlugins');
        $method->setAccessible(true);

        // 调用不抛异常（静默跳过，try/catch Throwable 分支）
        try {
            $method->invoke($plugin, $panel);
            $threw = false;
        } catch (Throwable) {
            $threw = true;
        }

        $this->assertFalse($threw, 'plugins 表不存在时 registerEnabledPlugins 不应抛出异常');
    }
}
