<?php

namespace FilamentAdmin\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * 清理旧操作日志
 */
class CleanActivityLogs extends Command
{
    protected $signature = 'filament-admin:clean-activity-logs {--days=180}';

    protected $description = '清理指定天数以前的操作日志';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = Activity::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("已清理 {$deleted} 条操作日志。");

        return self::SUCCESS;
    }
}
