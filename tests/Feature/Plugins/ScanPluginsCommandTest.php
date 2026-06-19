<?php

use FilamentAdmin\Models\Plugin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * ScanPlugins 命令集成测试（PLUGIN-01 / CR-01 修复验证）
 *
 * 覆盖场景：
 * 1. 含 extra.filament-admin 的包 → plugin:scan 成功退出，Plugin 记录被创建，installed_at 非空
 * 2. 重复执行 plugin:scan（幂等）→ 记录数量不变，installed_at 与首次值完全一致
 * 3. plugin:scan 执行后 Cache::get('plugins.enabled_list') 返回 null（缓存被清除）
 * 4. installed.json 含两个包（仅一个声明 extra.filament-admin）→ 只创建 1 条 Plugin 记录
 *
 * 测试策略：通过 File::put() 将 fixture 写入 base_path('vendor/composer/installed.json')，
 * ScanPlugins::handle() 直接调用 file_get_contents()，无法通过 Storage fake 拦截。
 * beforeEach 记录原始内容，afterEach 恢复，避免污染其他测试。
 */

/** @var string|null $originalInstalledJson 原始 installed.json 内容 */
$originalInstalledJson = null;

/** @var bool $originalExists 原始文件是否存在 */
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
        // 恢复原始内容
        File::put($installedJsonPath, $originalInstalledJson);
    } elseif (! $originalExists && file_exists($installedJsonPath)) {
        // 原本不存在，删除临时写入的文件
        File::delete($installedJsonPath);
    }
});

/**
 * 含 extra.filament-admin 的 fixture（单包）
 *
 * @return string JSON 字符串
 */
function singlePluginFixture(): string
{
    return json_encode([
        'packages' => [
            [
                'name'    => 'test/fake-fa-plugin',
                'version' => '1.0.0',
                'extra'   => [
                    'filament-admin' => [
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
 * 含两个包的 fixture：一个声明 extra.filament-admin，一个不声明
 *
 * @return string JSON 字符串
 */
function twoPackagesFixture(): string
{
    return json_encode([
        'packages' => [
            [
                'name'    => 'test/fake-fa-plugin',
                'version' => '1.0.0',
                'extra'   => [
                    'filament-admin' => [
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
                // 无 extra.filament-admin 声明
                'extra'   => [
                    'laravel' => [
                        'providers' => ['Test\\PlainServiceProvider'],
                    ],
                ],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

it('plugin:scan 遇到含 extra.filament-admin 包时以 SUCCESS 退出且 Plugin 记录被创建', function () {
    // 确保 vendor/composer 目录存在
    File::ensureDirectoryExists(base_path('vendor/composer'));
    File::put(base_path('vendor/composer/installed.json'), singlePluginFixture());

    $this->artisan('plugin:scan')->assertSuccessful();

    expect(Plugin::where('package_name', 'test/fake-fa-plugin')->exists())->toBeTrue();

    $plugin = Plugin::where('package_name', 'test/fake-fa-plugin')->first();
    expect($plugin)->not->toBeNull();
    expect($plugin->installed_at)->not->toBeNull();
});

it('重复执行 plugin:scan 后 installed_at 保持首次值（幂等）', function () {
    File::ensureDirectoryExists(base_path('vendor/composer'));
    File::put(base_path('vendor/composer/installed.json'), singlePluginFixture());

    // 第一次执行
    $this->artisan('plugin:scan')->assertSuccessful();

    /** @var \FilamentAdmin\Models\Plugin $firstRecord */
    $firstRecord = Plugin::where('package_name', 'test/fake-fa-plugin')->first();
    $firstAt     = $firstRecord->installed_at;

    // 第二次执行
    $this->artisan('plugin:scan')->assertSuccessful();

    // 记录数量不变（无重复创建）
    expect(Plugin::where('package_name', 'test/fake-fa-plugin')->count())->toBe(1);

    /** @var \FilamentAdmin\Models\Plugin $secondRecord */
    $secondRecord = Plugin::where('package_name', 'test/fake-fa-plugin')->first();
    $secondAt     = $secondRecord->fresh()->installed_at;

    // installed_at 幂等：两次值完全相同（Carbon equalTo 精确比较时间戳）
    expect($firstAt->equalTo($secondAt))->toBeTrue();
});

it('plugin:scan 执行后 plugins.enabled_list 缓存被清除', function () {
    File::ensureDirectoryExists(base_path('vendor/composer'));
    File::put(base_path('vendor/composer/installed.json'), singlePluginFixture());

    // 先写入旧缓存值
    Cache::put('plugins.enabled_list', ['Foo\\BarPlugin'], 60);
    expect(Cache::get('plugins.enabled_list'))->not->toBeNull();

    // 执行 plugin:scan
    $this->artisan('plugin:scan')->assertSuccessful();

    // 缓存应被清除
    expect(Cache::get('plugins.enabled_list'))->toBeNull();
});

it('installed.json 含两个包（仅一个声明 extra.filament-admin）时只创建 1 条 Plugin 记录', function () {
    File::ensureDirectoryExists(base_path('vendor/composer'));
    File::put(base_path('vendor/composer/installed.json'), twoPackagesFixture());

    $this->artisan('plugin:scan')->assertSuccessful();

    // 只有声明了 extra.filament-admin 的包被写入
    expect(Plugin::count())->toBe(1);
    expect(Plugin::where('package_name', 'test/fake-fa-plugin')->exists())->toBeTrue();
    expect(Plugin::where('package_name', 'test/plain-package')->exists())->toBeFalse();
});
