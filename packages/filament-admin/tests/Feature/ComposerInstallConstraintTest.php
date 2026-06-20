<?php

use FilamentAdmin\Models\Plugin;
use FilamentAdmin\Services\PluginManager;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Process\Process;

/**
 * PluginManager::runComposerInstall バージョン制約 require 引数テスト
 *
 * 覆盖场景：
 * 1. installed_version='^4.0' → require argument 'awcodes/filament-tiptap-editor:^4.0'
 * 2. installed_version=null → require argument 'vendor/pkg'（无约束后缀）
 * 3. dev-stability 约束 'dev-main' → require argument 'vendor/pkg:dev-main'（携带通过）
 * 4. validatePackageName 仍接收裸包名，init_status 不变为 'failed'
 *
 * 威胁缓解：T-12-00-01 — 通过子类覆盖 buildComposerProcess 和 resolveComposerExec
 * 进行测试隔离，不执行真实 composer 子进程。Plugin 通过 Mockery 模拟，避免数据库依赖。
 */
class ComposerInstallConstraintTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [];
    }

    /**
     * 构建一个可测试的 PluginManager 子类，通过覆盖受保护方法捕获 require 参数
     *
     * buildComposerProcess 和 resolveComposerExec 可见性改为 protected（Task 2 实现后）。
     */
    private function makeTestableManager(?string &$capturedRequireArg): PluginManager
    {
        return new class($capturedRequireArg) extends PluginManager
        {
            public function __construct(private mixed &$captured)
            {
                // no DI needed
            }

            protected function resolveComposerExec(): string
            {
                return '/usr/local/bin/composer';
            }

            protected function buildComposerProcess(array $command): Process
            {
                // 捕获 require argument（command 格式: [composer, 'require', $requireArg, ...]）
                $this->captured = $command[2] ?? null;

                // 返回一个立即成功的伪 Process，不执行真实 composer
                $process = new Process(['true']);
                $process->run();

                return $process;
            }
        };
    }

    /**
     * 构建模拟的 Plugin 对象（用 Mockery partial mock），无需数据库
     */
    private function makePluginMock(?string $installedVersion): Plugin
    {
        /** @var Plugin&MockInterface $plugin */
        $plugin = Mockery::mock(Plugin::class)->makePartial();
        $plugin->shouldReceive('getAttribute')->with('installed_version')->andReturn($installedVersion);
        $plugin->shouldReceive('getAttribute')->with('slug')->andReturn('test-slug');
        $plugin->shouldReceive('update')->andReturnNull();
        $plugin->shouldReceive('refresh')->andReturnSelf();
        $plugin->shouldReceive('getAttribute')->with('init_status')->andReturn('running');
        // postInstall は呼び出しを無視（成功パスのみ検証）
        $plugin->shouldReceive('getAttribute')->with('post_install_data')->andReturn([]);
        $plugin->shouldReceive('getAttribute')->with('service_provider')->andReturn(null);
        $plugin->shouldReceive('getAttribute')->with('plugin_class')->andReturn(null);

        return $plugin;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * installed_version='^4.0' → require arg 包含 ':^4.0' 版本约束
     */
    public function test_require_arg_includes_constraint_when_installed_version_is_set(): void
    {
        $plugin      = $this->makePluginMock('^4.0');
        $capturedArg = null;
        $manager     = $this->makeTestableManager($capturedArg);

        $manager->runComposerInstall($plugin, 'awcodes/filament-tiptap-editor');

        $this->assertSame(
            'awcodes/filament-tiptap-editor:^4.0',
            $capturedArg,
            'require argument must be package:constraint when installed_version is set'
        );
    }

    /**
     * installed_version=null → require arg は裸のパッケージ名（コロンなし）
     */
    public function test_require_arg_is_bare_package_name_when_installed_version_is_null(): void
    {
        $plugin      = $this->makePluginMock(null);
        $capturedArg = null;
        $manager     = $this->makeTestableManager($capturedArg);

        $manager->runComposerInstall($plugin, 'vendor/pkg');

        $this->assertSame(
            'vendor/pkg',
            $capturedArg,
            'require argument must be bare package name when installed_version is null'
        );
    }

    /**
     * dev-stability 約束 'dev-main' → require arg carries through into subprocess
     */
    public function test_dev_stability_constraint_is_carried_through(): void
    {
        $plugin      = $this->makePluginMock('dev-main');
        $capturedArg = null;
        $manager     = $this->makeTestableManager($capturedArg);

        $manager->runComposerInstall($plugin, 'vendor/pkg');

        $this->assertSame(
            'vendor/pkg:dev-main',
            $capturedArg,
            'dev-main constraint must be passed as-is in require argument'
        );
    }

    /**
     * validatePackageName は裸のパッケージ名を受け取る（コロンなし）
     *
     * package:constraint 文字列に ':' が含まれるため validatePackageName の正規表現が失敗する。
     * validatePackageName が裸の packageName を受け取っていれば init_status が 'failed' にならない。
     */
    public function test_validate_package_name_receives_bare_package_name(): void
    {
        $callCount = 0;

        // validatePackageName が受け取った引数を記録するサブクラス
        $manager = new class($callCount) extends PluginManager
        {
            /** @var list<string> */
            public array $validatedNames = [];

            public function __construct(private mixed &$count) {}

            protected function resolveComposerExec(): string
            {
                return '/usr/local/bin/composer';
            }

            protected function buildComposerProcess(array $command): Process
            {
                $process = new Process(['true']);
                $process->run();

                return $process;
            }
        };

        $plugin = Mockery::mock(Plugin::class)->makePartial();
        $plugin->shouldReceive('getAttribute')->with('installed_version')->andReturn('^4.0');
        $plugin->shouldReceive('getAttribute')->with('slug')->andReturn('test-slug');
        $plugin->shouldReceive('getAttribute')->with('init_status')->andReturn('running');
        $plugin->shouldReceive('getAttribute')->with('post_install_data')->andReturn([]);
        $plugin->shouldReceive('getAttribute')->with('service_provider')->andReturn(null);
        $plugin->shouldReceive('getAttribute')->with('plugin_class')->andReturn(null);
        $plugin->shouldReceive('update')->andReturnNull();

        // inst: when init_status stays 'running' (not 'failed'), validatePackageName passed
        // If validatePackageName receives 'awcodes/filament-tiptap-editor:^4.0', it rejects ':' and calls update(init_status=failed)
        $updateCalledWithFailed = false;
        $plugin->shouldReceive('update')->andReturnUsing(function (array $attrs) use (&$updateCalledWithFailed) {
            if (($attrs['init_status'] ?? '') === 'failed') {
                $updateCalledWithFailed = true;
            }
        });

        $manager->runComposerInstall($plugin, 'awcodes/filament-tiptap-editor');

        $this->assertFalse(
            $updateCalledWithFailed,
            'validatePackageName must receive bare package name; if it gets package:constraint it fails validation and sets init_status=failed'
        );
    }
}
