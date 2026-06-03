<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use FilamentAdmin\Enums\AdminUserStatus;
use FilamentAdmin\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * 管理员 API 认证控制器
 *
 * 提供 Bearer Token 认证的登录、登出和当前用户信息接口。
 */
class AuthController extends Controller
{
    /**
     * 管理员登录
     *
     * 支持账号（account）或邮箱（email）登录，返回 Sanctum Bearer Token。
     *
     * @unauthenticated
     *
     * @group 认证管理
     *
     * @bodyParam account string required 账号或邮箱。Example: admin
     * @bodyParam password string required 密码（最少6位）。Example: password123
     *
     * @response 200 scenario="登录成功" {
     *   "success": true,
     *   "message": "登录成功",
     *   "data": {
     *     "access_token": "1|abc123...",
     *     "token_type": "Bearer",
     *     "expires_at": "2026-06-03T10:00:00+08:00"
     *   }
     * }
     * @response 401 scenario="凭证错误" {
     *   "success": false,
     *   "message": "用户名或密码错误",
     *   "error_code": 2003,
     *   "data": null
     * }
     *
     * @throws ApiException 认证失败时抛出
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credential = $request->input('account');
        $password   = $request->input('password');

        // 支持账号或邮箱登录
        $admin = AdminUser::where('account', $credential)
            ->orWhere('email', $credential)
            ->first();

        if (! $admin || ! Hash::check($password, $admin->password)) {
            throw new ApiException(ApiErrorCode::WRONG_CREDENTIALS);
        }

        if ($admin->status === AdminUserStatus::Disabled) {
            throw new ApiException(ApiErrorCode::ACCOUNT_DISABLED);
        }

        // Token 有效期 24 小时
        $expiresAt   = now()->addDay();
        $tokenResult = $admin->createToken(
            name: 'api-token',
            abilities: ['*'],
            expiresAt: $expiresAt,
        );

        return response()->api(
            data: [
                'access_token' => $tokenResult->plainTextToken,
                'token_type'   => 'Bearer',
                'expires_at'   => $expiresAt->toIso8601String(),
            ],
            message: '登录成功',
        );
    }

    /**
     * 获取当前管理员信息
     *
     * 返回当前已认证的管理员基本信息。
     *
     * @group 认证管理
     *
     * @response 200 scenario="获取成功" {
     *   "success": true,
     *   "message": "获取成功",
     *   "data": {
     *     "id": 1,
     *     "account": "admin",
     *     "email": "admin@example.com",
     *     "nickname": "超级管理员",
     *     "created_at": "2026-01-01T00:00:00+08:00"
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        return response()->api(
            data: [
                'id'         => $admin->id,
                'account'    => $admin->account,
                'email'      => $admin->email,
                'nickname'   => $admin->nickname,
                'created_at' => $admin->created_at?->toIso8601String(),
            ],
            message: '获取成功',
        );
    }

    /**
     * 管理员登出
     *
     * 撤销当前请求使用的 Token，登出后该 Token 立即失效。
     *
     * @group 认证管理
     *
     * @response 200 scenario="登出成功" {
     *   "success": true,
     *   "message": "已退出登录",
     *   "data": null
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->api(message: '已退出登录');
    }
}
