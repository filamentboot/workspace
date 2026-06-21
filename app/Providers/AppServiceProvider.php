<?php

namespace App\Providers;

use App\Enums\ApiErrorCode;
use App\Policies\PluginPolicy;
use Filamentboot\Models\Plugin;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 注册应用服务
     */
    public function register(): void
    {
        //
    }

    /**
     * 启动应用服务
     *
     * 注意：Observer、Policy、Gate::before 已由 FilamentAdminServiceProvider 注册。
     * 登录日志监听器（LogAdminLogin）由 FilamentAdminServiceProvider 显式注册。
     */
    public function boot(): void
    {
        // 显式注册 PluginPolicy，避免依赖 Laravel 约定自动发现（CR-03 修复）
        // 约定发现在 Model/Policy 迁移命名空间后会静默失效；
        // 若 Gate::guessPolicyNamesUsing() 被 Shield 等覆盖，约定也会失效。
        Gate::policy(Plugin::class, PluginPolicy::class);

        $this->registerApiResponseMacros();
        $this->registerScrambleRouteFilter();
    }

    /**
     * 注册 Scramble API 文档路由过滤回调（FEAT-02 / D-33 / Pitfall 4）
     *
     * 精确过滤：只文档化 api/v1/* 前缀路由，避免将 Filament 内部路由、
     * Sanctum token 管理路由等非预期端点纳入 OpenAPI 文档。
     * 与 config/scramble.php 中的 api_path => 'api/v1' 形成双重保险。
     */
    protected function registerScrambleRouteFilter(): void
    {
        // Scramble（dedoc/scramble）为 dev 依赖；生产环境 --no-dev 部署时类不存在，
        // 跳过注册以避免应用启动失败（演示站 / 生产部署护栏）。
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::configure()->routes(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'));
    }

    /**
     * 注册 API 统一响应 Macro
     */
    protected function registerApiResponseMacros(): void
    {
        Response::macro('api', function (
            mixed $data = null,
            string $message = '操作成功',
            int $status = 200,
        ) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $data,
            ], $status);
        });

        Response::macro('apiError', function (
            ApiErrorCode $errorCode,
            ?string $message = null,
            mixed $data = null,
        ) {
            return response()->json([
                'success'    => false,
                'message'    => $message ?? $errorCode->defaultMessage(),
                'error_code' => $errorCode->value,
                'data'       => $data,
            ], $errorCode->httpStatus());
        });

        Response::macro('apiPaginated', function (
            LengthAwarePaginator $paginator,
            string $message = '获取成功',
        ) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $paginator->items(),
                'meta'    => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last'  => $paginator->url($paginator->lastPage()),
                    'prev'  => $paginator->previousPageUrl(),
                    'next'  => $paginator->nextPageUrl(),
                ],
            ], 200);
        });
    }
}
