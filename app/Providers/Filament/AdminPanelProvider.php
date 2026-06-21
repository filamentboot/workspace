<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use App\Filament\Pages\Marketplace\MarketplacePage;
use App\Filament\Resources\PluginResource;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filamentboot\Models\Plugin;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filamentboot\Filament\Pages\Auth\Login;
use Filamentboot\Filament\Pages\Profile;
use Filamentboot\FilamentbootPlugin;
use Filamentboot\Http\Middleware\EnsureTwoFactorEnabled;
use Filamentboot\Models\AdminUser;
use Filamentboot\Services\AdminNavigationBuilder;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationPlugin;

/**
 * 管理员面板服务提供者
 *
 * 配置 Filament 管理员面板，使用 FilamentbootPlugin 注册所有 Resources、Pages、Widgets。
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)           // 使用自定义登录页（支持 account/email）
            ->profile(Profile::class)       // 使用自定义个人资料页
            ->authGuard('admin')            // 使用 admin guard
            ->authPasswordBroker('admin_users')
            ->passwordReset()               // 启用密码重置（使用 admin_users broker，框架默认 token/限流/邮件）
            ->plugin(FilamentbootPlugin::make())
            ->plugin(
                TwoFactorAuthenticationPlugin::make()
                    ->enableTwoFactorAuthentication() // 启用 TOTP 双因素认证（用户可选启用）
                    ->addTwoFactorMenuItem()          // 在用户菜单中添加 2FA 管理入口
            )
            ->plugin(
                FilamentShieldPlugin::make()
                    ->navigationGroup('系统管理')
                    ->navigationLabel('角色管理')
            )
            ->plugin(
                ActivityLogPlugin::make()
                    ->label('操作日志')
                    ->pluralLabel('操作日志')
                    ->navigationGroup('系统管理')
                    ->navigationIcon('heroicon-o-clock')
                    ->navigationSort(40)
                    ->dashboard(false)
                    ->autoContextTracking()
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigation(function (AdminNavigationBuilder $builder): NavigationBuilder {
                $user = Filament::auth()->user();

                return (new NavigationBuilder)
                    ->groups($builder->build($user instanceof AdminUser ? $user : null));
            })
            ->resources([
                PluginResource::class,  // 显式注册插件资源（修复 SC-1 路由缺失）
            ])
            ->pages([
                Dashboard::class,
                MarketplacePage::class,
            ])
            ->tap(fn (Panel $panel) => $this->registerEnabledPlugins($panel))
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureTwoFactorEnabled::class, // POLISH-02：强制 2FA 拦截（Authenticate 后执行，确保有用户）
            ]);
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
                fn () => Plugin::query()
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
}
