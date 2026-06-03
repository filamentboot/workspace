<?php

namespace App\Enums;

/**
 * API 错误码枚举
 *
 * 错误码分段：
 * - 1xxx: 通用错误
 * - 2xxx: 认证与授权错误
 */
enum ApiErrorCode: int
{
    /** 服务器内部错误 */
    case SERVER_ERROR = 1000;

    /** 请求参数校验失败 */
    case VALIDATION_FAILED = 1001;

    /** 未找到请求的资源 */
    case NOT_FOUND = 1002;

    /** 请求方法不允许 */
    case METHOD_NOT_ALLOWED = 1003;

    /** 请求过于频繁（限流） */
    case TOO_MANY_REQUESTS = 1004;

    /** 未提供认证凭证 */
    case UNAUTHENTICATED = 2001;

    /** 认证凭证无效或已过期 */
    case INVALID_TOKEN = 2002;

    /** 用户名或密码错误 */
    case WRONG_CREDENTIALS = 2003;

    /** 账号已被禁用 */
    case ACCOUNT_DISABLED = 2004;

    /** 无权访问该资源 */
    case FORBIDDEN = 2005;

    /**
     * 获取对应的 HTTP 状态码
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::SERVER_ERROR       => 500,
            self::VALIDATION_FAILED  => 422,
            self::NOT_FOUND          => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::TOO_MANY_REQUESTS  => 429,
            self::UNAUTHENTICATED    => 401,
            self::INVALID_TOKEN      => 401,
            self::WRONG_CREDENTIALS  => 401,
            self::ACCOUNT_DISABLED   => 403,
            self::FORBIDDEN          => 403,
        };
    }

    /**
     * 获取错误码的默认提示信息
     */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::SERVER_ERROR       => '服务器内部错误',
            self::VALIDATION_FAILED  => '请求参数校验失败',
            self::NOT_FOUND          => '请求的资源不存在',
            self::METHOD_NOT_ALLOWED => '不支持该请求方法',
            self::TOO_MANY_REQUESTS  => '请求过于频繁，请稍后再试',
            self::UNAUTHENTICATED    => '请先登录',
            self::INVALID_TOKEN      => '认证凭证无效或已过期',
            self::WRONG_CREDENTIALS  => '用户名或密码错误',
            self::ACCOUNT_DISABLED   => '账号已被禁用',
            self::FORBIDDEN          => '无权访问该资源',
        };
    }
}
