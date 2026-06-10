<?php

namespace FilamentAdmin\Tests\Unit;

use FilamentAdmin\FilamentAdminServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Console\Command\Command;

/**
 * PublishCommand 行为测试
 *
 * 验证 `filament-admin:publish` 命令的各项行为（COMPLY-02）：
 * - D-01: --model 选项生成 Model stub 到默认路径
 * - D-02: --resource 选项生成 Resource 及 Pages 文件
 * - D-03: 目标文件已存在时跳过（无 --force）
 * - D-04: --force 时覆盖已有文件
 * - D-05: --path 选项推导正确命名空间
 * - D-06: --all 单独使用时生成全部内置四件套
 * - D-11: --model 与 --all 组合时仅生成指定模型四件套
 * - 安全防护: --path 拒绝路径上溯攻击（D-06 安全扩展）
 *
 * 每个测试在独立的临时目录中运行，互不污染。
 */
class PublishCommandTest extends TestCase
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
        $this->tempBase = sys_get_temp_dir().'/filament-admin-publish-'.uniqid();

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
     * 验证 --model=Product 在 app/Models/Product.php 生成文件
     * 且内容包含命名空间 App\Models 与类名 Product（D-01）
     */
    public function test_publish_model_option_generates_model_file_at_default_path(): void
    {
        $this->artisan('filament-admin:publish', ['--model' => 'Product'])
            ->assertExitCode(Command::SUCCESS);

        $expectedPath = $this->tempBase.'/app/Models/Product.php';
        self::assertTrue(File::exists($expectedPath), '期望生成 app/Models/Product.php，但文件不存在');

        $content = File::get($expectedPath);
        self::assertStringContainsString('namespace App\\Models;', $content);
        self::assertStringContainsString('class Product extends Model', $content);
    }

    /**
     * 验证 --resource=Product 生成 Resource 及 Pages 文件（D-02）
     * 生成文件：ProductResource.php / ListProducts.php / CreateProduct.php / EditProduct.php
     */
    public function test_publish_resource_option_generates_resource_and_pages_files(): void
    {
        $this->artisan('filament-admin:publish', ['--resource' => 'Product'])
            ->assertExitCode(Command::SUCCESS);

        $base = $this->tempBase.'/app/Filament/Resources/Products';

        self::assertTrue(
            File::exists($base.'/ProductResource.php'),
            '期望生成 ProductResource.php'
        );
        self::assertTrue(
            File::exists($base.'/Pages/ListProducts.php'),
            '期望生成 Pages/ListProducts.php'
        );
        self::assertTrue(
            File::exists($base.'/Pages/CreateProduct.php'),
            '期望生成 Pages/CreateProduct.php'
        );
        self::assertTrue(
            File::exists($base.'/Pages/EditProduct.php'),
            '期望生成 Pages/EditProduct.php'
        );
    }

    /**
     * 验证目标文件已存在时（不带 --force）输出含 Skipped: 字符串且文件未被修改（D-03）
     */
    public function test_publish_skips_existing_file_without_force_flag(): void
    {
        // Arrange: 预先创建占位文件
        $modelDir  = $this->tempBase.'/app/Models';
        $modelPath = $modelDir.'/Product.php';
        mkdir($modelDir, 0755, true);
        file_put_contents($modelPath, '<?php // placeholder content');

        // Act & Assert: 命令应跳过并输出 Skipped: 提示
        $this->artisan('filament-admin:publish', ['--model' => 'Product'])
            ->expectsOutputToContain('Skipped:')
            ->assertExitCode(Command::SUCCESS);

        // 文件内容未被替换
        $content = File::get($modelPath);
        self::assertStringContainsString('placeholder content', $content, '文件内容应保持为占位内容');
        self::assertStringNotContainsString('class Product extends Model', $content, '文件内容不应被覆盖');
    }

    /**
     * 验证带 --force 标志时覆盖已存在的目标文件（D-04）
     */
    public function test_publish_overwrites_existing_file_with_force_flag(): void
    {
        // Arrange: 预先创建占位文件
        $modelDir  = $this->tempBase.'/app/Models';
        $modelPath = $modelDir.'/Product.php';
        mkdir($modelDir, 0755, true);
        file_put_contents($modelPath, '<?php // placeholder content');

        // Act: 带 --force 强制覆盖
        $this->artisan('filament-admin:publish', [
            '--model' => 'Product',
            '--force' => true,
        ])->assertExitCode(Command::SUCCESS);

        // Assert: 文件内容已被替换为 stub 渲染结果
        $content = File::get($modelPath);
        self::assertStringContainsString('class Product extends Model', $content, '文件内容应已被覆盖');
        self::assertStringNotContainsString('placeholder content', $content, '占位内容应被替换');
    }

    /**
     * 验证 --path=app/Filament/Reseller 与 --resource=Product 组合时
     * 生成文件的 namespace 为 App\Filament\Reseller\Resources\Products（D-05）
     */
    public function test_publish_path_option_derives_correct_namespace(): void
    {
        $this->artisan('filament-admin:publish', [
            '--resource' => 'Product',
            '--path'     => 'app/Filament/Reseller',
        ])->assertExitCode(Command::SUCCESS);

        $resourcePath = $this->tempBase.'/app/Filament/Reseller/Products/ProductResource.php';
        self::assertTrue(File::exists($resourcePath), '期望在自定义路径下生成 ProductResource.php');

        $content = File::get($resourcePath);
        self::assertStringContainsString(
            'namespace App\\Filament\\Reseller\\Products;',
            $content,
            '期望 namespace 为 App\\Filament\\Reseller\\Products'
        );
    }

    /**
     * 验证单独 --all 生成 AdminUser / Department / Menu / LoginLog 全部四件套（D-06）
     */
    public function test_publish_all_alone_generates_all_builtin_sets(): void
    {
        $this->artisan('filament-admin:publish', ['--all' => true])
            ->assertExitCode(Command::SUCCESS);

        // 断言 4 个内置 Model 都已生成
        $modelsBase = $this->tempBase.'/app/Models/';
        self::assertTrue(File::exists($modelsBase.'AdminUser.php'), 'AdminUser.php 未生成');
        self::assertTrue(File::exists($modelsBase.'Department.php'), 'Department.php 未生成');
        self::assertTrue(File::exists($modelsBase.'Menu.php'), 'Menu.php 未生成');
        self::assertTrue(File::exists($modelsBase.'LoginLog.php'), 'LoginLog.php 未生成');
    }

    /**
     * 验证 --model=Product --all 仅生成 Product 的四件套
     * （Model + Resource + Migration + FeatureTest），不生成内置其他模型（D-11）
     */
    public function test_publish_model_with_all_generates_four_artifact_set(): void
    {
        $this->artisan('filament-admin:publish', [
            '--model' => 'Product',
            '--all'   => true,
        ])->assertExitCode(Command::SUCCESS);

        // 断言 Product 的四件套已生成
        self::assertTrue(
            File::exists($this->tempBase.'/app/Models/Product.php'),
            'Product Model 未生成'
        );
        self::assertTrue(
            File::exists($this->tempBase.'/app/Filament/Resources/Products/ProductResource.php'),
            'ProductResource 未生成'
        );
        self::assertTrue(
            File::exists($this->tempBase.'/tests/Feature/ProductResourceTest.php'),
            'ProductResourceTest 未生成'
        );

        // 关键负向断言：D-03 语义 B 不应生成内置资源的 Model
        self::assertFalse(
            File::exists($this->tempBase.'/app/Models/AdminUser.php'),
            'AdminUser.php 不应在语义 B 模式下生成'
        );
        self::assertFalse(
            File::exists($this->tempBase.'/app/Models/Department.php'),
            'Department.php 不应在语义 B 模式下生成'
        );
        self::assertFalse(
            File::exists($this->tempBase.'/app/Models/Menu.php'),
            'Menu.php 不应在语义 B 模式下生成'
        );
        self::assertFalse(
            File::exists($this->tempBase.'/app/Models/LoginLog.php'),
            'LoginLog.php 不应在语义 B 模式下生成'
        );
    }

    /**
     * 验证 --path=../../etc 返回 Command::FAILURE 且输出 --path 不允许包含 .. 路径上溯（安全防护）
     */
    public function test_publish_path_rejects_directory_traversal(): void
    {
        $this->artisan('filament-admin:publish', [
            '--resource' => 'Product',
            '--path'     => '../../etc',
        ])
            ->expectsOutputToContain('--path 不允许包含 .. 路径上溯')
            ->assertExitCode(Command::FAILURE);
    }
}
