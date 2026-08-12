<?php

namespace Filamentboot\Tests\Feature\Plugins;

use AlizHarb\ActivityLog\ActivityLogServiceProvider as FilamentActivityLogServiceProvider;
use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\QueryBuilder\QueryBuilderServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Filamentboot\Filament\Pages\Marketplace\MarketplacePage;
use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Jobs\ComposerInstallJob;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\EnvironmentChecker;
use Filamentboot\Tests\Support\StabilizeLivewireDataStoreProvider;
use Filamentboot\Tests\Support\TestAdminPanelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Mockery;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationServiceProvider;

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
 *
 * 包内没有真实宿主 AdminPanelProvider，Livewire::test(MarketplacePage::class)
 * 需要一个真实注册且"当前"的 Filament Panel（安装授权走 $this->authorize()，
 * 页面视图渲染走 Filament 面板上下文），借助 TestAdminPanelProvider 注册最小
 * 'admin' 面板 + Filament::setCurrentPanel() 显式置为当前面板。
 */
class MarketplaceInstallTest extends TestCase
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
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            FilamentShieldServiceProvider::class,
            TwoFactorAuthenticationServiceProvider::class,
            FilamentActivityLogServiceProvider::class,
            TestAdminPanelProvider::class,
            // 必须排在 LivewireServiceProvider 之后：修复 Testbench 环境下
            // Livewire::test() 渲染完整 Filament Page 时 DataStore 单例丢失
            // 导致 getErrorBag() 返回 null 的问题，详见该 Provider 的类注释。
            StabilizeLivewireDataStoreProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // 注册 admin guard（与生产配置一致），使 Auth::guard('admin') 可用
        $app['config']->set('auth.guards.admin', [
            'driver'   => 'session',
            'provider' => 'admin_users',
        ]);
        $app['config']->set('auth.providers.admin_users', [
            'driver' => 'eloquent',
            'model'  => AdminUser::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // 环境自检通过：注入 EnvironmentChecker mock，与 ComposerInstallJobTest 保持同样的模式
        $checkerMock = Mockery::mock(EnvironmentChecker::class);
        $checkerMock->shouldReceive('check')->andReturn([
            'ok'            => true,
            'composer_path' => '/usr/local/bin/composer',
            'issues'        => [],
        ]);
        $this->app->instance(EnvironmentChecker::class, $checkerMock);

        // 创建超级管理员用户（Gate::before 超管放行，绕过具体权限点检查）
        $superAdminRole = config('filamentboot.super_admin_role', 'super_admin');
        $role           = Role::firstOrCreate(['name' => $superAdminRole, 'guard_name' => 'admin']);
        $admin          = AdminUser::factory()->create();
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        // Livewire::test(Page::class) 不经过真实 HTTP 路由，Filament 页面
        // 渲染/授权依赖"当前面板"上下文，需显式置为 TestAdminPanelProvider
        // 注册的 'admin' 面板。
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * installPlugin 从官方目录条目创建 Plugin 行并推送 ComposerInstallJob
     */
    public function test_install_plugin_creates_plugin_row_from_official_entry_and_dispatches_job(): void
    {
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
        $this->assertNotNull($plugin);
        $this->assertSame('official_listed', $plugin->source);
        $this->assertSame('0.1.0', $plugin->installed_version);

        // Job 已推送
        Queue::assertPushed(ComposerInstallJob::class, function ($job) use ($plugin) {
            return $job->pluginId === $plugin->id
                && $job->packageName === 'filamentboot/aliyun-sms';
        });
    }

    /**
     * installCommunityPlugin 从社区条目创建 Plugin 行（source=community）并推送 Job
     */
    public function test_install_community_plugin_creates_plugin_row_with_community_source(): void
    {
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
        $this->assertNotNull($plugin);
        $this->assertSame('community', $plugin->source);

        // Job 已推送
        Queue::assertPushed(ComposerInstallJob::class);
    }

    /**
     * installPlugin 空包名是空操作：Plugin 表不变，无 Job 推送
     */
    public function test_install_plugin_with_empty_package_name_is_noop(): void
    {
        $countBefore = Plugin::count();

        Livewire::test(MarketplacePage::class)
            ->set('entries', [])
            ->call('installPlugin', '');

        $this->assertSame($countBefore, Plugin::count());
        Queue::assertNothingPushed();
    }

    /**
     * installPlugin 同一包名重复调用是幂等的（firstOrCreate 不重复创建）
     */
    public function test_install_plugin_is_idempotent_for_repeated_calls(): void
    {
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
        $this->assertSame(1, Plugin::where('package_name', 'filamentboot/aliyun-sms')->count());
    }
}
