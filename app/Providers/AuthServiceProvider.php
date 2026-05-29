<?php

namespace App\Providers;

use App\Models\AdminUser;
use App\Models\LoginLog;
use App\Policies\AdminUserPolicy;
use App\Policies\LoginLogPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * 授权服务提供者
 *
 * Laravel 11+ 已移除框架自带的 AuthServiceProvider 基类，
 * 这里直接继承 ServiceProvider，在 boot() 中手动注册 Policy 与 Gate::before。
 *
 * Policy 映射会在 boot() 中通过 Gate::policy() 注册。
 * 超级管理员通过 Gate::before() 绕过所有权限检查。
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Policy 映射表
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        AdminUser::class => AdminUserPolicy::class,
        LoginLog::class  => LoginLogPolicy::class,
    ];

    public function boot(): void
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
}
