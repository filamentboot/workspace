<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        //
    }
}
