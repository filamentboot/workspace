<?php

namespace Filamentboot;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filamentboot\AvatarProviders\InitialsAvatarProvider;
use Filamentboot\Filament\Pages\Marketplace\MarketplacePage;
use Filamentboot\Filament\Pages\Settings\GeneralSettingsPage;
use Filamentboot\Filament\Pages\Settings\LogSettingsPage;
use Filamentboot\Filament\Pages\Settings\SecuritySettingsPage;
use Filamentboot\Filament\Pages\Settings\UploadSettingsPage;
use Filamentboot\Filament\Resources\AdminUsers\AdminUserResource;
use Filamentboot\Filament\Resources\Departments\DepartmentResource;
use Filamentboot\Filament\Resources\LoginLogs\LoginLogResource;
use Filamentboot\Filament\Resources\Media\MediaResource;
use Filamentboot\Filament\Resources\Menus\MenuResource;
use Filamentboot\Filament\Resources\Plugins\PluginResource;
use Filamentboot\Filament\Widgets\QuickActionsWidget;
use Filamentboot\Filament\Widgets\QuickGuideWidget;
use Filamentboot\Filament\Widgets\RecentActivityWidget;
use Filamentboot\Filament\Widgets\SystemStatsWidget;
use Filamentboot\Filament\Widgets\WelcomeWidget;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Department;
use Filamentboot\Models\LoginLog;
use Filamentboot\Models\Menu;
use Filamentboot\Models\Plugin as PluginModel;
use Illuminate\Support\Facades\Cache;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationPlugin;

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
        'plugin'     => PluginResource::class,
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

        // 注册 Settings Pages + 插件市场浏览页
        $panel->pages([
            GeneralSettingsPage::class,
            UploadSettingsPage::class,
            SecuritySettingsPage::class,
            LogSettingsPage::class,
            MarketplacePage::class,
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

        // 2FA / 角色权限 / 操作日志三个面板插件——composer.json 已把它们列为硬依赖，
        // 装了 filamentboot 就一定装了这三个包，所以直接默认挂上，不留可选开关。
        $panel->plugin(
            TwoFactorAuthenticationPlugin::make()
                ->enableTwoFactorAuthentication() // 启用 TOTP 双因素认证（用户可选启用）
                ->addTwoFactorMenuItem()          // 在用户菜单中添加 2FA 管理入口
        );
        $panel->plugin(
            FilamentShieldPlugin::make()
                ->navigationGroup('系统管理')
                ->navigationLabel('角色管理')
        );
        $panel->plugin(
            ActivityLogPlugin::make()
                ->label('操作日志')
                ->pluralLabel('操作日志')
                ->navigationGroup('系统管理')
                ->navigationIcon('heroicon-o-clock')
                ->navigationSort(40)
                ->dashboard(false)
                ->autoContextTracking()
        );

        // 按 DB plugins.is_enabled 状态动态挂载第三方插件
        $this->registerEnabledPlugins($panel);
    }

    /**
     * 按 DB plugins.is_enabled 状态动态注册第三方插件
     *
     * 必须加 Cache::remember（TTL=30s）防每请求查库（RESEARCH Pitfall 1）。
     * 必须加 try/catch 防 plugins 表首次 migrate 前不存在（RESEARCH Pitfall 1）。
     */
    private function registerEnabledPlugins(Panel $panel): void
    {
        try {
            /** @var array<int, string> $classes */
            $classes = Cache::remember(
                'plugins.enabled_list',
                30,
                fn () => PluginModel::query()
                    ->where('is_enabled', true)
                    ->whereNotNull('plugin_class')
                    ->pluck('plugin_class')
                    ->all()
            );

            foreach ($classes as $class) {
                if (class_exists($class)) {
                    $panel->plugin(app($class));
                }
            }
        } catch (\Throwable) {
            // plugins 表首次 migrate 前不存在，静默跳过
        }
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
