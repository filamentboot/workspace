<?php

namespace Filamentboot\FilamentbootSite\Cms\Enums;

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
     * 本状态允许直接转移到的目标状态（#14）
     *
     * 转移规则写在枚举上而不是 Filament Action 里，这样状态机能脱离 Filament
     * 单测，且 seeder / tinker / 未来的 API 走同一套判据。
     *
     * 几条不明显的边：
     * - draft 可直接到 published：小站没有强制审核流程的必要，
     *   「只能提交审核」这条约束由 publish_site_page 权限点管，不由状态机管。
     * - scheduled 不能回 review：定时发布是审核通过后的排期动作，
     *   要改内容应先退回草稿。
     * - published 不能直接到 scheduled：给已发布页面排个未来时间等于悄悄下线它，
     *   要下线就走 archived。
     * - archived 只能回 draft：归档页重新上线必须过一遍编辑，不能一键复活。
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT     => [self::REVIEW, self::SCHEDULED, self::PUBLISHED],
            self::REVIEW    => [self::DRAFT, self::SCHEDULED, self::PUBLISHED],
            self::SCHEDULED => [self::DRAFT, self::PUBLISHED],
            self::PUBLISHED => [self::DRAFT, self::ARCHIVED],
            self::ARCHIVED  => [self::DRAFT],
        };
    }

    /**
     * 能否从本状态转移到目标状态
     *
     * 同状态返回 false：转移到自己不是一个动作，后台 Action 据此隐藏，
     * 避免出现「当前已是草稿」却还显示「退回草稿」按钮。
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
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
