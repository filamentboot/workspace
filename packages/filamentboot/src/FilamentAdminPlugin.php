<?php

namespace FilamentAdmin;

use Filament\Contracts\Plugin;
use Filament\Panel;
use FilamentAdmin\Filament\Pages\Settings\GeneralSettingsPage;
use FilamentAdmin\Filament\Pages\Settings\LogSettingsPage;
use FilamentAdmin\Filament\Pages\Settings\SecuritySettingsPage;
use FilamentAdmin\Filament\Pages\Settings\UploadSettingsPage;
use FilamentAdmin\Filament\Resources\AdminUsers\AdminUserResource;
use FilamentAdmin\Filament\Resources\Departments\DepartmentResource;
use FilamentAdmin\Filament\Resources\LoginLogs\LoginLogResource;
use FilamentAdmin\Filament\Resources\Media\MediaResource;
use FilamentAdmin\Filament\Resources\Menus\MenuResource;
use FilamentAdmin\Filament\Widgets\QuickActionsWidget;
use FilamentAdmin\Filament\Widgets\QuickGuideWidget;
use FilamentAdmin\Filament\Widgets\RecentActivityWidget;
use FilamentAdmin\Filament\Widgets\SystemStatsWidget;
use FilamentAdmin\Filament\Widgets\WelcomeWidget;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Department;
use FilamentAdmin\Models\LoginLog;
use FilamentAdmin\Models\Menu;

/**
 * FilamentAdmin 插件入口
 *
 * 用户在 AdminPanelProvider 中通过 ->plugins([FilamentAdminPlugin::make()]) 注册。
 * 所有可替换的 Model 和 Resource 均提供绑定方法。
 */
class FilamentAdminPlugin implements Plugin
{
    /**
     * Guard 名称
     */
    protected string $guardName = 'admin';

    /**
     * 可替换的模型类
     *
     * @var array<string, class-string>
     */
    protected array $models = [
        'adminUser'  => AdminUser::class,
        'department' => Department::class,
        'loginLog'   => LoginLog::class,
        'menu'       => Menu::class,
    ];

    /**
     * 可替换的 Resource 类
     *
     * @var array<string, class-string>
     */
    protected array $resources = [
        'adminUser'  => AdminUserResource::class,
        'department' => DepartmentResource::class,
        'loginLog'   => LoginLogResource::class,
        'menu'       => MenuResource::class,
        'media'      => MediaResource::class,
    ];

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-admin';
    }

    public function register(Panel $panel): void
    {
        // 注册 Resources
        $panel->resources(array_values($this->resources));

        // 注册 Settings Pages
        $panel->pages([
            GeneralSettingsPage::class,
            UploadSettingsPage::class,
            SecuritySettingsPage::class,
            LogSettingsPage::class,
        ]);

        // 注册 Widgets
        $panel->widgets([
            WelcomeWidget::class,
            SystemStatsWidget::class,
            QuickActionsWidget::class,
            RecentActivityWidget::class,
            QuickGuideWidget::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        // 包启动时的初始化逻辑
    }

    /**
     * 配置 Guard 名称
     */
    public function guard(string $guardName): static
    {
        $this->guardName = $guardName;

        return $this;
    }

    public function getGuardName(): string
    {
        return $this->guardName;
    }

    /**
     * 绑定自定义 AdminUser 模型
     */
    public function adminUserModel(string $class): static
    {
        $this->models['adminUser'] = $class;

        return $this;
    }

    /**
     * 绑定自定义 Department 模型
     */
    public function departmentModel(string $class): static
    {
        $this->models['department'] = $class;

        return $this;
    }

    /**
     * 绑定自定义 LoginLog 模型
     */
    public function loginLogModel(string $class): static
    {
        $this->models['loginLog'] = $class;

        return $this;
    }

    /**
     * 绑定自定义 Menu 模型
     */
    public function menuModel(string $class): static
    {
        $this->models['menu'] = $class;

        return $this;
    }

    /**
     * 获取绑定的模型类
     *
     * @return class-string
     */
    public function getModel(string $name): string
    {
        return $this->models[$name] ?? throw new \InvalidArgumentException("Unknown model: {$name}");
    }

    /**
     * 绑定自定义 AdminUserResource
     */
    public function adminUserResource(string $class): static
    {
        $this->resources['adminUser'] = $class;

        return $this;
    }

    /**
     * 绑定自定义 DepartmentResource
     */
    public function departmentResource(string $class): static
    {
        $this->resources['department'] = $class;

        return $this;
    }

    /**
     * 绑定自定义 LoginLogResource
     */
    public function loginLogResource(string $class): static
    {
        $this->resources['loginLog'] = $class;

        return $this;
    }

    /**
     * 绑定自定义 MenuResource
     */
    public function menuResource(string $class): static
    {
        $this->resources['menu'] = $class;

        return $this;
    }

    /**
     * 获取绑定的 Resource 类
     *
     * @return class-string
     */
    public function getResource(string $name): string
    {
        return $this->resources[$name] ?? throw new \InvalidArgumentException("Unknown resource: {$name}");
    }
}
