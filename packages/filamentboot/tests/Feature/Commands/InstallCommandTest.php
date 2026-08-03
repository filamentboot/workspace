<?php

namespace Filamentboot\Tests\Feature\Commands;

use Filamentboot\Commands\InstallCommand;
use Filamentboot\FilamentbootServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * InstallCommand 行为测试
 *
 * 验证 `filamentboot:install` 命令的核心行为：
 * - Test 1: 全新安装退出码 0（SUCCESS）
 * - Test 2: 生成的 AdminPanelProvider 含 authGuard('admin') 与 FilamentbootPlugin::make()
 * - Test 3: Provider 已存在且拒绝覆盖时跳过（内容不变，命令仍 SUCCESS）
 * - Test 4: --force 时强制覆盖已存在文件
 * - Test 5: 品牌资源被复制到 public/
 * - Test 6: 品牌资源已存在时不被覆盖
 *
 * 测试通过使用测试专用子类跳过 migrate/seed 步骤（避免 Testbench 环境
 * 中迁移类名冲突），专注于 Provider 文件生成的核心行为验证。
 */
class InstallCommandTest extends TestCase
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
        $this->tempBase = sys_get_temp_dir().'/filament-admin-install-'.uniqid();

        // 创建 testbench 运行所需的完整 Laravel skeleton 目录结构
        $dirs = [
            'bootstrap/cache',
            'storage/app/public',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/logs',
            'app/Providers/Filament',
            'config',
            'database/migrations',
            'public',
            'resources/views',
            'lang',
            'tests',
        ];

        foreach ($dirs as $dir) {
            mkdir($this->tempBase.'/'.$dir, 0755, true);
        }

        // 创建 bootstrap/providers.php（Laravel 13 标准文件）
        file_put_contents(
            $this->tempBase.'/bootstrap/providers.php',
            "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n"
        );

        parent::setUp();

        // 注册测试专用命令（跳过 migrate/seed 步骤）
        $this->app->extend('Illuminate\Contracts\Console\Kernel', function ($kernel, $app) {
            return $kernel;
        });

        /** @var Kernel $artisan */
        $artisan = $this->app['Illuminate\Contracts\Console\Kernel'];
        $artisan->registerCommand(new TestableInstallCommand);
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
     *
     * @param  string  $dir  要删除的目录路径
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
     * Test 1: 全新 skeleton（无 AdminPanelProvider）执行 install，退出码为 0（SUCCESS）
     */
    public function test_install_command_exits_with_success_on_fresh_skeleton(): void
    {
        $this->artisan('filamentboot:install', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);
    }

    /**
     * Test 2: 执行后 AdminPanelProvider.php 文件存在，且含 authGuard('admin') 与 FilamentbootPlugin::make()
     */
    public function test_install_generates_provider_with_authguard_and_plugin(): void
    {
        $this->artisan('filamentboot:install', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $providerPath = $this->tempBase.'/app/Providers/Filament/AdminPanelProvider.php';

        $this->assertFileExists($providerPath, 'AdminPanelProvider.php 应已生成');

        $content = file_get_contents($providerPath);

        $this->assertStringContainsString(
            "authGuard('admin')",
            $content,
            '生成的 Provider 必须含 authGuard(\'admin\')'
        );

        $this->assertStringContainsString(
            'FilamentbootPlugin::make()',
            $content,
            '生成的 Provider 必须含 FilamentbootPlugin::make()'
        );
    }

    /**
     * Test 3: AdminPanelProvider 已存在且用户拒绝覆盖时，原文件内容不变，命令仍返回 SUCCESS
     */
    public function test_install_skips_provider_when_exists_and_user_answers_no(): void
    {
        // 预先写入一个带标记内容的 AdminPanelProvider
        $providerPath    = $this->tempBase.'/app/Providers/Filament/AdminPanelProvider.php';
        $originalContent = "<?php\n// original-marker-do-not-overwrite\n";
        file_put_contents($providerPath, $originalContent);

        $this->artisan('filamentboot:install')
            ->expectsConfirmation('AdminPanelProvider.php 已存在，是否覆盖？', 'no')
            ->assertExitCode(Command::SUCCESS);

        // 断言文件内容未被覆盖
        $this->assertSame(
            $originalContent,
            file_get_contents($providerPath),
            '用户拒绝覆盖时原文件内容不应被修改'
        );
    }

    /**
     * Test 4: 带 --force 时强制覆盖已存在文件，内容被替换为 stub 内容
     */
    public function test_install_force_overwrites_existing_provider(): void
    {
        // 预先写入一个带标记内容的 AdminPanelProvider
        $providerPath = $this->tempBase.'/app/Providers/Filament/AdminPanelProvider.php';
        file_put_contents($providerPath, "<?php\n// old-content-should-be-replaced\n");

        $this->artisan('filamentboot:install', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);

        // 断言文件内容已被覆盖（旧内容消失，新内容含 authGuard）
        $newContent = file_get_contents($providerPath);

        $this->assertStringNotContainsString(
            'old-content-should-be-replaced',
            $newContent,
            '--force 后旧内容应被覆盖'
        );

        $this->assertStringContainsString(
            "authGuard('admin')",
            $newContent,
            '--force 后新内容应含 authGuard(\'admin\')'
        );
    }

    /**
     * Test 5: 品牌资源（favicon 与两个 Logo）被复制到 public/
     */
    public function test_install_copies_brand_assets_to_public(): void
    {
        $this->artisan('filamentboot:install', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);

        foreach (['favicon.svg', 'brand-logo.svg', 'brand-logo-dark.svg'] as $asset) {
            $this->assertFileExists(
                $this->tempBase.'/public/'.$asset,
                "品牌资源 {$asset} 应被复制到 public/"
            );
        }
    }

    /**
     * Test 6: public/ 下已有同名文件时不被覆盖（保护下游自有品牌资源）
     */
    public function test_install_does_not_overwrite_existing_brand_assets(): void
    {
        $target = $this->tempBase.'/public/favicon.svg';
        file_put_contents($target, '<svg><!-- 下游自有品牌 --></svg>');

        $this->artisan('filamentboot:install', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertStringContainsString(
            '下游自有品牌',
            file_get_contents($target),
            '已存在的品牌资源不应被覆盖'
        );
    }
}

/**
 * 测试专用 InstallCommand 子类：跳过数据库相关步骤（migrate/seed）
 *
 * 这样可以在无数据库的 Testbench 环境中测试 Provider 生成行为，
 * 且不影响对 migrate 失败中断行为的契约（那是集成测试层面的保障）。
 */
class TestableInstallCommand extends InstallCommand
{
    /**
     * 覆盖 handle() 以跳过 migrate/seed，只测试 Provider 生成行为
     */
    public function handle(): int
    {
        $this->components->info('开始安装 Filamentboot（测试模式）...');

        // Step 1: 生成 AdminPanelProvider（核心测试目标）
        if (! $this->generateProvider()) {
            return self::FAILURE;
        }

        // Step 3-4: 跳过 vendor:publish（测试环境）

        // Step 5: 复制品牌资源（纯文件操作，无需数据库，保留以覆盖该行为）
        $this->publishBrandAssets();

        // Step 6: 跳过 migrate（避免 Testbench 迁移冲突）
        // Step 7: 跳过 db:seed
        // Step 8: 输出报告
        $this->newLine();
        $this->components->success('Filamentboot 安装完成（测试模式）！');

        return self::SUCCESS;
    }
}
