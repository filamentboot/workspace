<?php

namespace Filamentboot\FilamentbootSite\Cms\Revisions;

use Filamentboot\FilamentbootSite\Cms\Models\SiteRevision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 内容类型接入版本快照的契约（批次 1.5c，从 SitePage 专属的 SitePageObserver 泛化）
 *
 * 三个静态方法各自返回该内容类型的字段清单，ContentRevisionObserver 与
 * RevisionsRelationManager 只认这三个方法，不认识任何具体模型类名——
 * 新增一种内容类型只需实现本接口 + use HasRevisions，不用改观察器或
 * 关系管理器一个字。
 *
 * revisionPayload()/revisions() 由 HasRevisions trait 提供统一实现，
 * 仍列进接口是为了让类型系统保证：实现本接口的模型真的 use 了那个 trait，
 * 而不是接口方法各自为政地自己实现一遍。
 */
interface Revisionable
{
    /**
     * 进入快照的字段（哪些字段变化会触发新快照）
     *
     * 只收内容与发布相关字段：排序、时间戳一类结构性字段不进快照，
     * 否则调一次排序就冒出一堆内容完全相同的版本。
     *
     * @return list<string>
     */
    public static function revisionTrackedFields(): array;

    /**
     * 回滚时会被恢复的字段
     *
     * 通常是 revisionTrackedFields() 去掉 status 与 published_at：
     * 回滚一条已归档记录的旧版本不该把它偷偷重新发布，发布与否始终是
     * 当下的独立决定。
     *
     * ⚠️ 回滚遍历的是本方法而不是 payload 的键——列删掉了必须同步从这里去掉，
     * 否则会 update() 一个不存在的列，回滚任意一条历史快照当场报 SQL 异常。
     *
     * @return list<string>
     */
    public static function revisionRestorableFields(): array;

    /**
     * 字段名 → 中文标签（版本对比表与变更摘要共用）
     *
     * @return array<string, string>
     */
    public static function revisionFieldLabels(): array;

    /**
     * 提取当前记录的快照 payload
     *
     * @return array<string, mixed>
     */
    public function revisionPayload(): array;

    /**
     * 该记录的全部版本快照，按新到旧排列
     *
     * @return MorphMany<SiteRevision, $this>
     */
    public function revisions(): MorphMany;
}
