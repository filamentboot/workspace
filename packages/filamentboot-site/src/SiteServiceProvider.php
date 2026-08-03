<?php

namespace Filamentboot\FilamentbootSite;

use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * 官网插件服务提供者
 *
 * 职责：
 * 1. 合并包配置，暴露 filamentboot-site-config 发布 tag
 * 2. 按 plugins.is_enabled 条件决定是否注册前台路由/视图/Livewire 组件
 * 3. plugins/settings 表未迁移时静默降级，不阻断应用启动（T-10-01-01 防护）
 * 4. 无论是否启用，均注册 settings 迁移供 php artisan migrate 使用
 *
 * 注意：前台资源（路由/Livewire/主题视图）仅在插件启用时注册。
 * catch 分支已 return，因此 plugins 表未迁移时 registerFrontend 不会被执行（Pitfall 1/2）。
 */
class SiteServiceProvider extends ServiceProvider
{
    /**
     * 视图命名空间
     */
    public const VIEW_NAMESPACE = 'filamentboot-site';

    /**
     * 插件 slug，与 composer.json extra.filamentboot.slug 保持一致
     */
    public const PLUGIN_SLUG = 'filamentboot-site';

    /**
     * 注册服务
     *
     * mergeConfigFrom 必须在 register() 执行，确保 boot() 与路由文件读到的
     * config('filamentboot-site.*') 已就绪。
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filamentboot-site.php', 'filamentboot-site');
    }

    /**
     * 启动服务提供者
     *
     * 优先检查 plugins 表中本插件的启用状态。
     * catch 分支：plugins 表未迁移时静默跳过前台注册，
     * 仅执行 registerMigrationsAndViews() 后返回，不抛异常。
     */
    public function boot(): void
    {
        if ($this->pluginIsEnabled()) {
            // 插件已启用：注册 Livewire 组件（须先于路由，Pitfall 6）
            $this->registerLivewireComponents();

            // 注册主题视图命名空间（须先于路由，确保 view() 可解析）
            $this->registerThemeViews();

            // 向所有前台视图共享站点设置（须先于路由）
            $this->shareSiteSettings();

            // 注册前台路由（loadRoutesFrom site.php）
            $this->registerFrontend();
        }

        // 无论启用与否，均注册迁移与视图发布
        $this->registerMigrationsAndViews();
    }

    /**
     * 查询插件启用状态
     *
     * 优先从缓存读取，缓存写失败时降级为直接查 DB（不把写缓存异常误判为"未启用"）。
     * 缓存键与 PluginManager::enable()/disable() 的 Cache::forget("{slug}:is_enabled") 同源，
     * 后台启停插件后立即失效。
     */
    protected function pluginIsEnabled(): bool
    {
        $query = fn (): bool => DB::table('plugins')
            ->where('slug', self::PLUGIN_SLUG)
            ->where('is_enabled', true)
            ->exists();

        try {
            return (bool) Cache::remember(self::PLUGIN_SLUG.':is_enabled', now()->addHours(24), $query);
        } catch (\Throwable) {
            // 缓存不可用（权限、驱动故障）时直接查 DB
            try {
                return $query();
            } catch (\Throwable) {
                // plugins 表未迁移或 DB 不可用：静默降级，不注册前台资源
                return false;
            }
        }
    }

    /**
     * 注册前台路由
     *
     * 通过 loadRoutesFrom 加载 routes/site.php，挂载模式由
     * config('filamentboot-site.route.mode') 决定（prefix/root/domain），
     * 默认 prefix 不抢占宿主根路由。
     * 仅在插件启用时调用，确保禁用时不影响现有 routes/web.php（T-10-04-01，Pitfall 1）。
     */
    protected function registerFrontend(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/site.php');
    }

    /**
     * 注册 Livewire 前台命名空间（Livewire v4 fix）
     *
     * Livewire v4 Finder::parseNamespaceAndName() 将 '::' 识别为命名空间分隔符，
     * Livewire::component() 显式注册的组件在命名空间查找路径中不可见（会直接返回 null）。
     * 必须改用 addNamespace() 注册类命名空间，
     * 使 filamentboot-site::case-filter 自动解析到 Http\Livewire\CaseFilter，
     *    filamentboot-site::contact-form  自动解析到 Http\Livewire\ContactForm。
     *
     * 必须在 registerFrontend()（loadRoutesFrom）之前调用（Pitfall 6）。
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::addNamespace(
            self::VIEW_NAMESPACE,
            classNamespace: 'Filamentboot\\FilamentbootSite\\Http\\Livewire',
        );
    }

    /**
     * 注册主题视图命名空间，并确定解析优先级
     *
     * 解析顺序（先注册者优先，Laravel 视图查找器按 hint 顺序返回首个命中）：
     *
     *   1. 宿主发布覆盖  resources/views/vendor/filamentboot-site/themes/{theme}
     *   2. 包内当前主题  resources/views/themes/{theme}
     *   3. 跨主题共享层  resources/views/shared
     *   4. 包内视图根    resources/views（Livewire 组件视图兜底）
     *
     * 用 replaceNamespace() 而非 loadViewsFrom()：后者会自作主张把
     * resources/views/vendor/filamentboot-site 追加到 hint 列表，
     * 而发布产物实际落在该目录的 themes/{theme}/ 子目录下，命名空间根对不上，
     * 正是宿主发布的主题视图一直不生效的原因。
     */
    protected function registerThemeViews(): void
    {
        $theme = static::resolveActiveTheme();

        $paths = array_values(array_filter([
            resource_path('views/vendor/'.self::VIEW_NAMESPACE.'/themes/'.$theme),
            __DIR__.'/../resources/views/themes/'.$theme,
            __DIR__.'/../resources/views/shared',
            __DIR__.'/../resources/views',
        ], 'is_dir'));

        $this->callAfterResolving('view', function ($view) use ($paths): void {
            $view->replaceNamespace(self::VIEW_NAMESPACE, $paths);
        });
    }

