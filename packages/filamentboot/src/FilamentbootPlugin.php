<?php

namespace Filamentboot;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filamentboot\AvatarProviders\InitialsAvatarProvider;
use Filamentboot\Filament\Pages\Settings\GeneralSettingsPage;
use Filamentboot\Filament\Pages\Settings\LogSettingsPage;
use Filamentboot\Filament\Pages\Settings\SecuritySettingsPage;
use Filamentboot\Filament\Pages\Settings\UploadSettingsPage;
use Filamentboot\Filament\Resources\AdminUsers\AdminUserResource;
use Filamentboot\Filament\Resources\Departments\DepartmentResource;
use Filamentboot\Filament\Resources\LoginLogs\LoginLogResource;
use Filamentboot\Filament\Resources\Media\MediaResource;
use Filamentboot\Filament\Resources\Menus\MenuResource;
use Filamentboot\Filament\Widgets\QuickActionsWidget;
use Filamentboot\Filament\Widgets\QuickGuideWidget;
use Filamentboot\Filament\Widgets\RecentActivityWidget;
use Filamentboot\Filament\Widgets\SystemStatsWidget;
use Filamentboot\Filament\Widgets\WelcomeWidget;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Department;
use Filamentboot\Models\LoginLog;
use Filamentboot\Models\Menu;

/**
 * Filamentboot 插件入口
 *
 * 用户在 AdminPanelProvider 中通过 ->plugins([FilamentbootPlugin::make()]) 注册。
 * 所有可替换的 Model 和 Resource 均提供绑定方法。
 */
class FilamentbootPlugin implements Plugin
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
        return 'filamentboot';
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

        // 用本地首字头像替换官方默认的 UiAvatarsProvider（后者每次渲染都请求 ui-avatars.com）。
        // 用户如需自定义，在 ->plugin() 之后再调一次 ->defaultAvatarProvider() 即可覆盖。
        $panel->defaultAvatarProvider(InitialsAvatarProvider::class);

        // 侧栏宽度 20rem(320px) → 14rem(224px)，对齐国内后台惯用的 208-256px 区间
        // （Filament 把 --sidebar-width 内联写在 <html> 上，CSS 覆盖层改不动，只能走此 API）。
        // 用户如需自定义，在 ->plugin() 之后再调一次 ->sidebarWidth() 即可覆盖。
        $panel->sidebarWidth('14rem');
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
