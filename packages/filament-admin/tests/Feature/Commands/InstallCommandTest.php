<?php

namespace FilamentAdmin\Tests\Feature\Commands;

use FilamentAdmin\FilamentAdminServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * InstallCommand 行为测试
 *
 * 验证 `filament-admin:install` 命令的核心行为：
 * - Test 1: 全新安装退出码 0（SUCCESS）
 * - Test 2: 生成的 AdminPanelProvider 含 authGuard('admin') 与 FilamentAdminPlugin::make()
 * - Test 3: Provider 已存在且拒绝覆盖时跳过（内容不变，命令仍 SUCCESS）
 * - Test 4: --force 时强制覆盖已存在文件
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
        return [FilamentAdminServiceProvider::class];
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
     *
     * 注意：命令内部调用 migrate/db:seed，测试环境中不实际连接数据库，
     * migrate 步骤使用 Testbench 内存数据库，避免真实 DB 连接失败导致 FAILURE。
     */
    public function test_install_command_exits_with_success_on_fresh_skeleton(): void
    {
        $this->artisan('filament-admin:install', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);
    }

    /**
     * Test 2: 执行后 AdminPanelProvider.php 文件存在，且含 authGuard('admin') 与 FilamentAdminPlugin::make()
     */
    public function test_install_generates_provider_with_authguard_and_plugin(): void
    {
        $this->artisan('filament-admin:install', ['--force' => true])
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
            'FilamentAdminPlugin::make()',
            $content,
            '生成的 Provider 必须含 FilamentAdminPlugin::make()'
        );
    }

    /**
     * Test 3: AdminPanelProvider 已存在且用户拒绝覆盖时，原文件内容不变，命令仍返回 SUCCESS
     */
    public function test_install_skips_provider_when_exists_and_user_answers_no(): void
    {
        // 预先写入一个带标记内容的 AdminPanelProvider
        $providerPath = $this->tempBase.'/app/Providers/Filament/AdminPanelProvider.php';
        $originalContent = "<?php\n// original-marker-do-not-overwrite\n";
        file_put_contents($providerPath, $originalContent);

        $this->artisan('filament-admin:install')
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

        $this->artisan('filament-admin:install', ['--force' => true])
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
}
