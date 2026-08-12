<?php

namespace Filamentboot\Tests\Feature\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * 混合发现机制测试（MKTPLACE-01）
 *
 * 覆盖场景：
 * 1. 无 extra.filament-admin 声明的包，只要有类实现 Filament\Contracts\Plugin，
 *    plugin:scan / syncFromInstalled 仍能发现（混合发现策略，D-12-07）
 * 2. /tests/ 路径下的类不被误发现（RESEARCH Pitfall 4 防护）
 * 3. 含 extra.filament-admin.plugin_class 的包优先使用声明值（不走 classmap grep）
 *
 * 威胁缓解：T-12-00-01 — 通过 File::put 写 fixture，无真实网络或 composer 调用。
 *
 * 数据库连接直接沿用根 phpunit.xml 注入的 MySQL 测试库环境变量
 * （本机无 pdo_sqlite 扩展），迁移由 FilamentbootServiceProvider::boot()
 * 的 loadMigrationsFrom 自动注册，无需在测试里重复声明。
 *
 * Testbench 默认 skeleton 没有真实 vendor 目录（未走 workbench 符号链接），
 * Laravel 包自动发现在此环境下失效，因此必须显式注册 Permission /
 * Activitylog / LaravelSettings 三个 ServiceProvider（本包迁移依赖它们的配置）。
 */
class HybridDiscoveryTest extends TestCase
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
            File::put($installedJsonPath, $this->originalInstalledJson);
        } elseif (! $this->originalExists && file_exists($installedJsonPath)) {
            File::delete($installedJsonPath);
        }

        parent::tearDown();
    }

    /**
     * 无 extra.filament-admin 但实现 Filament\Contracts\Plugin 的包 fixture
     */
    private function noExtraPluginFixture(): string
    {
        return json_encode([
            'packages' => [
                [
                    'name'    => 'community/no-extra-plugin',
                    'version' => '1.0.0',
                    // 无 extra.filament-admin 块
                    'autoload' => [
                        'psr-4' => [
                            'Community\\NoExtra\\' => 'src/',
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 无 extra.filament-admin 但实现 Filament\Contracts\Plugin 的包可被混合发现（MKTPLACE-01）
     */
    public function test_package_without_extra_declaration_is_discovered_via_interface(): void
    {
        File::ensureDirectoryExists(base_path('vendor/composer'));
        File::put(base_path('vendor/composer/installed.json'), $this->noExtraPluginFixture());

        // 为 fixture 包创建一个实现 Filament\Contracts\Plugin 的源文件
        // Wave 1 的 detectPluginClass 会通过 classmap grep 读取此文件
        $fakeClassFile = base_path('vendor/community/no-extra-plugin/src/NoExtraPlugin.php');
        File::ensureDirectoryExists(dirname($fakeClassFile));
        File::put($fakeClassFile, <<<'PHP'
<?php
namespace Community\NoExtra;
use Filament\Contracts\Plugin;
use Filament\Panel;
class NoExtraPlugin implements Plugin
{
    public static function make(): static { return app(static::class); }
    public function getId(): string { return 'community-no-extra'; }
    public function register(Panel $panel): void {}
    public function boot(Panel $panel): void {}
}
PHP);

        // plugin:scan 或 syncFromInstalled 应发现此包并写入 plugins 表
        $this->artisan('plugin:scan')->assertSuccessful();

        $this->assertTrue(Plugin::where('package_name', 'community/no-extra-plugin')->exists());

        // 清理 fixture 文件
        File::deleteDirectory(base_path('vendor/community'));
    }

    /**
     * /tests/ 路径下的类不被混合发现误报为插件（MKTPLACE-01 Pitfall 4）
     */
    public function test_classes_under_tests_directory_are_not_falsely_discovered(): void
    {
        File::ensureDirectoryExists(base_path('vendor/composer'));

        // 构造 installed.json：包 autoload classmap 指向一个 tests/ 路径下的文件
        $fixture = json_encode([
            'packages' => [
                [
                    'name'     => 'community/test-only-plugin',
                    'version'  => '1.0.0',
                    'autoload' => [
                        'classmap' => ['tests/'],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        File::put(base_path('vendor/composer/installed.json'), $fixture);

        // 在 tests/ 路径下创建一个带 implements Plugin 的测试文件（应被过滤）
        $fakeTestFile = base_path('vendor/community/test-only-plugin/tests/FakePluginTest.php');
        File::ensureDirectoryExists(dirname($fakeTestFile));
        File::put($fakeTestFile, <<<'PHP'
<?php
namespace Community\TestOnly\Tests;
use Filament\Contracts\Plugin;
use Filament\Panel;
// 此文件在 /tests/ 路径，不应被 classmap grep 发现
class FakePluginTest implements Plugin
{
    public static function make(): static { return app(static::class); }
    public function getId(): string { return 'test-only'; }
    public function register(Panel $panel): void {}
    public function boot(Panel $panel): void {}
}
PHP);

        $this->artisan('plugin:scan')->assertSuccessful();

        // /tests/ 路径下的类不应被写入 plugins 表
        $this->assertFalse(Plugin::where('package_name', 'community/test-only-plugin')->exists());

        // 清理 fixture 文件
        File::deleteDirectory(base_path('vendor/community'));
    }

    /**
     * 含 extra.filamentboot.plugin_class 的包优先使用声明值（MKTPLACE-01）
     */
    public function test_package_with_declared_plugin_class_takes_priority(): void
    {
        File::ensureDirectoryExists(base_path('vendor/composer'));

        $fixture = json_encode([
            'packages' => [
                [
                    'name'    => 'first/declared-plugin',
                    'version' => '1.0.0',
                    'extra'   => [
                        'filamentboot' => [
                            'slug'         => 'declared-plugin',
                            'name'         => 'Declared Plugin',
                            'plugin_class' => 'First\\Declared\\DeclaredPlugin',
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        File::put(base_path('vendor/composer/installed.json'), $fixture);
        $this->artisan('plugin:scan')->assertSuccessful();

        $record = Plugin::where('package_name', 'first/declared-plugin')->first();
        $this->assertNotNull($record);
        $this->assertSame('First\\Declared\\DeclaredPlugin', $record->plugin_class);
    }
}
