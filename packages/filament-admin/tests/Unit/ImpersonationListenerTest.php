<?php

namespace FilamentAdmin\Tests\Unit;

use FilamentAdmin\FilamentAdminServiceProvider;
use FilamentAdmin\Listeners\ImpersonationListener;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Services\ActivityLogger;
use Illuminate\Foundation\Application;
use Mockery;
use Orchestra\Testbench\TestCase;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

/**
 * ImpersonationListener 单元测试
 *
 * 验证模拟登录事件监听器的行为（FEAT-01 / D-32）：
 * - handleEnter：EnterImpersonation 事件触发时调用 ActivityLogger::log(causer, subject, 'impersonate.enter')
 * - handleLeave：LeaveImpersonation 事件触发时调用 ActivityLogger::log(causer, subject, 'impersonate.leave')
 * - handleLeave：impersonated 为 null 时不调用 log 且不抛异常（防御 ?Authenticatable 可空）
 * - handleEnter：impersonator 非 AdminUser 时不调用 log（guard 过滤）
 */
class ImpersonationListenerTest extends TestCase
{
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
     * handleEnter：impersonator 是 AdminUser 时调用 ActivityLogger::log()，action = 'impersonate.enter'
     */
    public function test_handle_enter_calls_activity_logger_with_enter_action(): void
    {
        /** @var AdminUser&\Mockery\MockInterface $impersonator */
        $impersonator = Mockery::mock(AdminUser::class)->makePartial();
        $impersonator->id = 1;

        /** @var AdminUser&\Mockery\MockInterface $impersonated */
        $impersonated = Mockery::mock(AdminUser::class)->makePartial();
        $impersonated->id = 2;

        /** @var ActivityLogger&\Mockery\MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::type(AdminUser::class),
                Mockery::type(AdminUser::class),
                'impersonate.enter',
            );

        $listener = new ImpersonationListener($logger);
        $listener->handleEnter(new EnterImpersonation($impersonator, $impersonated));
    }

    /**
     * handleLeave：impersonator 是 AdminUser 且 impersonated 非 null 时调用 log，action = 'impersonate.leave'
     */
    public function test_handle_leave_calls_activity_logger_with_leave_action(): void
    {
        /** @var AdminUser&\Mockery\MockInterface $impersonator */
        $impersonator = Mockery::mock(AdminUser::class)->makePartial();
        $impersonator->id = 1;

        /** @var AdminUser&\Mockery\MockInterface $impersonated */
        $impersonated = Mockery::mock(AdminUser::class)->makePartial();
        $impersonated->id = 2;

        /** @var ActivityLogger&\Mockery\MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('log')
            ->once()
            ->with(
                Mockery::type(AdminUser::class),
                Mockery::type(AdminUser::class),
                'impersonate.leave',
            );

        $listener = new ImpersonationListener($logger);
        $listener->handleLeave(new LeaveImpersonation($impersonator, $impersonated));
    }

    /**
     * handleLeave：impersonated 为 null 时不调用 log 且不抛异常（?Authenticatable 可空防御）
     */
    public function test_handle_leave_does_not_log_when_impersonated_is_null(): void
    {
        /** @var AdminUser&\Mockery\MockInterface $impersonator */
        $impersonator = Mockery::mock(AdminUser::class)->makePartial();
        $impersonator->id = 1;

        /** @var ActivityLogger&\Mockery\MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldNotReceive('log');

        $listener = new ImpersonationListener($logger);
        $listener->handleLeave(new LeaveImpersonation($impersonator, null));

        // 无异常抛出，测试通过即为防御成功
        $this->assertTrue(true);
    }

    /**
     * handleEnter：impersonator 非 AdminUser 时不调用 log（非 admin guard 用户过滤）
     */
    public function test_handle_enter_does_not_log_when_impersonator_is_not_admin_user(): void
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable&\Mockery\MockInterface $impersonator */
        $impersonator = Mockery::mock(\Illuminate\Contracts\Auth\Authenticatable::class);

        /** @var AdminUser&\Mockery\MockInterface $impersonated */
        $impersonated = Mockery::mock(AdminUser::class)->makePartial();
        $impersonated->id = 2;

        /** @var ActivityLogger&\Mockery\MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldNotReceive('log');

        $listener = new ImpersonationListener($logger);
        $listener->handleEnter(new EnterImpersonation($impersonator, $impersonated));

        $this->assertTrue(true);
    }
}
