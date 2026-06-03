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
     */
    protected function registerTranslations(): void
    {
        // 内置 2FA 包的中文翻译，覆盖该包的 en 翻译，实现零配置中文 UI
        $this->loadTranslationsFrom(
            __DIR__.'/../resources/lang/vendor/filament-two-factor-authentication',
            'filament-two-factor-authentication',
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
     */
    protected function registerListeners(): void
    {
        Event::listen(Login::class, Listeners\LogAdminLogin::class);
        Event::listen(Failed::class, Listeners\LogAdminLogin::class);
    }
}
