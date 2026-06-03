<?php

namespace FilamentAdmin\Commands;

use FilamentAdmin\Models\LoginLog;
use Illuminate\Console\Command;

/**
 * 清理旧登录日志
 */
class CleanLoginLogs extends Command
{
    protected $signature = 'filament-admin:clean-login-logs {--days=90}';

    protected $description = '清理指定天数以前的登录日志';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = LoginLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("已清理 {$deleted} 条登录日志。");

        return self::SUCCESS;
    }
}
