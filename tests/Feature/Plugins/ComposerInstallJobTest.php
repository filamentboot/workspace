<?php

use FilamentAdmin\Models\Plugin;
use Illuminate\Support\Facades\Queue;

/**
 * ComposerInstallJob 分发与状态变更测试（MKTPLACE-02）
 *
 * 覆盖场景：
 * 1. PluginManager::install() 分发 ComposerInstallJob（Queue::fake，不触发真实 composer）
 * 2. 分发时携带正确的 pluginId + packageName 参数
 * 3. 模拟成功完成后 init_status='done' 且 init_log 非空
 *
 * 威胁缓解：T-12-00-01 — 所有断言均使用 Queue::fake()；
 * CI 环境绝不执行真实 composer 子进程。
 */

it('PluginManager::install 分发 ComposerInstallJob（MKTPLACE-02）', function () {
    // 防御：Wave 0 目标类尚未实现，标记为待实现
    $this->markTestIncomplete('MKTPLACE-02: ComposerInstallJob implemented in Wave 2');

    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-install-plugin',
        'package_name' => 'test/install-plugin',
        'init_status'  => 'pending',
    ]);

    /** @var \App\Services\PluginManager $manager */
    $manager = app(\App\Services\PluginManager::class);
    $manager->install($plugin);

    // 断言：Job 已被推入队列（无真实 composer 执行）
    Queue::assertPushed(\FilamentAdmin\Jobs\ComposerInstallJob::class, function ($job) use ($plugin) {
        return $job->pluginId === $plugin->id
            && $job->packageName === 'test/install-plugin';
    });
});

it('install 后 init_status 立即变为 running（MKTPLACE-02）', function () {
    $this->markTestIncomplete('MKTPLACE-02: ComposerInstallJob implemented in Wave 2');

    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-install-running',
        'package_name' => 'test/install-running',
        'init_status'  => 'pending',
    ]);

    /** @var \App\Services\PluginManager $manager */
    $manager = app(\App\Services\PluginManager::class);
    $manager->install($plugin);

    // install() 调用后立即将状态置为 running，Job 异步完成余下步骤
    $plugin->refresh();
    expect($plugin->init_status)->toBe('running');
});

it('ComposerInstallJob 成功执行后 init_status=done 且 init_log 非空（MKTPLACE-02）', function () {
    $this->markTestIncomplete('MKTPLACE-02: ComposerInstallJob handle() implemented in Wave 2');

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-install-done',
        'package_name' => 'test/install-done',
        'init_status'  => 'running',
        'init_log'     => null,
    ]);

    // Wave 2 实现：ComposerInstallJob::handle() 使用 Process double 模拟成功
    // 此处使用 Queue::fake 确保 CI 不触发真实 composer

    Queue::fake();

    $job = new \FilamentAdmin\Jobs\ComposerInstallJob($plugin->id, 'test/install-done');
    $job->handle();

    $plugin->refresh();
    expect($plugin->init_status)->toBe('done');
    expect($plugin->init_log)->not->toBeNull();
    expect($plugin->init_log)->not->toBeEmpty();
});
