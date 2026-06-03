<?php

namespace App\Providers;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        $this->registerApiResponseMacros();
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
