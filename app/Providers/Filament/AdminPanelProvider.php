<?php

namespace App\Providers\Filament;

use AlizHarb\ActivityLog\ActivityLogPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
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
use FilamentAdmin\Filament\Pages\Auth\Login;
use FilamentAdmin\Filament\Pages\Profile;
use FilamentAdmin\FilamentAdminPlugin;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Services\AdminNavigationBuilder;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationPlugin;

/**
 * 管理员面板服务提供者
 *
 * 配置 Filament 管理员面板，使用 FilamentAdminPlugin 注册所有 Resources、Pages、Widgets。
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
            ->plugin(FilamentAdminPlugin::make())
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
            ->navigation(function (AdminNavigationBuilder $builder): NavigationBuilder {
                $user = Filament::auth()->user();

                return (new NavigationBuilder)
                    ->groups($builder->build($user instanceof AdminUser ? $user : null));
            })
            ->pages([
                Dashboard::class,
            ])
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
            ]);
    }
}
