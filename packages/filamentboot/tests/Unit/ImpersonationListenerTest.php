<?php

namespace FilamentAdmin\Tests\Unit;

use FilamentAdmin\Listeners\ImpersonationListener;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Services\ActivityLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
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
 *
 * 本测试为纯单元测试（PHPUnit TestCase），不依赖 Orchestra Testbench，
 * 避免 ServiceProvider bootstrap 副作用影响其他测试类（Mockery 全局状态隔离）。
 */
class ImpersonationListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * 每个测试后清理 Mockery，防止 mock 污染全局静态状态
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * handleEnter：impersonator 是 AdminUser 时调用 ActivityLogger::log()，action = 'impersonate.enter'
     */
    public function test_handle_enter_calls_activity_logger_with_enter_action(): void
    {
        /** @var AdminUser&MockInterface $impersonator */
        $impersonator     = Mockery::mock(AdminUser::class)->makePartial();
        $impersonator->id = 1;

        /** @var AdminUser&MockInterface $impersonated */
        $impersonated     = Mockery::mock(AdminUser::class)->makePartial();
        $impersonated->id = 2;

        /** @var ActivityLogger&MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);

        /** @var Expectation $expectation */
        $expectation = $logger->shouldReceive('log');
        $expectation->once()
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
        /** @var AdminUser&MockInterface $impersonator */
        $impersonator     = Mockery::mock(AdminUser::class)->makePartial();
        $impersonator->id = 1;

        /** @var AdminUser&MockInterface $impersonated */
        $impersonated     = Mockery::mock(AdminUser::class)->makePartial();
        $impersonated->id = 2;

        /** @var ActivityLogger&MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);

        /** @var Expectation $expectation */
        $expectation = $logger->shouldReceive('log');
        $expectation->once()
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
        /** @var AdminUser&MockInterface $impersonator */
        $impersonator     = Mockery::mock(AdminUser::class)->makePartial();
        $impersonator->id = 1;

        /** @var ActivityLogger&MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldNotReceive('log');

        $listener = new ImpersonationListener($logger);
        $listener->handleLeave(new LeaveImpersonation($impersonator, null));

        // 无异常抛出，shouldNotReceive 断言确保 log 未被调用
        $this->addToAssertionCount(1);
    }

    /**
     * handleEnter：impersonator 非 AdminUser 时不调用 log（非 admin guard 用户过滤）
     */
    public function test_handle_enter_does_not_log_when_impersonator_is_not_admin_user(): void
    {
        /** @var Authenticatable&MockInterface $impersonator */
        $impersonator = Mockery::mock(Authenticatable::class);

        /** @var AdminUser&MockInterface $impersonated */
        $impersonated     = Mockery::mock(AdminUser::class)->makePartial();
        $impersonated->id = 2;

        /** @var ActivityLogger&MockInterface $logger */
        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldNotReceive('log');

        $listener = new ImpersonationListener($logger);
        $listener->handleEnter(new EnterImpersonation($impersonator, $impersonated));

        // 无异常抛出，shouldNotReceive 断言确保 log 未被调用
        $this->addToAssertionCount(1);
    }
}
