<?php

namespace Filamentboot\Commands;

use Filamentboot\Database\Seeders\SuperAdminSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Filamentboot 一键安装命令
 *
 * 七步顺序执行安装流程：
 * Step 1: 生成 AdminPanelProvider（幂等检测，D-11-03）
 * Step 2: 注入 admin guard 到 config/auth.php（幂等：已存在时跳过）
 * Step 3: vendor:publish filamentboot-config
 * Step 4: vendor:publish filamentboot-lang
 * Step 5: migrate（迁移由 FilamentbootServiceProvider::loadMigrationsFrom 自动加载，
 *           不 publish migrations，避免与 loadMigrationsFrom 同名类冲突；
 *           需要自定义迁移的用户可手动运行 vendor:publish --tag=filamentboot-migrations）
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
    protected $signature = 'filamentboot:install {--force : 强制覆盖已存在的 AdminPanelProvider}';

    /**
     * 命令说明
     *
     * @var string
     */
    protected $description = '安装 Filamentboot：生成 AdminPanelProvider，发布资源，执行迁移，创建超管账号';

    /**
     * 执行安装流程
     */
    public function handle(): int
    {
        $this->components->info('开始安装 Filamentboot...');

        // Step 1: 生成 AdminPanelProvider（幂等检测，D-11-03）
        if (! $this->generateProvider()) {
            return self::FAILURE;
        }

        // Step 2: 注入 admin guard 到 config/auth.php
        $this->injectAuthGuard();

        // Step 3: vendor:publish config（使用 tag 规避 Pitfall 1）
        $this->callSilently('vendor:publish', ['--tag' => 'filamentboot-config', '--ansi' => false]);
        $this->components->info('✓ 配置文件已发布到 config/filament-admin.php');

        // Step 3: vendor:publish lang
        $this->callSilently('vendor:publish', ['--tag' => 'filamentboot-lang', '--ansi' => false]);
        $this->components->info('✓ 语言文件已发布到 lang/vendor/filament-admin/');

        // Step 4: migrate（迁移由 FilamentbootServiceProvider::loadMigrationsFrom 自动加载，
        //   不再 publish migrations，避免与 auto-load 产生同名类冲突。
        //   若需自定义迁移，可手动运行 vendor:publish --tag=filamentboot-migrations）
        $this->components->info('正在执行数据库迁移...');

        if ($this->call('migrate') !== 0) {
            $this->components->error('迁移失败，请检查数据库配置后重试');

            return self::FAILURE;
        }

        $this->components->info('✓ 数据库迁移完成');

        // Step 5: 创建超级管理员（T-11-02：输出默认密码提示）
        $this->callSilently('db:seed', ['--class' => SuperAdminSeeder::class]);
        $this->components->info('✓ 超级管理员已创建：admin@example.com / password');

        // Step 7: 输出安装报告
        $this->newLine();
        $this->components->success('Filamentboot 安装完成！');
        $this->newLine();
        $this->line('  后台地址：<fg=cyan>/admin</>');
        $this->line('  默认账号：<fg=cyan>admin@example.com</> / <fg=cyan>password</>');
        $this->newLine();
        $this->components->warn('⚠  首次登录后请立即修改默认密码！');
        $this->newLine();
        $this->line('如需安装官网插件，运行：');
        $this->line('  <fg=cyan>composer require filamentboot/filamentboot-site</>');
        $this->line('  <fg=cyan>php artisan plugin:scan</>');

        return self::SUCCESS;
    }

    /**
     * 向 config/auth.php 注入 admin guard、admin_users provider 和密码重置配置
     *
     * 幂等：已存在 admin guard 时跳过，防止重复添加。
     * 使用字符串替换而非 AST 解析，保持文件原有格式。
     */
    protected function injectAuthGuard(): void
    {
        $authConfig = config_path('auth.php');

        if (! file_exists($authConfig)) {
            $this->components->warn('config/auth.php 不存在，跳过 admin guard 注入');

            return;
        }

        $content = file_get_contents($authConfig);

        // 幂等：已包含 admin guard 时跳过
        if (str_contains($content, "'admin'")) {
            $this->components->info('✓ admin guard 已存在，跳过注入');

            return;
        }

        // 注入 admin guard（在 'web' guard 之后）
        $guardSnippet = <<<'PHP'

        // 管理员 guard（由 filamentboot:install 添加）
        'admin' => [
            'driver'   => 'session',
            'provider' => 'admin_users',
        ],
PHP;
        $content = str_replace(
            "'web' => [\n            'driver' => 'session',\n            'provider' => 'users',\n        ],\n    ],",
            "'web' => [\n            'driver' => 'session',\n            'provider' => 'users',\n        ],".$guardSnippet."\n    ],",
            $content
        );

        // 注入 admin_users provider
        $providerSnippet = <<<'PHP'

        // 管理员用户 provider（由 filamentboot:install 添加）
        'admin_users' => [
            'driver' => 'eloquent',
            'model'  => \Filamentboot\Models\AdminUser::class,
        ],
PHP;
        // 在 providers 数组的末尾 ], 之前插入（找到 users provider 结束处）
        $content = preg_replace(
            "/(\\s+'users'\\s*=>\\s*\\[[^\\]]+\\],\\s*)(\\n\\s+\\/\\/.*?\\n\\s+\\],|\\n\\s+\\],)/s",
            '$1'.$providerSnippet.'$2',
            $content,
            1
        );

        // 注入 admin_users 密码重置
        $passwordSnippet = <<<'PHP'

        // 管理员密码重置（由 filamentboot:install 添加）
        'admin_users' => [
            'provider' => 'admin_users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
PHP;
        $content = preg_replace(
            "/(\\s+'users'\\s*=>\\s*\\[\\s*'provider'\\s*=>\\s*'users'[^\\]]+\\],\\s*)(\\n\\s+\\],)/s",
            '$1'.$passwordSnippet.'$2',
            $content,
            1
        );

        file_put_contents($authConfig, $content);
        $this->components->info('✓ admin guard 已注入到 config/auth.php');
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
