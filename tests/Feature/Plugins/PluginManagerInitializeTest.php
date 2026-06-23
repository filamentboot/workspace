<?php

use App\Services\PluginManager;
use Filamentboot\Models\Plugin;
use Illuminate\Support\Facades\Cache;

/**
 * 插件管理器初始化测试（PLUGIN-02 / PLUGIN-03）
 *
 * 覆盖：成功初始化置 init_status=done、失败时保留 init_log、重试整体幂等
 * 注意：失败路径使用 partialMock 拦截内部步骤抛异常，不使用 Artisan::fake，
 * 确保失败路径的 init_log 非空断言真正覆盖日志写入逻辑（Plan 02 WARNING）
 */

/**
 * 创建允许 mock 受保护方法的 PluginManager partialMock
 */
function makePluginManagerMock(): PluginManager
{
    /** @var PluginManager */
    return Mockery::mock(PluginManager::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
}

it('PluginManager initialize 同步执行后 init_status 变为 done', function () {
    $plugin = Plugin::factory()->create([
        'slug'         => 'test-plugin-success',
        'init_status'  => 'pending',
        'plugin_class' => null,
        'init_log'     => null,
    ]);

    // 用 partialMock 绕过真实 Artisan::call（避免测试环境数据库冲突）
    // runMigrate/runPublish/runSeeder 改为 no-op，但 appendInitLog / updateCacheStatus 等私有方法仍真实执行
    $manager = makePluginManagerMock();
    $manager->shouldReceive('runMigrate')->once()->andReturnUsing(function () {});
    $manager->shouldReceive('runPublish')->once()->andReturnUsing(function () {});
    $manager->shouldReceive('runSeeder')->once()->andReturnUsing(function () {});

    $manager->initialize($plugin);

    $plugin->refresh();

    expect($plugin->init_status)->toBe('done');

    // Cache 应写入 status=done
    $cacheData = Cache::get('plugin.init.test-plugin-success');
    expect($cacheData)->not->toBeNull();
    expect($cacheData['status'])->toBe('done');
});

it('初始化失败时 init_log 保留错误信息且 init_status 为 failed', function () {
    $plugin = Plugin::factory()->create([
        'slug'         => 'test-plugin-fail',
        'init_status'  => 'pending',
        'plugin_class' => null,
        'init_log'     => null,
    ]);

    // 策略 (a)：partialMock 拦截 runSeeder 抛异常（非 Artisan::fake）
    // 确保失败路径能执行真实的 appendInitLog 日志写入逻辑
    $manager = makePluginManagerMock();
    $manager->shouldReceive('runMigrate')->once()->andReturnUsing(function () {});
    $manager->shouldReceive('runPublish')->once()->andReturnUsing(function () {});
    $manager->shouldReceive('runSeeder')
        ->once()
        ->andThrow(new RuntimeException('Seeder 执行失败：App\\Seeders\\NonExistentSeeder 类不存在'));

    $manager->initialize($plugin);

    $plugin->refresh();

    // 失败路径断言
    expect($plugin->init_status)->toBe('failed');
    expect($plugin->init_log)->not->toBeNull();
    expect($plugin->init_log)->not->toBeEmpty();
    expect($plugin->init_log)->toContain('Seeder 执行失败');

    // Cache 应写入 status=failed
    $cacheData = Cache::get('plugin.init.test-plugin-fail');
    expect($cacheData)->not->toBeNull();
    expect($cacheData['status'])->toBe('failed');
});

it('重复调用 initialize 整体幂等不抛异常', function () {
    $plugin = Plugin::factory()->create([
        'slug'         => 'test-plugin-idempotent',
        'init_status'  => 'pending',
        'plugin_class' => null,
        'init_log'     => null,
    ]);

    // partialMock：两次调用均成功（D-06-12 整体幂等）
    $manager = makePluginManagerMock();
    $manager->shouldReceive('runMigrate')->twice()->andReturnUsing(function () {});
    $manager->shouldReceive('runPublish')->twice()->andReturnUsing(function () {});
    $manager->shouldReceive('runSeeder')->twice()->andReturnUsing(function () {});

    // 连续两次调用不抛异常
    $manager->initialize($plugin);
    $manager->initialize($plugin);

    $plugin->refresh();

    // 最终状态仍为 done
    expect($plugin->init_status)->toBe('done');
});
