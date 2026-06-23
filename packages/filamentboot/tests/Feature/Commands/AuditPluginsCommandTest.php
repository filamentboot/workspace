<?php

namespace Filamentboot\Tests\Feature\Commands;

use Filamentboot\FilamentbootServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * AuditPluginsCommand 行为测试
 *
 * 验证修复后的 extra.filamentboot 键读取与 PSR-4 感知文件解析（D-05 硬切）：
 * - Test 1: 声明完整 extra.filamentboot 契约的包通过所有检查，退出码 0（SUCCESS）
 * - Test 2: 缺少 extra.filamentboot.post_install 的包记录 FAIL，退出码 1（FAILURE）
 * - Test 3: 使用旧 extra.filament-admin 键的包 post_install 检查不通过（D-05 硬切，无回退）
 */
class AuditPluginsCommandTest extends TestCase
{
    /**
     * 临时测试根目录路径
     */
    protected string $tempBase = '';

    /**
     * 返回需要注册的包服务提供者
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FilamentbootServiceProvider::class];
    }

    /**
     * 返回临时测试目录作为应用根路径（隔离 base_path()）
     */
    protected function getApplicationBasePath(): string
    {
        return $this->tempBase;
    }

    /**
     * 每个测试前创建独立的临时目录（含 Laravel skeleton 所需子目录）
     */
    protected function setUp(): void
    {
        $this->tempBase = sys_get_temp_dir().'/filamentboot-audit-'.uniqid();

        $dirs = [
            'bootstrap/cache',
            'storage/app/public',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/logs',
            'config',
        ];

        foreach ($dirs as $dir) {
            mkdir($this->tempBase.'/'.$dir, 0755, true);
        }

        parent::setUp();
    }

    /**
     * 每个测试后清理临时目录
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tempBase !== '' && is_dir($this->tempBase)) {
            $this->deleteDirectoryNative($this->tempBase);
        }
    }

    /**
     * 原生 PHP 递归删除目录（不依赖 Facade，可在应用销毁后安全调用）
     */
    private function deleteDirectoryNative(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path)) {
                $this->deleteDirectoryNative($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * 创建符合 extra.filamentboot 契约的 fixture 包目录
     *
     * @param  string  $pkgName  包目录名（如 filamentboot-dummy）
     * @param  bool  $includePostInstall  是否在 extra.filamentboot 中声明 post_install
     * @param  bool  $useOldKey  是否改用旧键 extra.filament-admin（D-05 硬切测试）
     */
    private function createFixturePackage(
        string $pkgName,
        bool $includePostInstall = true,
        bool $useOldKey = false
    ): string {
        $pkgDir = $this->tempBase.'/packages/'.$pkgName;
        mkdir($pkgDir.'/src', 0755, true);

        // 生成 Plugin 类源文件（implements Plugin 接口特征）
        $pluginClass = 'Filamentboot\\'.str($pkgName)->studly()->value().'\\DummyPlugin';
        $namespace   = 'Filamentboot\\'.str($pkgName)->studly()->value();
        $srcFile     = $pkgDir.'/src/DummyPlugin.php';

        file_put_contents($srcFile, <<<PHP
            <?php

            namespace {$namespace};

            use Filament\Contracts\Plugin;
            use Filament\Panel;

            class DummyPlugin implements Plugin
            {
                public static function make(): static
                {
                    return app(static::class);
                }

                public function getId(): string
                {
                    return '{$pkgName}';
                }

                public function register(Panel \$panel): void {}

                public function boot(Panel \$panel): void {}
            }
            PHP);

        $extraKey = $useOldKey ? 'filament-admin' : 'filamentboot';

        $extraBlock = [
            'slug'         => $pkgName,
            'name'         => 'Dummy Plugin',
            'type'         => 'package',
            'source'       => 'official_listed',
            'plugin_class' => $pluginClass,
        ];

        if ($includePostInstall) {
            $extraBlock['post_install'] = [
                'publish_tags'   => [],
                'run_migrations' => false,
                'seeders'        => [],
            ];
        }

        $composerJson = [
            'name'     => 'filamentboot/'.$pkgName,
            'type'     => 'library',
            'keywords' => ['filament', 'filament-plugin'],
            'autoload' => [
                'psr-4' => [
                    $namespace.'\\' => 'src/',
                ],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [$namespace.'\\DummyServiceProvider'],
                ],
                $extraKey => $extraBlock,
            ],
        ];

        file_put_contents($pkgDir.'/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $pkgDir;
    }

    /**
     * Test 1: 声明完整 extra.filamentboot 契约（含 post_install）的包通过所有检查，退出码 SUCCESS
     *
     * 锁定 D-05 修复：extra.filamentboot 键被正确读取，
     * plugin_class 通过 PSR-4 映射解析到源文件，
     * Plugin 接口实现通过源码 grep 检测。
     */
    public function test_audit_passes_for_compliant_package_with_filamentboot_key(): void
    {
        $this->createFixturePackage('filamentboot-dummy', includePostInstall: true);

        $this->artisan('filamentboot:audit-plugins')
            ->assertExitCode(Command::SUCCESS);
    }

    /**
     * Test 2: 缺少 extra.filamentboot.post_install 的包记录 FAIL，退出码 FAILURE
     */
    public function test_audit_fails_for_package_missing_post_install(): void
    {
        $this->createFixturePackage('filamentboot-dummy', includePostInstall: false);

        $this->artisan('filamentboot:audit-plugins')
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * Test 3: D-05 硬切 — 使用旧 extra.filament-admin 键的包，post_install 检查不通过
     *
     * 确认修复后代码不向旧键回退（no backward-compat fallback）。
     */
    public function test_audit_fails_for_package_using_old_filament_admin_key(): void
    {
        // 包的 composer.json 声明旧键 extra.filament-admin（非 extra.filamentboot）
        $this->createFixturePackage('filamentboot-dummy', includePostInstall: true, useOldKey: true);

        // 因为代码只读 extra.filamentboot，旧键的 post_install 不可见 → 检查失败
        $this->artisan('filamentboot:audit-plugins')
            ->assertExitCode(Command::FAILURE);
    }
}
