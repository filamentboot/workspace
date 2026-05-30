<?php

namespace App\Providers;

use App\Models\AdminUser;
use App\Models\Department;
use App\Models\Menu;
use App\Models\RoleDataScope;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * 注意：登录日志监听器（LogAdminLogin）通过 Laravel 自动发现机制注册，
     * 无需在此处手动 Event::listen。
     */
    public function boot(): void
    {
        AdminUser::observe(ActivityLogObserver::class);
        Department::observe(ActivityLogObserver::class);
        Menu::observe(ActivityLogObserver::class);
        RoleDataScope::observe(ActivityLogObserver::class);
        Role::observe(ActivityLogObserver::class);
    }
}
