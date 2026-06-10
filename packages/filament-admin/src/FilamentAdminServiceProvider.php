<?php

namespace FilamentAdmin;

use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Department;
use FilamentAdmin\Models\LoginLog;
use FilamentAdmin\Models\Menu;
use FilamentAdmin\Observers\ActivityLogObserver;
use FilamentAdmin\Policies\ActivityLogPolicy;
use FilamentAdmin\Policies\AdminUserPolicy;
use FilamentAdmin\Policies\DepartmentPolicy;
use FilamentAdmin\Policies\LoginLogPolicy;
use FilamentAdmin\Policies\MenuPolicy;
use FilamentAdmin\Policies\RolePolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

/**
 * FilamentAdmin 包服务提供者
 *
 * 负责注册迁移、命令、监听器、Observer、Policy 等包级资源。
 */
class FilamentAdminServiceProvider extends ServiceProvider
{
    /**
     * Policy 映射表
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        AdminUser::class  => AdminUserPolicy::class,
        LoginLog::class   => LoginLogPolicy::class,
        Menu::class       => MenuPolicy::class,
        Department::class => DepartmentPolicy::class,
        Activity::class   => ActivityLogPolicy::class,
        Role::class       => RolePolicy::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-admin.php', 'filament-admin');
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerCommands();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerObservers();
        $this->registerPolicies();
        $this->registerListeners();
        $this->registerPublishes();
    }

    /**
     * 注册数据库迁移
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * 注册 Artisan 命令
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\PublishCommand::class,
                Commands\CleanActivityLogs::class,
                Commands\CleanLoginLogs::class,
                // FEAT-03：四个 CRUD 代码生成命令
                Commands\MakeFilamentAdminModelCommand::class,
                Commands\MakeFilamentAdminResourceCommand::class,
                Commands\MakeFilamentAdminMigrationCommand::class,
                Commands\MakeFilamentAdminTestCommand::class,
            ]);
        }
    }

    /**
     * 注册 Blade 视图路径
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-admin');
    }

    /**
     * 注册语言包
     *
     * 通过 loadTranslationsFrom 注册翻译命名空间覆盖：
     * - filament-two-factor-authentication：内置中文翻译，零配置中文 UI
     * - filament-impersonate（zh_CN）：将横幅文案覆盖为锁定中文字面（D-19）
     *   "正在模拟 {username}（结束模拟）"
     */
    protected function registerTranslations(): void
    {
        // 内置 2FA 包的中文翻译，覆盖该包的 en 翻译，实现零配置中文 UI
        $this->loadTranslationsFrom(
            __DIR__.'/../resources/lang/vendor/filament-two-factor-authentication',
            'filament-two-factor-authentication',
        );

        // 覆盖 filament-impersonate 插件的 zh_CN 翻译：
        // 将横幅文案对齐锁定字面 "正在模拟 {username}（结束模拟）"（D-19）。
        // FileLoader::addNamespace 后注册覆盖先注册，确保主包翻译优先于插件翻译。
        $this->loadTranslationsFrom(
            __DIR__.'/../resources/lang/zh_CN',
            'filament-impersonate',
        );
    }

    /**
     * 注册模型 Observer
     */
    protected function registerObservers(): void
    {
        AdminUser::observe(ActivityLogObserver::class);
        Department::observe(ActivityLogObserver::class);
        Menu::observe(ActivityLogObserver::class);
        Role::observe(ActivityLogObserver::class);
    }

    /**
     * 注册 Policy 映射与超级管理员 Gate::before
     */
    protected function registerPolicies(): void
    {
        // 注册所有 Policy 映射
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 超级管理员绕过所有权限检查
        $superAdminRole = config('filament-admin.super_admin_role', 'super_admin');

        Gate::before(function (Authenticatable $user, string $ability) use ($superAdminRole) {
            // 防御：非 HasRoles 用户（如普通 User 模型）跳过判断
            if (! method_exists($user, 'hasRole')) {
                return null;
            }

            return $user->hasRole($superAdminRole) ? true : null;
        });
    }

    /**
     * 注册事件监听器
     *
     * 注意：LogAdminLogin 通过 Laravel 自动发现机制注册，
     * 但为了确保在包内正确注册，这里显式注册。
     * Impersonation 事件（FEAT-01 / D-32）通过 ImpersonationListener 接入 ActivityLogger。
     */
    protected function registerListeners(): void
    {
        Event::listen(Login::class, Listeners\LogAdminLogin::class);
        Event::listen(Failed::class, Listeners\LogAdminLogin::class);
        // 新增：模拟登录事件（FEAT-01），写入统一审计日志
        Event::listen(EnterImpersonation::class, [Listeners\ImpersonationListener::class, 'handleEnter']);
        Event::listen(LeaveImpersonation::class, [Listeners\ImpersonationListener::class, 'handleLeave']);
    }

    /**
     * 注册可发布资源出口（vendor:publish 5 个 tag）
     *
     * 支持 filament-admin-config / filament-admin-migrations /
     * filament-admin-views / filament-admin-lang / filament-admin-stubs
     * 五个标签，让用户通过 `php artisan vendor:publish --tag=filament-admin-*` 将资源复制到项目。
     */
    protected function registerPublishes(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // D-07: config tag — 将包内配置文件发布到用户项目 config/ 目录
        $this->publishes([
            __DIR__.'/../config/filament-admin.php' => config_path('filament-admin.php'),
        ], 'filament-admin-config');

        // D-08: migrations tag — 使用 publishesMigrations 自动追加时间戳前缀（Laravel 13 原生 API）
        $this->publishesMigrations([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'filament-admin-migrations');

        // D-09: views tag — 将包内视图目录发布到用户项目 resources/views/vendor/filament-admin
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-admin'),
        ], 'filament-admin-views');

        // D-10: lang tag — 分别发布 en 和 zh_CN 骨架目录；精确指向子目录避免将 2FA 翻译误发布（Pitfall 4）
        $this->publishes([
            __DIR__.'/../resources/lang/en' => $this->app->langPath('vendor/filament-admin/en'),
        ], 'filament-admin-lang');

        $this->publishes([
            __DIR__.'/../resources/lang/zh_CN' => $this->app->langPath('vendor/filament-admin/zh_CN'),
        ], 'filament-admin-lang');

        // D-11: stubs tag — 将包内 stubs 目录发布到用户项目 stubs/vendor/filament-admin
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/vendor/filament-admin'),
        ], 'filament-admin-stubs');
    }
}
