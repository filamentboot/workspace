<?php

namespace Filamentboot\FilamentbootSite\Cms\Observers;

use Filamentboot\FilamentbootSite\Cms\Models\SiteRevision;
use Filamentboot\FilamentbootSite\Cms\Revisions\Revisionable;
use Illuminate\Database\Eloquent\Model;

/**
 * 内容版本快照观察器（批次 1.5c，从 SitePage 专属的 SitePageObserver 泛化）
 *
 * 与 SearchPushObserver 同一个模式：一个观察器循环注册给全部 7 类内容
 * （见 SiteServiceProvider::registerContentRevisionObserver()）。本类
 * 不认识任何具体模型类名，字段清单全部来自 Revisionable 接口。
 *
 * created 与 updated 分开处理，不用 saved + wasChanged()：Laravel 的
 * performInsert() 不调 syncChanges()，新建记录在 saved 里 wasChanged() 恒为 false，
 * 那样首版永远没有快照，回滚就回不到最初那一版。
 *
 * 快照不可变（SiteRevision 只有 created_at），回滚靠「用旧 payload 再存一次」
 * 实现，于是本观察器自然又写一条新快照——「回滚产生新版本而非删除历史」
 * 这条要求因此是免费的。
 */
class ContentRevisionObserver
{
    /**
     * 新建后写入基线快照
     *
     * 没有基线，第一次编辑后的版本列表里最早那条就是「改完之后」，
     * 想退回原始内容无从可退。
     */
    public function created(Model&Revisionable $model): void
    {
        $this->snapshot($model);
    }

    /**
     * 更新后按变更字段决定是否写快照
     */
    public function updated(Model&Revisionable $model): void
    {
        if (! $model->wasChanged($model::revisionTrackedFields())) {
            return;
        }

        $this->snapshot($model);
    }

    /**
     * 硬删除时清掉该记录全部快照
     *
     * 旧的 site_page_revisions 靠数据库外键 cascadeOnDelete() 免费获得这个行为；
     * 多态列没有外键可用，必须在这里手动做。软删除不清（内容还能恢复），
     * 只有彻底删除才级联。
     */
    public function forceDeleted(Model&Revisionable $model): void
    {
        SiteRevision::query()
            ->where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->delete();
    }

    /**
     * 写一条快照并裁剪超出上限的旧版本
     */
    protected function snapshot(Model&Revisionable $model): void
    {
        SiteRevision::create([
            'revisionable_type' => $model->getMorphClass(),
            'revisionable_id'   => $model->getKey(),
            'payload'           => $model->revisionPayload(),
            'created_by'        => auth('admin')->id(),
        ]);

        $this->prune($model);
    }

    /**
     * 删除超出保留上限的旧快照
     *
     * 不加上限，高频编辑的记录会把 site_revisions 撑爆——每次保存都留一份
     * 正文全文，几百次编辑就是几十 MB。
     *
     * 用 whereKey(...) 批量删而不是逐条 delete()：快照不可变、无软删除、
     * 也没有观察器，一条 DELETE 就够。
     */
    protected function prune(Model&Revisionable $model): void
    {
        $keep = (int) config('filamentboot-site.revisions_keep', 50);

        if ($keep < 1) {
            return;
        }

        $expiredIds = SiteRevision::query()
            ->where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($expiredIds->isNotEmpty()) {
            SiteRevision::query()->whereKey($expiredIds)->delete();
        }
    }
}
