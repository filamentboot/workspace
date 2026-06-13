<?php

namespace LaravelStack\FilamentAdminSite\Enums;

/**
 * 装修案例风格枚举
 *
 * 对应 site_cases.style 列存储值。
 */
enum CaseStyle: string
{
    /** 现代简约 */
    case MODERN = 'modern';

    /** 中式风格 */
    case CHINESE = 'chinese';

    /** 欧式风格 */
    case EUROPEAN = 'european';

    /** 北欧风格 */
    case NORDIC = 'nordic';

    /** 工业风格 */
    case INDUSTRIAL = 'industrial';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::MODERN     => '现代简约',
            self::CHINESE    => '中式',
            self::EUROPEAN   => '欧式',
            self::NORDIC     => '北欧',
            self::INDUSTRIAL => '工业风',
        };
    }
}
