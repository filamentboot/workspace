<?php

use FilamentAdmin\Commands\CleanActivityLogs;
use FilamentAdmin\Commands\CleanLoginLogs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每日凌晨 2 点清理 180 天前的操作日志
Schedule::command(CleanActivityLogs::class)->dailyAt('02:00');

// 每日凌晨 2 点 30 分清理 90 天前的登录日志
Schedule::command(CleanLoginLogs::class)->dailyAt('02:30');
