<?php

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Support\Facades\Cache;

/**
 * 插件依赖关系测试（PLUGIN-06）
 *
 * 覆盖：compatibility 不兼容时 checkDependencies 返回非空警告列表
 */

it('compatibility 声明不兼容时启用插件被阻断', function () {
    // 构造一个 compatibility 声明不满足当前环境的插件
    $plugin = Plugin::factory()->create([
        'compatibility' => [
            'laravel' => '^99.0', // 故意声明不存在的版本约束
        ],
        'is_enabled' => false,
    ]);

    $manager = new PluginManager();

    // enable 前检查 checkDependencies，不兼容时抛 RuntimeException（异常优先，CLAUDE.md）
    expect(fn () => $manager->enable($plugin))->toThrow(\RuntimeException::class);

    // 插件仍为禁用状态
    expect($plugin->refresh()->is_enabled)->toBeFalse();
});

it('compatibility 声明不兼容时给出明确警告信息', function () {
    $plugin = Plugin::factory()->create([
        'compatibility' => [
            'filament' => '^1.0', // 故意声明旧版本约束
            'laravel'  => '^10.0', // 同样不兼容当前环境
        ],
    ]);

    $manager = new PluginManager();

    $issues = $manager->checkDependencies($plugin);

    // 返回非空的不兼容提示列表
    expect($issues)->not->toBeEmpty();

    // 提示信息应包含版本约束详情
    $combinedMessages = implode(' ', $issues);
    expect($combinedMessages)->toContain('filament');
});
