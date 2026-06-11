<?php

namespace FilamentAdmin\Commands;

use Illuminate\Console\Command;

/**
 * 重置演示站数据
 *
 * 将演示站数据库重置到初始演示状态（drop 所有表后重新迁移并播种）。
 * 包含生产环境护栏：非演示环境（APP_DEMO 未开启）且未传 --force 时
 * 拒绝执行，防止误删生产数据。
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : 跳过环境护栏（仅测试用）}';

    protected $description = '重置演示站数据到初始演示状态';

    public function handle(): int
    {
        // [BLOCKING] 生产环境护栏：仅演示环境（APP_DEMO=true）或携带 --force 时放行
        if (! $this->option('force') && ! config('app.demo', false)) {
            $this->error('仅演示环境可执行（APP_DEMO=true），生产环境请勿运行。');

            return self::FAILURE;
        }

        $this->info('开始重置演示数据...');

        // 临时确保 app.demo 配置为 true，使 DatabaseSeeder 包含 DemoSeeder
        // 在当前进程内设置，保证 migrate:fresh --seed 时 DemoSeeder 被播种
        config(['app.demo' => true]);

        // 使用 migrate:fresh --seed 重置（方案 A）：drop 所有表后重建并播种
        // 演示账号由 DatabaseSeeder（APP_DEMO=true 时含 DemoSeeder）重建
        $exitCode = $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        // CR-02：捕获 migrate:fresh 退出码。重置失败时演示库可能停在半重置损坏态，
        // 必须显式失败，避免每日 04:00 cron 谎报成功而监控无感知。
        if ($exitCode !== self::SUCCESS) {
            $this->error("演示数据重置失败：migrate:fresh 退出码 {$exitCode}。");

            return self::FAILURE;
        }

        // 清理缓存，避免悬挂引用
        $this->call('cache:clear');

        $this->info('演示数据已重置。');

        return self::SUCCESS;
    }
}
