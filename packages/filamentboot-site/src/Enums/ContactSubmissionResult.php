<?php

namespace Filamentboot\FilamentbootSite\Enums;

/**
 * 询盘提交结果（#29）
 *
 * 校验失败不在这里——那走 ValidationException，两个调用方（无状态端点与 Livewire 组件）
 * 都有各自的原生处理方式。本枚举只区分「过了校验之后」的三种归宿。
 */
enum ContactSubmissionResult: string
{
    /**
     * 正常入库
     */
    case CREATED = 'created';

    /**
     * 判为机器人，静默丢弃
     *
     * 对外必须回成功：回错误等于在教脚本怎么绕过，回成功则让它以为得手、不再换策略重试。
     */
    case DISCARDED = 'discarded';

    /**
     * 触发 IP 限流
     */
    case THROTTLED = 'throttled';
}
