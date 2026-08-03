<?php

namespace Filamentboot\FilamentbootSite\Enums;

/**
 * 页面发布状态枚举（#11）
 *
 * 对应 site_pages.status 列存储值，替代原先的 is_published 布尔列。
 *
 * 状态流转：draft → review → scheduled → published → archived
 * - draft      草稿，仅作者可见
 * - review     待审核，编辑提交后等发布者处理
 * - scheduled  定时发布，到 published_at 之后前台自动可见
 * - published  已发布
 * - archived   已归档，前台不可访问但保留内容与历史
 *
 * 定时发布靠 scopePublished() 的查询过滤实现，不引入队列或定时任务：
 * 少一个必须常驻运行的组件，就少一处"忘了起 worker 导致内容不上线"的故障点。
 */
enum PageStatus: string
{
    /** 草稿 */
    case DRAFT = 'draft';

    /** 待审核 */
    case REVIEW = 'review';

    /** 定时发布 */
    case SCHEDULED = 'scheduled';

    /** 已发布 */
    case PUBLISHED = 'published';

    /** 已归档 */
    case ARCHIVED = 'archived';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => '草稿',
            self::REVIEW    => '待审核',
            self::SCHEDULED => '定时发布',
            self::PUBLISHED => '已发布',
            self::ARCHIVED  => '已归档',
        };
    }

    /**
     * 获取后台 badge 配色
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT     => 'gray',
            self::REVIEW    => 'warning',
            self::SCHEDULED => 'info',
            self::PUBLISHED => 'success',
            self::ARCHIVED  => 'danger',
        };
    }

    /**
     * 键值对形式的全部选项（后台 Select / Filter 用）
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
