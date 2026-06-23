<?php

namespace Filamentboot;

use Filament\Forms\Components\FileUpload;
use Filament\Support\Exceptions\Halt;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Department;
use Filamentboot\Models\LoginLog;
use Filamentboot\Models\Menu;
use Filamentboot\Observers\ActivityLogObserver;
use Filamentboot\Policies\ActivityLogPolicy;
use Filamentboot\Policies\AdminUserPolicy;
use Filamentboot\Policies\DepartmentPolicy;
use Filamentboot\Policies\LoginLogPolicy;
use Filamentboot\Policies\MenuPolicy;
use Filamentboot\Policies\RolePolicy;
use Filamentboot\Settings\UploadSettings;
use Filamentboot\Support\UploadValidator;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

/**
 * Filamentboot 包服务提供者
 *
 * 负责注册迁移、命令、监听器、Observer、Policy 等包级资源。
 */
class FilamentbootServiceProvider extends ServiceProvider
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
        $this->mergeConfigFrom(__DIR__.'/../config/filamentboot.php', 'filamentboot');

        // Resolve factory class names for Filamentboot models:
        // Laravel's default resolver guesses Database\Factories\{Model}Factory,
        // but package factories live under Filamentboot\Database\Factories\.
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            if (str_starts_with($modelName, 'Filamentboot\\')) {
                $modelShortName = class_basename($modelName);

                return 'Filamentboot\\Database\\Factories\\'.$modelShortName.'Factory';
            }

            // Fall back to Laravel default for non-package models
            $modelNamespace = 'App\\Models\\';
            $factoryNamespace = 'Database\\Factories\\';

            $modelName = str_starts_with($modelName, $modelNamespace)
                ? str_replace($modelNamespace, '', $modelName)
                : $modelName;

            return $factoryNamespace.$modelName.'Factory';
        });
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
        $this->registerUploadGuards();
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
                Commands\InstallCommand::class,
                Commands\PublishCommand::class,
                Commands\CleanActivityLogs::class,
                Commands\CleanLoginLogs::class,
                // FEAT-03：四个 CRUD 代码生成命令
                Commands\MakeFilamentbootModelCommand::class,
                Commands\MakeFilamentbootResourceCommand::class,
                Commands\MakeFilamentbootMigrationCommand::class,
                Commands\MakeFilamentbootTestCommand::class,
                // DEMO-02：演示站数据重置命令
                Commands\DemoReset::class,
                // PLUGIN：插件扫描命令
                Commands\PluginScanCommand::class,
                // MKTPLACE-09：一方插件合规审查命令
                Commands\AuditPluginsCommand::class,
            ]);
        }
    }

    /**
     * 注册 Blade 视图路径
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filamentboot');
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

        // 覆盖 filament-impersonate 横幅翻译，对齐锁定字面（D-19）。
        // loadTranslationsFrom 用 callAfterResolving，若插件 SP 后启动会被覆盖。
        // 用 app->booted() 确保在所有 SP 启动后最后注册，保证优先级。
        $langPath = __DIR__.'/../resources/lang';
        $this->app->booted(function () use ($langPath): void {
            app('translator')->getLoader()->addNamespace('filament-impersonate', $langPath);
        });
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
     * 注册 Policy 映射与合并 Gate::before 回调
     *
     * 合并回调顺序（两个判定必须按此顺序，不可颠倒）：
     * ① 演示拒绝：演示账号的写类 ability 直接返回 false（短路拒绝）
     * ② 超管放行：super_admin 角色返回 true，其余返回 null
     *
     * 顺序说明：若将超管放行置于演示拒绝之前，挂 super_admin 的演示账号
     * 会被超管放行短路（返回 true），导致演示写操作屏蔽完全失效。
     * 合并为单一回调可消除多 Gate::before 注册顺序不确定性。
     */
    protected function registerPolicies(): void
    {
        // 注册所有 Policy 映射
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 超级管理员绕过所有权限检查（含演示拒绝前置分支）
        $superAdminRole = config('filamentboot.super_admin_role', 'super_admin');

        Gate::before(function (Authenticatable $user, string $ability) use ($superAdminRole) {
            // 防御：非 HasRoles 用户（如普通 User 模型）跳过判断
            if (! method_exists($user, 'hasRole')) {
                return null;
            }

            // ① 演示拒绝分支（必须早于超管放行执行）
            // 演示账号的写类 ability 返回 false（直接拒绝，Gate 链路短路），
            // 读类 ability 返回 null（放行后续判定，超管放行将允许 viewAny 等读操作）。
            if (self::isDemoUser($user) && self::isWriteAbility($ability)) {
                return false;
            }

            // ② 超管放行分支
            return $user->hasRole($superAdminRole) ? true : null;
        });
    }

    /**
     * 判断是否为演示账号
     *
     * 演示账号以 email 字段 demo@example.com 为唯一标识。
     * 使用 isset 防御 email 属性不存在的情况。
     *
     * @param  Authenticatable  $user  当前认证用户
     */
    private static function isDemoUser(Authenticatable $user): bool
    {
        return isset($user->email) && $user->email === 'demo@example.com';
    }

    /**
     * 判断是否为写类 ability（演示环境需屏蔽的操作）
     *
     * 使用前缀匹配覆盖 Filament 所有写类 ability，包括：
     * create / update / delete / deleteAny / forceDelete / forceDeleteAny /
     * restore / restoreAny / replicate / reorder
     *
     * @param  string  $ability  Gate ability 名称
     */
    private static function isWriteAbility(string $ability): bool
    {
        $blocked = [
            'create', 'update', 'delete', 'deleteAny',
            'forceDelete', 'forceDeleteAny',
            'restore', 'restoreAny', 'replicate', 'reorder',
        ];

        foreach ($blocked as $prefix) {
            if (str_starts_with($ability, $prefix)) {
                return true;
            }
        }

        return false;
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
     * 支持 filamentboot-config / filamentboot-migrations /
     * filamentboot-views / filamentboot-lang / filamentboot-stubs
     * 五个标签，让用户通过 `php artisan vendor:publish --tag=filamentboot-*` 将资源复制到项目。
     */
    protected function registerPublishes(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // D-07: config tag — 将包内配置文件发布到用户项目 config/ 目录
        $this->publishes([
            __DIR__.'/../config/filamentboot.php' => config_path('filamentboot.php'),
        ], 'filamentboot-config');

        // D-08: migrations tag — 使用 publishesMigrations 自动追加时间戳前缀（Laravel 13 原生 API）
        $this->publishesMigrations([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'filamentboot-migrations');

        // D-09: views tag — 将包内视图目录发布到用户项目 resources/views/vendor/filamentboot
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filamentboot'),
        ], 'filamentboot-views');

        // D-10: lang tag — 分别发布 en 和 zh_CN 骨架目录；精确指向子目录避免将 2FA 翻译误发布（Pitfall 4）
        $this->publishes([
            __DIR__.'/../resources/lang/en' => $this->app->langPath('vendor/filamentboot/en'),
        ], 'filamentboot-lang');

        $this->publishes([
            __DIR__.'/../resources/lang/zh_CN' => $this->app->langPath('vendor/filamentboot/zh_CN'),
        ], 'filamentboot-lang');

        // D-11: stubs tag — 将包内 stubs 目录发布到用户项目 stubs/vendor/filamentboot
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/vendor/filamentboot'),
        ], 'filamentboot-stubs');
    }

    /**
     * 注册上传安全守卫：
     * 1. 依据 UploadSettings.default_disk 同步 media-library.disk_name（D-08-07）
     * 2. 注册 FileUpload 全局校验规则，接入 UploadValidator（D-08-08/09）
     */
    protected function registerUploadGuards(): void
    {
        $this->app->booted(function (): void {
            // 同步 media-library.disk_name（settings 表不存在时静默跳过）
            try {
                /** @var UploadSettings $uploadSettings */
                $uploadSettings = app(UploadSettings::class);
                if (! empty($uploadSettings->default_disk)) {
                    config(['media-library.disk_name' => $uploadSettings->default_disk]);
                }
            } catch (\Throwable) {
                // settings 表不存在（首次迁移前）或 UploadSettings 未注册，静默跳过
            }

            // 注册全局 FileUpload 校验规则
            FileUpload::configureUsing(function (FileUpload $component): void {
                $component->afterStateUpdated(function ($state) use ($component): void {
                    if (! $state) {
                        return;
                    }

                    try {
                        $uploadSettings = app(UploadSettings::class);
                        $validator      = new UploadValidator($uploadSettings);
                    } catch (\Throwable) {
                        return;
                    }

                    $files = is_array($state) ? $state : [$state];

                    foreach ($files as $file) {
                        if (! $file instanceof UploadedFile) {
                            continue;
                        }

                        try {
                            $validator->validate($file);
                        } catch (\RuntimeException $e) {
                            $component->state(null);
                            $component->callAfterStateUpdated();
                            throw Halt::make($e->getMessage());
                        }
                    }
                });
            });
        });
    }
}
