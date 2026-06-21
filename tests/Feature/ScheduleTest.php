<?php

use Illuminate\Console\Scheduling\Schedule;

it('操作日志清理命令已注册每日 02:00 调度', function () {
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($e): bool => str_contains($e->command ?? '', 'filamentboot:clean-activity-logs'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * *');
});

it('登录日志清理命令已注册每日 02:30 调度', function () {
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($e): bool => str_contains($e->command ?? '', 'filamentboot:clean-login-logs'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 2 * * *');
});
