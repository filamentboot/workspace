<?php

use App\Filament\Pages\Marketplace\MarketplacePage;
use Filamentboot\Jobs\ComposerInstallJob;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\EnvironmentChecker;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * MarketplacePage installPlugin / installCommunityPlugin firstOrCreate 测试
 *
 * 覆盖场景：
 * 1. 官方目录插件安装创建 Plugin 行（source='official_listed', installed_version='0.1.0'）并推送 Job
 * 2. 社区插件安装创建 Plugin 行（source='community'）并推送 Job
 * 3. 空包名是空操作（行数不变, 无 Job 推送）
 * 4. 同一包名重复安装是幂等的（firstOrCreate — 不重复创建行）
 *
 * 威胁缓解：T-12-00-01 — 所有 Job 通过 Queue::fake() 拦截；不调用真实 composer 子进程。
 */
beforeEach(function () {
    Queue::fake();

    // 环境自检通过：注入 EnvironmentChecker mock，与 ComposerInstallJobTest 保持同样的模式
    $checkerMock = Mockery::mock(EnvironmentChecker::class);
    $checkerMock->shouldReceive('check')->andReturn([
        'ok'            => true,
        'composer_path' => '/usr/local/bin/composer',
        'issues'        => [],
    ]);
    app()->instance(EnvironmentChecker::class, $checkerMock);

    // 创建超级管理员用户（Gate::before 超管放行，绕过具体权限点检查）
    $superAdminRole = config('filamentboot.super_admin_role', 'super_admin');
    $role           = Role::firstOrCreate(['name' => $superAdminRole, 'guard_name' => 'admin']);
    $admin          = AdminUser::factory()->create();
    $admin->assignRole($role);
    $this->actingAs($admin, 'admin');
});

it('installPlugin 从官方目录条目创建 Plugin 行并推送 ComposerInstallJob', function () {
    // 准备官方目录 entries（与 config/official-market.php 的 aliyun-sms 条目一致）
    $entries = [
        [
            'slug'         => 'aliyun-sms',
            'display_name' => '阿里云短信',
            'package_name' => 'filamentboot/aliyun-sms',
            'kind'         => 'plugin',
            'source'       => 'official_listed',
            'version'      => '0.1.0',
        ],
    ];

    // 通过 Livewire 测试调用 installPlugin
    Livewire::test(MarketplacePage::class)
        ->set('entries', $entries)
        ->call('installPlugin', 'filamentboot/aliyun-sms');

    // Plugin 行已创建，属性正确
    $plugin = Plugin::where('package_name', 'filamentboot/aliyun-sms')->first();
    expect($plugin)->not->toBeNull();
    expect($plugin->source)->toBe('official_listed');
    expect($plugin->installed_version)->toBe('0.1.0');

    // Job 已推送
    Queue::assertPushed(ComposerInstallJob::class, function ($job) use ($plugin) {
        return $job->pluginId === $plugin->id
            && $job->packageName === 'filamentboot/aliyun-sms';
    });
});

it('installCommunityPlugin 从社区条目创建 Plugin 行（source=community）并推送 Job', function () {
    $communityResults = [
        [
            'name'                 => 'vendor/community-pkg',
            'description'          => '社区测试插件',
            'url'                  => 'https://packagist.org/packages/vendor/community-pkg',
            'repository'           => 'https://github.com/vendor/community-pkg',
            'downloads'            => 1000,
            'favers'               => 50,
            'source'               => 'community',
            'filament_constraint'  => '^5.0',
            'compatibility_status' => 'unknown',
        ],
    ];

    Livewire::test(MarketplacePage::class)
        ->set('communityResults', $communityResults)
        ->call('installCommunityPlugin', 'vendor/community-pkg');

    // Plugin 行已创建，来源为 community
    $plugin = Plugin::where('package_name', 'vendor/community-pkg')->first();
    expect($plugin)->not->toBeNull();
    expect($plugin->source)->toBe('community');

    // Job 已推送
    Queue::assertPushed(ComposerInstallJob::class);
});

it('installPlugin 空包名是空操作：Plugin 表不变，无 Job 推送', function () {
    $countBefore = Plugin::count();

    Livewire::test(MarketplacePage::class)
        ->set('entries', [])
        ->call('installPlugin', '');

    expect(Plugin::count())->toBe($countBefore);
    Queue::assertNothingPushed();
});

it('installPlugin 同一包名重复调用是幂等的（firstOrCreate 不重复创建）', function () {
    $entries = [
        [
            'slug'         => 'aliyun-sms',
            'display_name' => '阿里云短信',
            'package_name' => 'filamentboot/aliyun-sms',
            'kind'         => 'plugin',
            'source'       => 'official_listed',
            'version'      => '0.1.0',
        ],
    ];

    $component = Livewire::test(MarketplacePage::class)
        ->set('entries', $entries);

    $component->call('installPlugin', 'filamentboot/aliyun-sms');
    $component->call('installPlugin', 'filamentboot/aliyun-sms');

    // 只有一个 Plugin 行
    expect(Plugin::where('package_name', 'filamentboot/aliyun-sms')->count())->toBe(1);
});
