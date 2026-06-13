<?php

namespace LaravelStack\FilamentAdminSite;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * 官网插件服务提供者
 *
 * 职责：
 * 1. 按 plugins.is_enabled 条件决定是否注册前台路由/视图/Livewire 组件
 * 2. plugins/settings 表未迁移时静默降级，不阻断应用启动（T-10-01-01 防护）
 * 3. 无论是否启用，均注册 settings 迁移供 php artisan migrate 使用
 *
 * 注意：registerFrontend() 仅在插件启用时调用。
 * catch 分支已 return，因此 plugins 表未迁移时 registerFrontend 不会被执行（Pitfall 1/2）。
 */
class SiteServiceProvider extends ServiceProvider
{
    /**
     * 启动服务提供者
     *
     * 优先检查 plugins 表中本插件的启用状态。
     * catch 分支：plugins 表未迁移时静默跳过前台注册，
     * 仅执行 registerMigrationsAndViews() 后返回，不抛异常。
     */
    public function boot(): void
    {
        try {
            $isEnabled = DB::table('plugins')
                ->where('slug', 'filament-admin-site')
                ->where('is_enabled', true)
                ->exists();

            if (! $isEnabled) {
                // 插件未启用：只注册迁移，不加载前台资源
                $this->registerMigrationsAndViews();

                return;
            }

            // 插件已启用：注册前台路由、视图、Livewire 组件
            $this->registerFrontend();
        } catch (\Throwable) {
            // plugins 表未迁移或数据库不可用时静默跳过前台注册
            // 此分支已 return，registerFrontend 不会被调用（符合 Pitfall 1/2 防护）
            $this->registerMigrationsAndViews();

            return;
        }

        // 无论启用与否，均注册迁移与视图发布
        $this->registerMigrationsAndViews();
    }

    /**
     * 注册前台路由、视图、Livewire 组件
     *
     * 本 Plan 10-01 仅留占位注释，真实实现由 Plan 10-04 完成：
     * - $this->loadRoutesFrom(__DIR__ . '/../routes/site.php')
     * - $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-admin-site')
     * - Livewire::component('site::contact-form', ContactForm::class)
     * - 主题视图目录根据 SiteSettings.active_theme 动态注册
     */
    protected function registerFrontend(): void
    {
        // TODO（Plan 10-04）：注册前台路由、视图、Livewire 组件
    }

    /**
     * 注册 Settings 与内容迁移文件，并发布资源
     *
     * 仅在 Console 环境中加载迁移，避免影响 HTTP 请求生命周期。
     * 发布 tag 供用户执行 php artisan vendor:publish --tag=filament-admin-site-migrations 复制迁移文件。
     */
    protected function registerMigrationsAndViews(): void
    {
        if ($this->app->runningInConsole()) {
            // 注册 settings 迁移（Spatie laravel-settings）
            $this->loadMigrationsFrom(__DIR__ . '/../database/settings');

            // 注册内容迁移（site_cases、site_solutions 等表由 Plan 10-02 创建）
            $settingsMigrationsPath = __DIR__ . '/../database/settings';
            $contentMigrationsPath  = __DIR__ . '/../database/migrations';

            // 发布迁移文件供用户自定义
            $this->publishes([
                $settingsMigrationsPath => database_path('migrations'),
            ], 'filament-admin-site-migrations');

            if (is_dir($contentMigrationsPath)) {
                $this->loadMigrationsFrom($contentMigrationsPath);

                $this->publishes([
                    $contentMigrationsPath => database_path('migrations'),
                ], 'filament-admin-site-migrations');
            }
        }
    }
}