    /**
     * 解析当前生效的主题目录名
     *
     * 读取 SiteSettings.active_theme，并用 config('filamentboot-site.themes')
     * 白名单校验，非法值（含目录穿越尝试）强制回退到默认主题（T-10-04-07）。
     * settings 表未迁移时同样回退（Pitfall 2）。
     */
    public static function resolveActiveTheme(): string
    {
        /** @var array<string, string> $allowed */
        $allowed = config('filamentboot-site.themes', []);
        /** @var string $default */
        $default = config('filamentboot-site.default_theme', 'decoration');

        /** @var string $theme */
        $theme = rescue(
            fn () => app(SiteSettings::class)->active_theme,
            $default,
            report: false
        );

        return array_key_exists($theme, $allowed) ? $theme : $default;
    }

    /**
     * 向所有前台视图共享 $siteSettings
     *
     * 此前控制器用 compact('settings') 传参，而全部 Blade 读的是 $siteSettings，
     * 变量名对不上导致站点设置从未生效（页脚联系方式空白、logo 不显示、
     * 默认 SEO 描述为空）。改用命名空间通配 composer 统一注入，
     * 使控制器视图、Livewire 组件视图、错误页和两套主题都能拿到同一份设置。
     */
    protected function shareSiteSettings(): void
    {
        View::composer(self::VIEW_NAMESPACE.'::*', function (ViewContract $view): void {
            $view->with('siteSettings', rescue(
                fn () => app(SiteSettings::class),
                null,
                report: false
            ));
        });
    }

    /**
     * 注册 Settings 与内容迁移文件，并发布资源
     *
     * 仅在 Console 环境中加载迁移，避免影响 HTTP 请求生命周期。
     * 发布 tag 供用户执行 php artisan vendor:publish 复制配置、迁移文件与主题视图。
     */
    protected function registerMigrationsAndViews(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // 配置文件（tag filamentboot-site-config，composer.json post_install 声明的 tag）
        $this->publishes([
            __DIR__.'/../config/filamentboot-site.php' => config_path('filamentboot-site.php'),
        ], 'filamentboot-site-config');

        // 注册 settings 迁移（Spatie laravel-settings）
        $settingsMigrationsPath = __DIR__.'/../database/settings';
        $this->loadMigrationsFrom($settingsMigrationsPath);

        $this->publishes([
            $settingsMigrationsPath => database_path('migrations'),
        ], 'filamentboot-site-migrations');

        // 注册内容迁移（site_cases、site_solutions 等表）
        $contentMigrationsPath = __DIR__.'/../database/migrations';
        if (is_dir($contentMigrationsPath)) {
            $this->loadMigrationsFrom($contentMigrationsPath);

            $this->publishes([
                $contentMigrationsPath => database_path('migrations'),
            ], 'filamentboot-site-migrations');
        }

        // 发布主题视图（tag filamentboot-site-views），供用户覆盖定制（D-10-12）
        // 发布后落在 resources/views/vendor/filamentboot-site/themes/{theme}/，
        // 与 registerThemeViews() 的第 1 优先级路径对应。
        $themesViewsPath = __DIR__.'/../resources/views/themes';
        if (is_dir($themesViewsPath)) {
            $this->publishes([
                $themesViewsPath => resource_path('views/vendor/'.self::VIEW_NAMESPACE.'/themes'),
            ], 'filamentboot-site-views');
        }

        // 发布 CSS/JS 前端资源（tag filamentboot-site-assets）
        $resourcesCssPath = __DIR__.'/../resources/css';
        if (is_dir($resourcesCssPath)) {
            $this->publishes([
                $resourcesCssPath => resource_path('css/vendor/'.self::VIEW_NAMESPACE),
            ], 'filamentboot-site-assets');
        }
    }
}
