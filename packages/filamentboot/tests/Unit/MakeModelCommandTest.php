<?php

namespace FilamentAdmin\Tests\Unit;

use FilamentAdmin\FilamentAdminServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * MakeFilamentAdminModelCommand 行为测试
 *
 * 验证 `make:filament-admin-model` 命令的各项行为（FEAT-03）：
 * - 生成文件到 app/Models/ 并包含正确命名空间和类名
 * - 目标文件已存在时跳过（无 --force），输出含 Skipped:
 * - 带 --force 时覆盖已存在文件
 * - 非 PascalCase name 参数返回 FAILURE
 *
 * 每个测试在独立的临时目录中运行，互不污染。
 */
class MakeModelCommandTest extends TestCase
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
        $this->tempBase = sys_get_temp_dir().'/filament-admin-make-model-'.uniqid();

        // 创建 testbench 运行所需的完整 Laravel skeleton 目录结构
        $dirs = [
            'bootstrap/cache',
            'storage/app/public',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/logs',
            'app',
            'config',
            'database',
            'resources/views',
            'tests',
        ];

        foreach ($dirs as $dir) {
            mkdir($this->tempBase.'/'.$dir, 0755, true);
        }

        parent::setUp();
    }

    /**
     * 每个测试后清理临时目录（使用原生 PHP，避免 Facade 在应用销毁后不可用）
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
     * 验证 make:filament-admin-model Product 生成 app/Models/Product.php
     * 且内容包含正确命名空间 App\Models 与类名 Product
     */
    public function test_model_generates_file_with_correct_namespace(): void
    {
        $this->artisan('make:filament-admin-model', ['name' => 'Product'])
            ->assertExitCode(Command::SUCCESS);

        $expectedPath = $this->tempBase.'/app/Models/Product.php';
        self::assertTrue(File::exists($expectedPath), '期望生成 app/Models/Product.php，但文件不存在');

        $content = File::get($expectedPath);
        self::assertStringContainsString('namespace App\\Models;', $content, '期望命名空间为 App\\Models');
        self::assertStringContainsString('class Product extends Model', $content, '期望类名为 Product');
    }

    /**
     * 验证目标文件已存在时（不带 --force）输出含 Skipped: 且文件内容未被修改
     */
    public function test_model_skips_existing_without_force(): void
    {
        // 预置占位文件
        $modelDir  = $this->tempBase.'/app/Models';
        $modelPath = $modelDir.'/Product.php';
        mkdir($modelDir, 0755, true);
        file_put_contents($modelPath, '<?php // placeholder content');

        // 命令应跳过并输出 Skipped: 提示
        $this->artisan('make:filament-admin-model', ['name' => 'Product'])
            ->expectsOutputToContain('Skipped:')
            ->assertExitCode(Command::SUCCESS);

        // 文件内容未被替换
        $content = File::get($modelPath);
        self::assertStringContainsString('placeholder content', $content, '文件内容应保持为占位内容');
        self::assertStringNotContainsString('class Product extends Model', $content, '文件内容不应被覆盖');
    }

    /**
     * 验证带 --force 标志时覆盖已存在的目标文件
     */
    public function test_model_overwrites_existing_with_force(): void
    {
        // 预置占位文件
        $modelDir  = $this->tempBase.'/app/Models';
        $modelPath = $modelDir.'/Product.php';
        mkdir($modelDir, 0755, true);
        file_put_contents($modelPath, '<?php // placeholder content');

        // 带 --force 强制覆盖
        $this->artisan('make:filament-admin-model', [
            'name'    => 'Product',
            '--force' => true,
        ])->assertExitCode(Command::SUCCESS);

        // 文件内容已被替换为真实 stub 渲染结果
        $content = File::get($modelPath);
        self::assertStringContainsString('class Product extends Model', $content, '文件内容应已被覆盖');
        self::assertStringNotContainsString('placeholder content', $content, '占位内容应被替换');
    }

    /**
     * 验证非 PascalCase 的 name 参数（小写开头）返回 FAILURE
     */
    public function test_model_rejects_invalid_name(): void
    {
        $this->artisan('make:filament-admin-model', ['name' => 'product'])
            ->assertExitCode(Command::FAILURE);
    }
}
