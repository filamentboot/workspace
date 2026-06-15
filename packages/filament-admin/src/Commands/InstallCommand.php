<?php

namespace FilamentAdmin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * FilamentAdmin 一键安装命令
 *
 * 按 D-11-02 七步顺序执行安装流程：
 * Step 1: 生成 AdminPanelProvider（幂等检测，D-11-03）
 * Step 2: vendor:publish filament-admin-config
 * Step 3: vendor:publish filament-admin-migrations
 * Step 4: vendor:publish filament-admin-lang
 * Step 5: migrate（失败则中断，T-11-03 缓解）
 * Step 6: db:seed SuperAdminSeeder
 * Step 7: 输出安装报告（T-11-02 缓解：提示修改默认密码）
 */
class InstallCommand extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'filament-admin:install {--force : 强制覆盖已存在的 AdminPanelProvider}';

    /**
     * 命令说明
     *
     * @var string
     */
    protected $description = '安装 FilamentAdmin：生成 AdminPanelProvider，发布资源，执行迁移，创建超管账号';

    /**
     * 执行安装流程
     */
    public function handle(): int
    {
        $this->components->info('开始安装 FilamentAdmin...');

        // Step 1: 生成 AdminPanelProvider（幂等检测，D-11-03）
        if (! $this->generateProvider()) {
            return self::FAILURE;
        }

        // Step 2: vendor:publish config（使用 tag 规避 Pitfall 1）
        $this->callSilently('vendor:publish', ['--tag' => 'filament-admin-config', '--ansi' => false]);
        $this->components->info('✓ 配置文件已发布到 config/filament-admin.php');

        // Step 3: vendor:publish migrations
        $this->callSilently('vendor:publish', ['--tag' => 'filament-admin-migrations', '--ansi' => false]);
        $this->components->info('✓ 迁移文件已发布到 database/migrations/');

        // Step 4: vendor:publish lang
        $this->callSilently('vendor:publish', ['--tag' => 'filament-admin-lang', '--ansi' => false]);
        $this->components->info('✓ 语言文件已发布到 lang/vendor/filament-admin/');

        // Step 5: migrate（失败时中断，T-11-03 缓解措施）
        $this->components->info('正在执行数据库迁移...');

        if ($this->call('migrate') !== 0) {
            $this->components->error('迁移失败，请检查数据库配置后重试');

            return self::FAILURE;
        }

        $this->components->info('✓ 数据库迁移完成');

        // Step 6: 创建超级管理员（T-11-02：输出默认密码提示）
        $this->callSilently('db:seed', ['--class' => 'FilamentAdmin\\Database\\Seeders\\SuperAdminSeeder']);
        $this->components->info('✓ 超级管理员已创建：admin@example.com / password');

        // Step 7: 输出安装报告
        $this->newLine();
        $this->components->success('FilamentAdmin 安装完成！');
        $this->newLine();
        $this->line('  后台地址：<fg=cyan>/admin</>');
        $this->line('  默认账号：<fg=cyan>admin@example.com</> / <fg=cyan>password</>');
        $this->newLine();
        $this->components->warn('⚠  首次登录后请立即修改默认密码！');
        $this->newLine();
        $this->line('如需安装官网插件，运行：');
        $this->line('  <fg=cyan>composer require laravelstack/filament-admin-site</>');
        $this->line('  <fg=cyan>php artisan plugin:scan</>');

        return self::SUCCESS;
    }

    /**
     * 生成 AdminPanelProvider 文件（幂等：已存在时询问是否覆盖）
     *
     * T-11-01 缓解：stub 必须含 authGuard('admin')，防止 web guard 误用于 admin 认证。
     *
     * @return bool 成功返回 true（含用户拒绝覆盖的情况），生成失败返回 false
     */
    protected function generateProvider(): bool
    {
        $path = app_path('Providers/Filament/AdminPanelProvider.php');
        $dir  = dirname($path);

        // 幂等检测：文件已存在且非 --force 时询问用户
        if (file_exists($path) && ! $this->option('force')) {
            if (! $this->confirm('AdminPanelProvider.php 已存在，是否覆盖？', false)) {
                $this->components->warn('已跳过 AdminPanelProvider 生成，使用现有文件');

                // 非失败，继续后续步骤（D-11-03）
                return true;
            }
        }

        // 确保目标目录存在
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 从 stub 读取并写入目标路径
        $stub = File::get(__DIR__.'/../../stubs/AdminPanelProvider.stub');
        File::put($path, $stub);

        // 注册到 bootstrap/providers.php（Laravel 13 标准方式）
        $bootstrapPath = app()->bootstrapPath('providers.php');

        if (file_exists($bootstrapPath)) {
            ServiceProvider::addProviderToBootstrapFile(
                'App\\Providers\\Filament\\AdminPanelProvider',
                $bootstrapPath
            );
        }

        $this->components->info('✓ AdminPanelProvider 已生成：app/Providers/Filament/AdminPanelProvider.php');

        return true;
    }
}
