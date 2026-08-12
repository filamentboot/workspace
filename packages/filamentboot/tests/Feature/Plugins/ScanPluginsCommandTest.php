<?php

namespace Filamentboot\Tests\Feature\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * ScanPlugins 命令集成测试（PLUGIN-01 / CR-01 修复验证）
 *
 * 覆盖场景：
 * 1. 含 extra.filamentboot 的包 → plugin:scan 成功退出，Plugin 记录被创建，installed_at 非空
 * 2. 重复执行 plugin:scan（幂等）→ 记录数量不变，installed_at 与首次值完全一致
 * 3. plugin:scan 执行后 Cache::get('plugins.enabled_list') 返回 null（缓存被清除）
 * 4. installed.json 含两个包（仅一个声明 extra.filamentboot）→ 只创建 1 条 Plugin 记录
 *
 * 测试策略：通过 File::put() 将 fixture 写入 base_path('vendor/composer/installed.json')，
 * ScanPlugins::handle() 直接调用 file_get_contents()，无法通过 Storage fake 拦截。
 * setUp 记录原始内容，tearDown 恢复，避免污染其他测试。
 *
 * 数据库连接直接沿用根 phpunit.xml 注入的 MySQL 测试库环境变量
 * （本机无 pdo_sqlite 扩展），迁移由 FilamentbootServiceProvider::boot()
 * 的 loadMigrationsFrom 自动注册，无需在测试里重复声明。
 *
 * Testbench 默认 skeleton 没有真实 vendor 目录（未走 workbench 符号链接），
 * Laravel 包自动发现在此环境下失效，因此必须显式注册 Permission /
 * Activitylog / LaravelSettings 三个 ServiceProvider（本包迁移依赖它们的配置）。
 */
class ScanPluginsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** 原始 installed.json 内容 */
    private ?string $originalInstalledJson = null;

    /** 原始文件是否存在 */
    private bool $originalExists = false;

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
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $installedJsonPath    = base_path('vendor/composer/installed.json');
        $this->originalExists = file_exists($installedJsonPath);

        if ($this->originalExists) {
            $this->originalInstalledJson = File::get($installedJsonPath);
        }
    }

    protected function tearDown(): void
    {
        $installedJsonPath = base_path('vendor/composer/installed.json');

        if ($this->originalExists && $this->originalInstalledJson !== null) {
            // 恢复原始内容
            File::put($installedJsonPath, $this->originalInstalledJson);
        } elseif (! $this->originalExists && file_exists($installedJsonPath)) {
            // 原本不存在，删除临时写入的文件
            File::delete($installedJsonPath);
        }

        parent::tearDown();
    }

    /**
     * 含 extra.filamentboot 的 fixture（单包）
     */
    private function singlePluginFixture(): string
    {
        return json_encode([
            'packages' => [
                [
                    'name'    => 'test/fake-fa-plugin',
                    'version' => '1.0.0',
                    'extra'   => [
                        'filamentboot' => [
                            'slug'        => 'fake-fa-plugin',
                            'name'        => 'Fake FA Plugin',
                            'type'        => 'package',
                            'description' => '测试用虚拟插件',
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 含两个包的 fixture：一个声明 extra.filamentboot，一个不声明
     */
    private function twoPackagesFixture(): string
    {
        return json_encode([
            'packages' => [
                [
                    'name'    => 'test/fake-fa-plugin',
                    'version' => '1.0.0',
                    'extra'   => [
                        'filamentboot' => [
                            'slug'        => 'fake-fa-plugin',
                            'name'        => 'Fake FA Plugin',
                            'type'        => 'package',
                            'description' => '测试用虚拟插件',
                        ],
                    ],
                ],
                [
                    'name'    => 'test/plain-package',
                    'version' => '2.0.0',
                    // 无 extra.filamentboot 声明
                    'extra' => [
                        'laravel' => [
                            'providers' => ['Test\\PlainServiceProvider'],
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * plugin:scan 遇到含 extra.filamentboot 包时以 SUCCESS 退出且 Plugin 记录被创建
     */
    public function test_scan_succeeds_and_creates_plugin_record_for_declared_package(): void
    {
        // 确保 vendor/composer 目录存在
        File::ensureDirectoryExists(base_path('vendor/composer'));
        File::put(base_path('vendor/composer/installed.json'), $this->singlePluginFixture());

        $this->artisan('plugin:scan')->assertSuccessful();

        $this->assertTrue(Plugin::where('package_name', 'test/fake-fa-plugin')->exists());

        $plugin = Plugin::where('package_name', 'test/fake-fa-plugin')->first();
        $this->assertNotNull($plugin);
        $this->assertNotNull($plugin->installed_at);
    }

    /**
     * 重复执行 plugin:scan 后 installed_at 保持首次值（幂等）
     */
    public function test_repeated_scan_keeps_installed_at_stable(): void
    {
        File::ensureDirectoryExists(base_path('vendor/composer'));
        File::put(base_path('vendor/composer/installed.json'), $this->singlePluginFixture());

        // 第一次执行
        $this->artisan('plugin:scan')->assertSuccessful();

        /** @var Plugin $firstRecord */
        $firstRecord = Plugin::where('package_name', 'test/fake-fa-plugin')->first();
        $firstAt     = $firstRecord->installed_at;

        // 第二次执行
        $this->artisan('plugin:scan')->assertSuccessful();

        // 记录数量不变（无重复创建）
        $this->assertSame(1, Plugin::where('package_name', 'test/fake-fa-plugin')->count());

        /** @var Plugin $secondRecord */
        $secondRecord = Plugin::where('package_name', 'test/fake-fa-plugin')->first();
        $secondAt     = $secondRecord->fresh()->installed_at;

        // installed_at 幂等：两次值完全相同（Carbon equalTo 精确比较时间戳）
        $this->assertTrue($firstAt->equalTo($secondAt));
    }

    /**
     * plugin:scan 执行后 plugins.enabled_list 缓存被清除
     */
    public function test_scan_clears_enabled_list_cache(): void
    {
        File::ensureDirectoryExists(base_path('vendor/composer'));
        File::put(base_path('vendor/composer/installed.json'), $this->singlePluginFixture());

        // 先写入旧缓存值
        Cache::put('plugins.enabled_list', ['Foo\\BarPlugin'], 60);
        $this->assertNotNull(Cache::get('plugins.enabled_list'));

        // 执行 plugin:scan
        $this->artisan('plugin:scan')->assertSuccessful();

        // 缓存应被清除
        $this->assertNull(Cache::get('plugins.enabled_list'));
    }

    /**
     * installed.json 含两个包（仅一个声明 extra.filamentboot）时只创建 1 条 Plugin 记录
     */
    public function test_scan_only_creates_record_for_declared_package_among_two(): void
    {
        File::ensureDirectoryExists(base_path('vendor/composer'));
        File::put(base_path('vendor/composer/installed.json'), $this->twoPackagesFixture());

        $this->artisan('plugin:scan')->assertSuccessful();

        // 只有声明了 extra.filamentboot 的包被写入
        $this->assertSame(1, Plugin::count());
        $this->assertTrue(Plugin::where('package_name', 'test/fake-fa-plugin')->exists());
        $this->assertFalse(Plugin::where('package_name', 'test/plain-package')->exists());
    }
}
