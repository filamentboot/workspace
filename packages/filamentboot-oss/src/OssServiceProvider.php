<?php

namespace Filamentboot\FilamentbootOss;

use Filamentboot\FilamentbootOss\Settings\OssSettings;
use Iidestiny\Flysystem\Oss\OssAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

/**
 * 阿里云 OSS 存储服务提供者
 *
 * 职责：
 * 1. 通过 Storage::extend('oss') 注册 Flysystem OSS 磁盘驱动
 * 2. 读取 OssSettings 凭证注入 filesystems.disks.oss 运行时配置
 * 3. settings 表未迁移时静默降级，不阻断应用启动（T-08-02 防护）
 */
class OssServiceProvider extends ServiceProvider
{
    /**
     * 注册 OSS Flysystem 驱动与运行时配置
     */
    public function boot(): void
    {
        // 注册 'oss' Flysystem 驱动工厂（仅在实际调用 Storage::disk('oss') 时执行闭包）
        Storage::extend('oss', function (Application $app, array $config): FilesystemAdapter {
            $adapter = new OssAdapter(
                $config['access_key'] ?? '',
                $config['secret_key'] ?? '',
                $config['endpoint'] ?? '',
                $config['bucket'] ?? '',
                $config['isCName'] ?? false,
                $config['root'] ?? '',
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });

        // 从 OssSettings 读取凭证，注入 filesystems.disks.oss 运行时配置
        // try/catch 防止 settings 表未迁移时崩溃（Pitfall 2，T-08-02）
        try {
            // D-08-10：仅当插件在后台启用时才注入磁盘配置
            $isEnabled = DB::table('plugins')
                ->where('slug', 'filamentboot-oss')
                ->where('is_enabled', true)
                ->exists();

            if (! $isEnabled) {
                $this->registerMigrations();

                return;
            }

            /** @var OssSettings $settings */
            $settings = app(OssSettings::class);

            if (! empty($settings->access_key_id) && ! empty($settings->bucket)) {
                config([
                    'filesystems.disks.oss' => [
                        'driver'     => 'oss',
                        'access_key' => $settings->access_key_id,
                        'secret_key' => $settings->access_key_secret,
                        'endpoint'   => $settings->endpoint,
                        'bucket'     => $settings->bucket,
                        'region'     => $settings->region,
                        'isCName'    => false,
                        'root'       => '',
                    ],
                ]);
            }
        } catch (\Throwable) {
            // settings 表未迁移或数据库不可用时静默跳过，
            // 应用仍可正常启动（T-08-02 防护）
        }

        $this->registerMigrations();
    }

    /**
     * 注册 OSS Settings 迁移文件
     *
     * 仅在 Console 环境中加载，避免影响 HTTP 请求生命周期。
     */
    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/settings');
        }
    }
}
