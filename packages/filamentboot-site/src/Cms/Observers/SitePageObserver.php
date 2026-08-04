<?php

namespace Filamentboot\FilamentbootSite\Cms\Observers;

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Models\SitePageRevision;

/**
 * 页面版本快照观察器（#15）
 *
 * 用 Observer 而不是 Filament 的 afterSave 钩子：钩子只覆盖后台表单，
 * Observer 连 seeder、tinker、状态流转 Action 与未来的 API 一起覆盖——
 * 「谁改的都有快照」这条才立得住。
 *
 * created 与 updated 分开处理，不用 saved + wasChanged()：Laravel 的
 * performInsert() 不调 syncChanges()，新建记录在 saved 里 wasChanged() 恒为 false，
 * 那样首版永远没有快照，回滚就回不到最初那一版。
 *
 * 快照不可变（SitePageRevision 只有 created_at），回滚靠「用旧 payload 再存一次」
 * 实现，于是 Observer 自然又写一条新快照——「回滚产生新版本而非删除历史」
 * 这条要求因此是免费的。
 */
class SitePageObserver
{
    /**
     * 进入快照的字段
     *
     * 只收内容与发布相关字段：sort、时间戳一类不进快照，
     * 否则调一次排序就冒出一堆内容完全相同的版本。
     *
     * @var list<string>
     */
    public const TRACKED = [
        'title_zh',
        'title_en',
        'slug',
        'template',
        'content_zh',
        'content_en',
        'blocks',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_og_image',
        'status',
        'published_at',
    ];

    /**
     * 回滚时会被恢复的字段
     *
     * status 与 published_at **不在**其中：回滚一篇已归档页的旧版本
     * 不该把它偷偷重新发布，发布与否始终是当下的独立决定。
     *
     * @var list<string>
     */
    public const RESTORABLE = [
        'title_zh',
        'title_en',
        'slug',
        'template',
        'content_zh',
        'content_en',
        'blocks',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_og_image',
    ];

    /**
     * 新建后写入基线快照
     *
     * 没有基线，第一次编辑后的版本列表里最早那条就是「改完之后」，
     * 想退回原始内容无从可退。
     */
    public function created(SitePage $page): void
    {
        $this->snapshot($page);
    }

    /**
     * 更新后按变更字段决定是否写快照
     */
    public function updated(SitePage $page): void
    {
        if (! $page->wasChanged(self::TRACKED)) {
            return;
        }

        $this->snapshot($page);
    }

    /**
     * 写一条快照并裁剪超出上限的旧版本
     */
    protected function snapshot(SitePage $page): void
    {
        SitePageRevision::create([
            'page_id'    => $page->getKey(),
            'payload'    => static::payloadOf($page),
            'created_by' => auth('admin')->id(),
        ]);

        $this->prune($page);
    }

    /**
     * 提取当前页面的快照 payload
     *
     * status 存 enum 的标量值：payload 是 JSON，存枚举实例会在反序列化后
     * 变成字符串，两次读出来类型不一致。
     *
     * @return array<string, mixed>
     */
    public static function payloadOf(SitePage $page): array
    {
        $payload = [];

        foreach (self::TRACKED as $field) {
            $value = $page->getAttribute($field);

            $payload[$field] = match (true) {
                $value instanceof \BackedEnum        => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default                              => $value,
            };
        }

        return $payload;
    }

    /**
     * 删除超出保留上限的旧快照
     *
     * 不加上限，高频编辑的页面会把 site_page_revisions 撑爆——每次保存都留一份
     * 正文全文，一篇长页面几十 KB，几百次编辑就是几十 MB。
     *
     * 用 whereKey(...) 批量删而不是逐条 delete()：快照不可变、无软删除、
     * 也没有观察器，一条 DELETE 就够。
     */
    protected function prune(SitePage $page): void
    {
        $keep = (int) config('filamentboot-site.revisions_keep', 50);

        if ($keep < 1) {
            return;
        }

        $expiredIds = SitePageRevision::query()
            ->where('page_id', $page->getKey())
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($expiredIds->isNotEmpty()) {
            SitePageRevision::query()->whereKey($expiredIds)->delete();
        }
    }
}
