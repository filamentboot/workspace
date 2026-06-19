<?php

namespace LaravelStack\FilamentAdminSite;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
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
        $isEnabled = false;

        try {
            // 优先从缓存读取，缓存写失败时降级为直接查 DB（不把写缓存异常误判为"未启用"）
            $isEnabled = Cache::remember(
                'filament-admin-site:is_enabled',
                now()->addHours(24),
                fn () => DB::table('plugins')
                    ->where('slug', 'filament-admin-site')
                    ->where('is_enabled', true)
                    ->exists()
            );
        } catch (\Throwable) {
            // 缓存不可用（权限、驱动故障）时直接查 DB
            try {
                $isEnabled = DB::table('plugins')
                    ->where('slug', 'filament-admin-site')
                    ->where('is_enabled', true)
                    ->exists();
            } catch (\Throwable) {
                // plugins 表未迁移或 DB 不可用：静默降级，不注册前台资源
            }
        }

        if ($isEnabled) {
            // 插件已启用：注册 Livewire 组件（须先于路由，Pitfall 6）
            $this->registerLivewireComponents();

            // 注册主题视图命名空间（须先于路由，确保 view() 可解析）
            $this->registerThemeViews();

            // 注册前台路由（loadRoutesFrom site.php）
            $this->registerFrontend();
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
     * 注册 Livewire 前台命名空间（Livewire v4 fix）
     *
     * Livewire v4 Finder::parseNamespaceAndName() 将 '::' 识别为命名空间分隔符，
     * Livewire::component() 显式注册的组件在命名空间查找路径中不可见（会直接返回 null）。
     * 必须改用 addNamespace() 注册类命名空间，
     * 使 filament-admin-site::case-filter 自动解析到 Http\Livewire\CaseFilter，
     *    filament-admin-site::contact-form  自动解析到 Http\Livewire\ContactForm。
     *
     * 必须在 registerFrontend()（loadRoutesFrom）之前调用（Pitfall 6）。
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::addNamespace(
            'filament-admin-site',
            classNamespace: 'LaravelStack\\FilamentAdminSite\\Http\\Livewire',
        );
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

        // 将 'filament-admin-site' 命名空间根指向当前主题目录（优先）
        // 10-05 在 themes/{active_theme}/ 下提供 home.blade.php、cases/index.blade.php 等
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views/themes/' . $activeTheme,
            'filament-admin-site'
        );

        // Fallback：Livewire 视图在 resources/views/livewire/，不在主题子目录下
        // Livewire 组件 render() 调用 view('filament-admin-site::livewire.*')，
        // 主题目录无该路径时 Laravel 视图查找器自动降级到此路径（Livewire v4 view resolution fix）
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'filament-admin-site'
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
