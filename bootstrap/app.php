<?php

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Middleware\ResetAuthGuards;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', ResetAuthGuards::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 捕获 ApiException，返回标准错误格式
        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->apiError(
                    errorCode: $e->errorCode,
                    message: $e->getMessage(),
                    data: $e->data,
                );
            }
        });

        // 捕获 ValidationException（Laravel 表单校验失败）
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->apiError(
                    errorCode: ApiErrorCode::VALIDATION_FAILED,
                    message: $e->getMessage(),
                    data: $e->errors(),
                );
            }
        });

        // 捕获未认证异常
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->apiError(
                    errorCode: ApiErrorCode::UNAUTHENTICATED,
                );
            }
        });

        // 捕获未预期的服务器异常
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->apiError(
                    errorCode: ApiErrorCode::SERVER_ERROR,
                    message: app()->isProduction()
                        ? ApiErrorCode::SERVER_ERROR->defaultMessage()
                        : $e->getMessage(),
                );
            }
        });
    })->create();
