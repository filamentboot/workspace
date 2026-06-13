<?php

namespace LaravelStack\FilamentAdminSite;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use LaravelStack\FilamentAdminSite\Http\Livewire\CaseFilter;
use LaravelStack\FilamentAdminSite\Http\Livewire\ContactForm;
use LaravelStack\FilamentAdminSite\Settings\SiteSettings;
use Livewire\Livewire;

/**
 * 官网插件服务提供者
 *
 * 职责：
 * 1. 按 plugins.is_enabled 条件决定是否注册前台路由/视图/Livewire 组件
 * 2. plugins/settings 表未迁移时静默降级，不阻断应用启动（T-10-01-01 防护）
 * 3. 无论是否启用，均注册 settings 迁移供 php artisan migrate 使用
 *
 * 注意：前台资源（路由/Livewire/主题视图）仅在插件启用时注册。
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

            // 插件已启用：注册 Livewire 组件（须先于路由，Pitfall 6）
            $this->registerLivewireComponents();

            // 注册主题视图命名空间（须先于路由，确保 view() 可解析）
            $this->registerThemeViews();

            // 注册前台路由（loadRoutesFrom site.php）
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
     * 注册前台路由
     *
     * 通过 loadRoutesFrom 加载 routes/site.php，
     * 接管 /、/cases、/solutions、/products、/{slug} 及 /en/ 英文镜像（SITE-02/D-10-02）。
     * 仅在插件启用时调用，确保禁用时不影响现有 routes/web.php（T-10-04-01，Pitfall 1）。
     */
    protected function registerFrontend(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/site.php');
    }

    /**
     * 注册 Livewire 前台组件
     *
     * 注册两个前台组件到全局 Livewire 注册表（Pattern 6）：
     * - filament-admin-site::contact-form → ContactForm
     * - filament-admin-site::case-filter  → CaseFilter
     *
     * 必须在 registerFrontend()（loadRoutesFrom）之前调用，
     * 避免视图渲染时组件尚未注册（Pitfall 6）。
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('filament-admin-site::contact-form', ContactForm::class);
        Livewire::component('filament-admin-site::case-filter', CaseFilter::class);
    }

    /**
     * 注册主题视图命名空间（BLOCKER 2 修复点，SITE-03 主题切换服务端落点）
     *
     * 读取 SiteSettings.active_theme，将 'filament-admin-site' 视图命名空间根
     * 指向对应主题目录（resources/views/themes/{active_theme}/）。
     * 当用户在后台更改 active_theme 并 view:clear 后，下次请求即加载新主题目录（Pattern 4）。
     *
     * 安全防护：
     * - rescue() 降级：settings 表未迁移时降级到 'decoration'（Pitfall 2）
     * - 白名单校验：非法主题名强制回退 'decoration'，防目录穿越（T-10-04-07）
     *
     * 额外注册共享视图命名空间（filament-admin-site-shared），使 Livewire 视图
     * resources/views/livewire/ 可通过 filament-admin-site::livewire.* 解析。
     *
     * 注意：本方法是 SITE-03 主题切换的服务端落点。
     * active_theme 改变后，配合 SiteSettingsPage 保存时调用 view:clear，
     * 下次请求即加载新主题目录的 Blade 模板（10-05 在 themes/{active_theme}/ 下提供视图）。
     */
    protected function registerThemeViews(): void
    {
        /** @var string $activeTheme */
        $activeTheme = rescue(
            fn () => app(SiteSettings::class)->active_theme,
            'decoration',
            report: false
        );

        // 白名单校验，防目录穿越（T-10-04-07）
        $allowedThemes = ['decoration', 'tech-product'];
        if (! in_array($activeTheme, $allowedThemes, true)) {
            $activeTheme = 'decoration';
        }

        // 将 'filament-admin-site' 命名空间根指向当前主题目录
        // 10-05 在 themes/{active_theme}/ 下提供 home.blade.php、cases/index.blade.php 等
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views/themes/' . $activeTheme,
            'filament-admin-site'
        );

        // 注册共享视图命名空间，使 Livewire 视图（resources/views/livewire/）可解析
        // 10-05 将 Livewire 视图放于 resources/views/livewire/，非主题子目录下
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'filament-admin-site-shared'
        );
    }

    /**
     * 注册 Settings 与内容迁移文件，并发布资源
     *
     * 仅在 Console 环境中加载迁移，避免影响 HTTP 请求生命周期。
     * 发布 tag 供用户执行 php artisan vendor:publish 复制迁移文件与主题视图。
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

            // 发布主题视图（tag filament-admin-site-views），供用户覆盖定制（D-10-12）
            $themesViewsPath = __DIR__ . '/../resources/views/themes';
            if (is_dir($themesViewsPath)) {
                $this->publishes([
                    $themesViewsPath => resource_path('views/vendor/filament-admin-site/themes'),
                ], 'filament-admin-site-views');
            }

            // 发布 CSS/JS 前端资源（tag filament-admin-site-assets，供 10-05/vite）
            $resourcesCssPath = __DIR__ . '/../resources/css';
            if (is_dir($resourcesCssPath)) {
                $this->publishes([
                    $resourcesCssPath => resource_path('css/vendor/filament-admin-site'),
                ], 'filament-admin-site-assets');
            }
        }
    }
}
