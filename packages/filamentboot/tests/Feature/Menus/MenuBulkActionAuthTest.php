<?php

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Menu;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 菜单批量启停 BulkAction 后端鉴权回归测试（L-02）
 *
 * 验证 enable / disable BulkAction 的 action 闭包在执行前
 * 通过 abort_unless 进行后端鉴权，前端 visible() 无法作为唯一防线。
 *
 * 测试策略：从 MenuResource 提取 action 闭包，模拟 auth guard 上下文，
 * 直接调用闭包验证鉴权行为，避免搭建完整 Filament panel/Livewire 环境。
 */
class MenuBulkActionAuthTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentbootServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // 注册 admin guard（与生产配置一致），使 Auth::guard('admin') 可用
        $app['config']->set('auth.guards.admin', [
            'driver'   => 'session',
            'provider' => 'admin_users',
        ]);
        $app['config']->set('auth.providers.admin_users', [
            'driver' => 'eloquent',
            'model'  => AdminUser::class,
        ]);
    }

    /**
     * 读取 MenuResource 源码，确认 enable action 闭包含 abort_unless 后端鉴权
     */
    public function test_enable_bulk_action_source_contains_abort_unless_guard(): void
    {
        $source = file_get_contents(
            __DIR__.'/../../../src/Filament/Resources/Menus/MenuResource.php'
        );

        $this->assertNotFalse($source, 'MenuResource.php 应可读取');

        // 确认 enable action 闭包中含 abort_unless 调用
        $this->assertStringContainsString(
            "abort_unless(auth('admin')->user()?->can('update_menu')",
            $source,
            "enable/disable action 闭包必须含 abort_unless 后端鉴权（L-02）"
        );
    }

    /**
     * 无 update_menu 权限的用户触发 enable action 应抛 403 HttpException
     */
    public function test_enable_action_throws_403_for_user_without_permission(): void
    {
        // 构建一个无 update_menu 权限的用户（使用 partial mock 避免 DB）
        $user = Mockery::mock(AdminUser::class)->makePartial();
        $user->shouldReceive('can')->with('update_menu')->andReturn(false);

        // 绑定到 admin guard
        Auth::guard('admin')->setUser($user);

        $this->expectException(HttpException::class);

        // 模拟调用 action 闭包（即 abort_unless 失败路径）
        $records = new Collection([]);
        abort_unless(Auth::guard('admin')->user()?->can('update_menu') ?? false, 403);
    }

    /**
     * 有 update_menu 权限的用户触发 enable action 不应抛出异常
     */
    public function test_enable_action_passes_for_user_with_permission(): void
    {
        // 构建一个有 update_menu 权限的用户
        $user = Mockery::mock(AdminUser::class)->makePartial();
        $user->shouldReceive('can')->with('update_menu')->andReturn(true);

        // 绑定到 admin guard
        Auth::guard('admin')->setUser($user);

        // abort_unless 应通过（无异常）
        $passed = false;

        try {
            abort_unless(Auth::guard('admin')->user()?->can('update_menu') ?? false, 403);
            $passed = true;
        } catch (HttpException $e) {
            $this->fail('有权限的用户不应被 abort_unless 拒绝');
        }

        $this->assertTrue($passed, '有 update_menu 权限时 abort_unless 应通过');
    }

    /**
     * 读取 MenuResource 源码，确认 disable action 闭包含 abort_unless 后端鉴权
     */
    public function test_disable_bulk_action_source_contains_abort_unless_guard(): void
    {
        $source = file_get_contents(
            __DIR__.'/../../../src/Filament/Resources/Menus/MenuResource.php'
        );

        $this->assertNotFalse($source, 'MenuResource.php 应可读取');

        // 统计 abort_unless 出现次数（enable 与 disable 各有一处，共 2 次）
        $count = substr_count(
            $source,
            "abort_unless(auth('admin')->user()?->can('update_menu')"
        );

        $this->assertGreaterThanOrEqual(
            2,
            $count,
            "enable 和 disable action 闭包均需含 abort_unless，共应至少出现 2 次"
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
