<?php

namespace Filamentboot\FilamentbootCos;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Filamentboot\FilamentbootCos\Settings\CosSettings;

/**
 * 腾讯云 COS 插件服务提供者
 *
 * 负责：
 * 1. 从 CosSettings 读取凭证并注入 filesystems.disks.cos 配置。
 *    cos 驱动本身由 overtrue/laravel-filesystem-cos 包的内置 ServiceProvider 自动注册，
 *    本 ServiceProvider 仅负责配置注入（per RESEARCH A2）。
 * 2. 注册 settings 迁移文件（仅在 console 环境）。
 *
 * Pitfall 2 处理：Settings 表在首次迁移前不存在，使用 try/catch (\Throwable) 包裹
 * Settings 读取，确保应用启动时不因 settings 表缺失而崩溃。
 */
class CosServiceProvider extends ServiceProvider
{
    /**
     * 注册服务容器绑定
     */
    public function register(): void
    {
        // 无需注册额外绑定
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        // 从 CosSettings 读取凭证并注入 filesystems.disks.cos 磁盘配置
        // try/catch 防止 settings 表未迁移时崩溃（Pitfall 2）
        try {
            // D-08-10：仅当插件在后台启用时才注入磁盘配置
            $isEnabled = DB::table('plugins')
                ->where('slug', 'filamentboot-cos')
                ->where('is_enabled', true)
                ->exists();

            if (! $isEnabled) {
                $this->registerMigrations();

                return;
            }

            /** @var CosSettings $settings */
            $settings = app(CosSettings::class);

            if (! empty($settings->secret_id) && ! empty($settings->bucket)) {
                config(['filesystems.disks.cos' => [
                    'driver'     => 'cos',
                    'app_id'     => $settings->app_id,
                    'secret_id'  => $settings->secret_id,
                    'secret_key' => $settings->secret_key,
                    'region'     => $settings->region,
                    'bucket'     => $settings->bucket,
                ]]);
            }
        } catch (\Throwable) {
            // settings 表未迁移或凭证读取失败时静默跳过，不阻断应用启动
        }

        $this->registerMigrations();
    }

    /**
     * 注册 settings 迁移文件（仅在 console 环境避免多余 IO）
     */
    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/settings');
        }
    }
}
