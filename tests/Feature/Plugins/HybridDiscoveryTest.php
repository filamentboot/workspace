<?php

use Filamentboot\Models\Plugin;
use Illuminate\Support\Facades\File;

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
 */

/** @var string|null $originalInstalledJson */
$originalInstalledJson = null;

/** @var bool $originalExists */
$originalExists = false;

beforeEach(function () use (&$originalInstalledJson, &$originalExists) {
    $installedJsonPath = base_path('vendor/composer/installed.json');
    $originalExists    = file_exists($installedJsonPath);

    if ($originalExists) {
        $originalInstalledJson = File::get($installedJsonPath);
    }
});

afterEach(function () use (&$originalInstalledJson, &$originalExists) {
    $installedJsonPath = base_path('vendor/composer/installed.json');

    if ($originalExists && $originalInstalledJson !== null) {
        File::put($installedJsonPath, $originalInstalledJson);
    } elseif (! $originalExists && file_exists($installedJsonPath)) {
        File::delete($installedJsonPath);
    }
});

/**
 * 无 extra.filament-admin 但实现 Filament\Contracts\Plugin 的包 fixture
 *
 * @return string JSON 字符串
 */
function noExtraPluginFixture(): string
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

it('无 extra.filament-admin 但实现 Filament\Contracts\Plugin 的包可被混合发现（MKTPLACE-01）', function () {

    File::ensureDirectoryExists(base_path('vendor/composer'));
    File::put(base_path('vendor/composer/installed.json'), noExtraPluginFixture());

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

    /** @var Plugin|null $plugin */
    // plugin:scan 或 syncFromInstalled 应发现此包并写入 plugins 表
    $this->artisan('plugin:scan')->assertSuccessful();

    expect(Plugin::where('package_name', 'community/no-extra-plugin')->exists())->toBeTrue();

    // 清理 fixture 文件
    File::deleteDirectory(base_path('vendor/community'));
});

it('/tests/ 路径下的类不被混合发现误报为插件（MKTPLACE-01 Pitfall 4）', function () {

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
    expect(Plugin::where('package_name', 'community/test-only-plugin')->exists())->toBeFalse();

    // 清理 fixture 文件
    File::deleteDirectory(base_path('vendor/community'));
});

it('含 extra.filamentboot.plugin_class 的包优先使用声明值（MKTPLACE-01）', function () {

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
    expect($record)->not->toBeNull();
    expect($record->plugin_class)->toBe('First\\Declared\\DeclaredPlugin');
});
