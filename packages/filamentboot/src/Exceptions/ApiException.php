<?php

namespace Filamentboot\Exceptions;

use Filamentboot\Enums\ApiErrorCode;
use RuntimeException;

/**
 * API 业务异常
 *
 * 抛出此异常会被全局异常处理捕获，返回标准 API 错误格式。
 */
class ApiException extends RuntimeException
{
    /**
     * @param  ApiErrorCode  $errorCode  错误码枚举
     * @param  string|null  $message  自定义提示信息（为 null 时使用错误码默认提示）
     * @param  mixed  $data  附加数据（如校验错误详情）
     */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        ?string $message = null,
        public readonly mixed $data = null,
    ) {
        parent::__construct(
            $message ?? $errorCode->defaultMessage(),
            $errorCode->value
        );
    }

    /**
     * 获取 HTTP 状态码
     */
    public function httpStatus(): int
    {
        return $this->errorCode->httpStatus();
    }
}
