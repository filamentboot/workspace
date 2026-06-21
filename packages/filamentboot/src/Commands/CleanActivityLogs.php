<?php

namespace Filamentboot\Commands;

use Filamentboot\Settings\LogSettings;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * 清理旧操作日志
 *
 * 默认从 LogSettings.activity_log_retention_days 读取保留天数；
 * --days 选项传入时作为覆盖值。保留天数为 0 表示永久保留（不删除）。
 */
class CleanActivityLogs extends Command
{
    /** @var string */
    protected $signature = 'filamentboot:clean-activity-logs {--days= : 覆盖配置中的保留天数（留空则读 LogSettings）}';

    /** @var string */
    protected $description = '清理指定天数以前的操作日志';

    public function handle(): int
    {
        // 从选项或 LogSettings 确定保留天数
        $option = $this->option('days');
        $days   = $option !== null
            ? (int) $option
            : app(LogSettings::class)->activity_log_retention_days;

        // 0 表示永久保留，不删除任何记录
        if ($days <= 0) {
            $this->info('保留天数为 0，永久保留，未清理任何操作日志。');

            return self::SUCCESS;
        }

        $deleted = Activity::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("已清理 {$deleted} 条操作日志。");

        return self::SUCCESS;
    }
}
