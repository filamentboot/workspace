<?php

namespace Filamentboot\FilamentbootCos\Tests\Unit;

use Filamentboot\FilamentbootCos\CosServiceProvider;
use Filamentboot\FilamentbootCos\Settings\CosSettings;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * CosServiceProvider 单元测试
 *
 * 验证：
 * (a) 凭证完整时 boot 后 filesystems.disks.cos.driver === 'cos'
 * (b) settings 表不存在或凭证为空时 boot() 不抛异常（Pitfall 2 防护）
 *
 * 说明：CosSettings 继承 Spatie Settings（构造为 final），使用 ReflectionClass
 * newInstanceWithoutConstructor 绕过 DB 依赖，直接设置属性创建 stub。
 */
class CosServiceProviderTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建 Laravel Application 实例
        $this->app = new Application(
            dirname(__DIR__, 4) // worktree 根目录
        );

        // 初始化 config repository（不触发 Settings 配置读取）
        $this->app->singleton('config', function () {
            return new ConfigRepository([
                'filesystems' => ['disks' => []],
            ]);
        });

        // 使 app() / config() 辅助函数指向此 application
        Application::setInstance($this->app);

        // 清除 Facade 静态缓存，确保 DB facade 使用本测试的 Application
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Facade::clearResolvedInstances();
        Application::setInstance(null);
    }

    /**
     * 创建 CosSettings stub（绕过 Spatie Settings 的 final __construct 和 DB 依赖）
     */
    private function makeCosSettingsStub(): CosSettings
    {
        /** @var CosSettings $stub */
        $stub             = (new ReflectionClass(CosSettings::class))->newInstanceWithoutConstructor();
        $stub->secret_id  = 'test-secret-id';
        $stub->secret_key = 'test-secret-key';
        $stub->app_id     = '1234567890';
        $stub->bucket     = 'my-test-bucket';
        $stub->region     = 'ap-guangzhou';

        return $stub;
    }

    /**
     * 凭证完整时，boot 后 filesystems.disks.cos.driver 应为 'cos'
     *
     * 使用匿名类 mock `db` 服务，模拟 plugins 表中 filament-admin-cos 已启用（D-08-10）。
     */
    public function test_cos_disk_config_injected_when_credentials_present(): void
    {
        // Mock DB facade：模拟 DB::table('plugins')->where()->where()->exists() 返回 true
        $this->app->instance('db', new class
        {
            /** @phpstan-ignore-next-line */
            public function table(string $table): static
            {
                return $this;
            }

            /** @phpstan-ignore-next-line */
            public function where(string $column, mixed $value): static
            {
                return $this;
            }

            public function exists(): bool
            {
                return true;
            }
        });

        $this->app->instance(CosSettings::class, $this->makeCosSettingsStub());

        $provider = new CosServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        self::assertSame('cos', config('filesystems.disks.cos.driver'));
        self::assertSame('test-secret-id', config('filesystems.disks.cos.secret_id'));
        self::assertSame('my-test-bucket', config('filesystems.disks.cos.bucket'));
    }

    /**
     * settings 表不存在时 boot() 必须不抛异常（Pitfall 2 防护）
     */
    public function test_boot_does_not_throw_when_settings_unavailable(): void
    {
        // 绑定抛出异常的假实现，模拟 settings 表不存在
        $this->app->bind(CosSettings::class, function () {
            throw new \RuntimeException('settings 表不存在（模拟 Pitfall 2）');
        });

        $provider = new CosServiceProvider($this->app);

        // boot() 不应抛出任何 Throwable
        try {
            $provider->boot();
            self::assertTrue(true); // 到达此处说明 try/catch (\Throwable) 生效
        } catch (\Throwable $e) {
            self::fail('boot() 抛出了异常，但应当静默处理：'.$e->getMessage());
        }
    }
}
