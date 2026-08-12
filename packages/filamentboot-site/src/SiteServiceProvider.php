<?php

namespace Filamentboot\FilamentbootSite;

use Filamentboot\FilamentbootSite\Cms\Blocks\BlockRegistry;
use Filamentboot\FilamentbootSite\Cms\Blocks\ContactFormBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\CtaBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\FaqBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\FeatureGridBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\GatedDownloadBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\HeroBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\MapBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\MediaTextBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\RichContentBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\RoadmapBlock;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\BuiltinContentTypes;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\ContentTypeRegistry;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypeRegistry;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\BooleanFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\DateFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\ImageFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\NumberFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\RichTextFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\SelectFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\TextareaFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\TextFieldType;
use Filamentboot\FilamentbootSite\Cms\ContentTypes\FieldTypes\UrlFieldType;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Observers\ContentRevisionObserver;
use Filamentboot\FilamentbootSite\Cms\Routing\SiteRedirectMiddleware;
use Filamentboot\FilamentbootSite\Console\Commands\CrawlerStatsCommand;
use Filamentboot\FilamentbootSite\Console\Commands\ImportRegionsCommand;
use Filamentboot\FilamentbootSite\Console\Commands\PublishCityPagesCommand;
use Filamentboot\FilamentbootSite\Console\Commands\PushBaiduCommand;
use Filamentboot\FilamentbootSite\Console\Commands\SyncContentTypesCommand;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Observers\SearchPushObserver;
use Filamentboot\FilamentbootSite\Services\ContactSourceLabel;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * 官网插件服务提供者
 *
 * 职责：
 * 1. 合并包配置，暴露 filamentboot-site-config 发布 tag
 * 2. 注册区块注册表单例（#12，无条件注册：后台表单与前台渲染都要用）
 * 3. 按 plugins.is_enabled 条件决定是否注册前台路由与主题视图
 * 4. plugins/settings 表未迁移时静默降级，不阻断应用启动（T-10-01-01 防护）
 * 5. 无论是否启用，均注册 settings 迁移供 php artisan migrate 使用
 *
 * 注意：前台资源（路由/主题视图）仅在插件启用时注册。
 *
 * ⚠️ #29 起公开页**零 Livewire**：包内不再有任何 Livewire 组件，因此不注册
 * Livewire 命名空间。原因是 Livewire 注入的 livewire.js 带 data-csrf，
 * 渲染时会调 csrf_token() → 起 session → 公开页必然带 Set-Cookie，整页缓存无从谈起。
 * Alpine 改由 resources/js/site.js 经 Vite 独立交付。
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

        $this->registerBlockRegistry();
        $this->registerFieldTypeRegistry();
        $this->registerContentTypeRegistry();

        // 单例：内部按请求缓存区划名表。非单例的话后台询盘列表一页 25 行
        // 就是 25 次全表查询（三期批次 8）
        $this->app->singleton(ContactSourceLabel::class);
    }

    /**
     * 注册区块注册表（#12）
     *
     * 单例：注册表同时充当安全白名单，全应用必须共用同一份，
     * 否则「已注册」的判断会随解析位置漂移。
     *
     * 内置区块在此一次性注册；宿主可在自己的 ServiceProvider::boot() 里
     * 用 app(BlockRegistry::class)->register(new MyBlock) 追加自定义区块。
     */
    protected function registerBlockRegistry(): void
    {
        $this->app->singleton(BlockRegistry::class, function (): BlockRegistry {
            $registry = new BlockRegistry;

            $registry->registerMany([
                new HeroBlock,
                new RichContentBlock,
                new MediaTextBlock,
                new FeatureGridBlock,
                new CtaBlock,
                new FaqBlock,
                new ContactFormBlock,
                new MapBlock,
                new GatedDownloadBlock,
                new RoadmapBlock,
            ]);

            return $registry;
        });
    }

    /**
     * 注册字段类型注册表（批次 5，可配置内容类型系统）
     *
     * 单例，同 BlockRegistry：既是查找表也是白名单。内置字段类型在此一次性
     * 注册；宿主可在自己的 ServiceProvider::boot() 里用
     * app(FieldTypeRegistry::class)->register(new MyFieldType) 追加自定义字段类型。
     */
    protected function registerFieldTypeRegistry(): void
    {
        $this->app->singleton(FieldTypeRegistry::class, function (): FieldTypeRegistry {
            $registry = new FieldTypeRegistry;

            $registry->registerMany([
                new TextFieldType,
                new TextareaFieldType,
                new RichTextFieldType,
                new NumberFieldType,
                new BooleanFieldType,
                new DateFieldType,
                new SelectFieldType,
                new ImageFieldType,
                new UrlFieldType,
            ]);

            return $registry;
        });
    }

    /**
     * 注册内容类型注册表（批次 5，可配置内容类型系统）
     *
     * 单例，收集 ContentTypeDefinition 声明。内置友情链接、广告位两个内容类型
     * （BuiltinContentTypes，批次 5 验收），宿主/包在自己的 ServiceProvider::boot()
     * 里用 app(ContentTypeRegistry::class)->register(...) 可追加更多声明。
     * SyncContentTypesCommand 与前台通用渲染器都读这份注册表。
     */
    protected function registerContentTypeRegistry(): void
    {
        $this->app->singleton(ContentTypeRegistry::class, function (): ContentTypeRegistry {
            $registry = new ContentTypeRegistry;

            $registry->registerMany(BuiltinContentTypes::all());

            return $registry;
        });
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
            // 注册主题视图命名空间（须先于路由，确保 view() 可解析）
            $this->registerThemeViews();

            // 向所有前台视图共享站点设置（须先于路由）
            $this->shareSiteSettings();

            // 注册前台路由（loadRoutesFrom site.php）
            $this->registerFrontend();

            // 内容发布后主动推送搜索引擎（须后于路由：观察器要用 route() 生成 URL）
            $this->registerSearchPushObserver();

            // 旧 URL 301 重定向（#18）：必须是全局中间件——旧 URL 已经匹配不到
            // 任何路由，路由中间件跑不到
            $this->registerRedirectMiddleware();
        }

        // 内容版本快照（#15，批次 1.5c 起覆盖全 7 类内容）：与前台无关，
        // 插件禁用时后台仍在用，故不放进上面的分支
        $this->registerContentRevisionObserver();

        // 无论启用与否，均注册迁移与视图发布
        $this->registerMigrationsAndViews();

        // 控制台命令（存量内容回推、爬虫统计、区划导入）
        $this->registerCommands();
    }

    /**
     * 注册内容发布推送观察器（B4）
     *
     * 七类内容共用一个观察器：它只关心「发布状态变了没有」与「现在可见吗」，
     * 后者回查各模型自己的 published() 作用域。⚠️ 2026-08-11 补上 SiteCityPage——
     * 此前这份清单历史上漏过它。它没有 slug 列，URL 形状与其余六类不同，
     * SearchPushObserver 内部按模型类型单独分支处理，不是「不需要按模型分支」。
     */
    protected function registerSearchPushObserver(): void
    {
        foreach ([SiteCase::class, SiteSolution::class, SitePackage::class, SiteProduct::class, SitePage::class, NewsArticle::class, SiteCityPage::class] as $model) {
            $model::observe(SearchPushObserver::class);
        }
    }

    /**
     * 注册旧 URL 重定向中间件（#18）
     *
     * pushMiddleware 而不是 prependMiddleware：让它排在宿主自己的全局中间件
     * 之后，宿主若有维护模式、IP 黑名单一类的拦截，那些应当先生效。
     *
     * 中间件自身第一件事是挂载路径早退，所以「全局」的代价对宿主路由是零查询。
     * 表未迁移时 DB 查询会抛，但 boot 阶段不查表——真正查表时表必然已存在
     * （迁移没跑的项目连官网前台都打不开）。
     */
    protected function registerRedirectMiddleware(): void
    {
        $this->callAfterResolving(Kernel::class, function (Kernel $kernel): void {
            $kernel->pushMiddleware(SiteRedirectMiddleware::class);
        });
    }

    /**
     * 注册内容版本快照观察器（#15，批次 1.5c 起覆盖全部 7 类内容）
     *
     * 走 Observer 而不是 Filament 钩子：钩子只覆盖后台表单，Observer 连 seeder、
     * tinker、状态流转 Action 与未来的 API 一起覆盖。
     *
     * 与 registerSearchPushObserver() 同一个模式，但这里显式列全 7 类
     * （含 SiteCityPage）——那份清单历史上漏过 SiteCityPage，这里不照抄，
     * 单独维护一份完整的。
     */
    protected function registerContentRevisionObserver(): void
    {
        foreach ([SitePage::class, SiteCase::class, NewsArticle::class, SiteSolution::class, SiteProduct::class, SitePackage::class, SiteCityPage::class] as $model) {
            $model::observe(ContentRevisionObserver::class);
        }
    }

    /**
     * 注册控制台命令
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            PushBaiduCommand::class,
            CrawlerStatsCommand::class,
            ImportRegionsCommand::class,
            PublishCityPagesCommand::class,
            SyncContentTypesCommand::class,
        ]);
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

            $this->registerThemeErrorViews($paths);
        });
    }

    /**
     * 让框架的错误页解析到主题里的 errors/
     *
     * 两套主题各带一份 `errors/404.blade.php`，但**从来没被渲染过**：框架的
     * `RegisterErrorViewPaths` 在渲染 HttpException 时会把 `errors` 命名空间
     * 重置成 `config('view.paths')` 逐个拼 `/errors` 再加框架自带那份，
     * 命名空间视图（`filamentboot-site::errors.404`）根本不在候选里。
     * 于是任何 404 都落到框架那张白底 "Not Found" ——写好的 404 页是死代码。
     *
     * 修法是把主题目录**追加**进 `config('view.paths')`：
     *
     * - 追加而不是前插，宿主自己的 `resources/views/errors/404.blade.php`
     *   仍然优先，覆盖能力不变
     * - 在 `callAfterResolving('view')` 里改，此时视图查找器已经拿着自己那份
     *   路径数组建好了，改 config 只影响错误页解析这一处，不会让主题里的
     *   其它视图变成可以不带命名空间直接引用
     * - 只在插件启用时调用（调用方在启用分支内），禁用时宿主保持框架默认行为
     *
     * @param  list<string>  $paths  主题视图路径，顺序同命名空间
     */
    protected function registerThemeErrorViews(array $paths): void
    {
        $viewPaths = (array) config('view.paths', []);

        foreach ($paths as $path) {
            if (! in_array($path, $viewPaths, true) && is_dir($path.'/errors')) {
                $viewPaths[] = $path;
            }
        }

        config(['view.paths' => $viewPaths]);
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

        // 前台脚本（#29：Alpine 的交付路径）。发布后落在
        // resources/js/vendor/filamentboot-site/site.js，对应 config 的第二个候选。
        $resourcesJsPath = __DIR__.'/../resources/js';
        if (is_dir($resourcesJsPath)) {
            $this->publishes([
                $resourcesJsPath => resource_path('js/vendor/'.self::VIEW_NAMESPACE),
            ], 'filamentboot-site-assets');
        }
    }
}
