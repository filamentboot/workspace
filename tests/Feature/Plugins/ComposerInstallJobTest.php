<?php

use FilamentAdmin\Jobs\ComposerInstallJob;
use FilamentAdmin\Models\Plugin;
use FilamentAdmin\Services\EnvironmentChecker;
use FilamentAdmin\Services\PluginManager;
use Illuminate\Support\Facades\Queue;

/**
 * ComposerInstallJob 分发与状态变更测试（MKTPLACE-02）
 *
 * 覆盖场景：
 * 1. PluginManager::install() 分发 ComposerInstallJob（Queue::fake，不触发真实 composer）
 * 2. 分发时 init_status 立即变为 running
 * 3. 模拟成功完成后 init_status='done' 且 init_log 非空
 *
 * 威胁缓解：T-12-00-01 — 所有断言均使用 Queue::fake()；
 * CI 环境绝不执行真实 composer 子进程。
 */
it('PluginManager::install 分发 ComposerInstallJob（MKTPLACE-02）', function () {
    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-install-plugin',
        'package_name' => 'test/install-plugin',
        'init_status'  => 'pending',
    ]);

    // 环境自检通过：注入 EnvironmentChecker mock
    $checkerMock = Mockery::mock(EnvironmentChecker::class);
    $checkerMock->shouldReceive('check')->once()->andReturn([
        'ok'            => true,
        'composer_path' => '/usr/local/bin/composer',
        'issues'        => [],
    ]);
    app()->instance(EnvironmentChecker::class, $checkerMock);

    $manager = app(PluginManager::class);
    $manager->install($plugin);

    // 断言：Job 已被推入队列（无真实 composer 执行）
    Queue::assertPushed(ComposerInstallJob::class, function ($job) use ($plugin) {
        return $job->pluginId === $plugin->id
            && $job->packageName === 'test/install-plugin';
    });
});

it('install 后 init_status 立即变为 running（MKTPLACE-02）', function () {
    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-install-running',
        'package_name' => 'test/install-running',
        'init_status'  => 'pending',
    ]);

    $checkerMock = Mockery::mock(EnvironmentChecker::class);
    $checkerMock->shouldReceive('check')->once()->andReturn([
        'ok'            => true,
        'composer_path' => '/usr/local/bin/composer',
        'issues'        => [],
    ]);
    app()->instance(EnvironmentChecker::class, $checkerMock);

    $manager = app(PluginManager::class);
    $manager->install($plugin);

    // install() 调用后立即将状态置为 running，Job 异步完成余下步骤
    $plugin->refresh();
    expect($plugin->init_status)->toBe('running');
});

it('ComposerInstallJob 成功执行后 init_status=done 且 init_log 非空（MKTPLACE-02）', function () {
    $plugin = Plugin::factory()->create([
        'slug'         => 'test-install-done',
        'package_name' => 'test/install-done',
        'init_status'  => 'running',
        'init_log'     => null,
    ]);

    // 模拟 runComposerInstall 成功（不触发真实 composer 子进程）
    $managerMock = Mockery::mock(PluginManager::class)->makePartial();
    $managerMock->shouldReceive('runComposerInstall')
        ->once()
        ->andReturnUsing(function (Plugin $p, string $pkg) {
            $p->update(['init_status' => 'done', 'init_log' => 'composer require output OK']);
        });
    app()->instance(PluginManager::class, $managerMock);

    $job = new ComposerInstallJob($plugin->id, 'test/install-done');
    $job->handle($managerMock);

    $plugin->refresh();
    expect($plugin->init_status)->toBe('done');
    expect($plugin->init_log)->not->toBeNull();
    expect($plugin->init_log)->not->toBeEmpty();
});
