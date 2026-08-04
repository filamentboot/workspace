<?php

namespace Filamentboot\FilamentbootSite\Models;

use Filamentboot\FilamentbootSite\Database\Factories\SitePageFactory;
use Filamentboot\FilamentbootSite\Enums\PageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * CMS 页面内容模型
 *
 * 支持软删除，发布状态由 PageStatus 状态机管理（#11）：
 * draft → review → scheduled → published → archived。
 *
 * 定时发布不靠队列或定时任务，而是由 scopePublished() 的查询过滤实现——
 * 少一个必须常驻运行的组件，就少一处「忘了起 worker 导致内容不上线」的故障点。
 *
 * is_published 是被 status 取代的旧列，保留一个版本供下游回滚，
 * 由 saving 钩子镜像为 status 的派生值，计划在阶段 4 随包重命名一起删除。
 *
 * @property int $id
 * @property string $title_zh
 * @property string|null $title_en
 * @property string $slug
 * @property string $template
 * @property string|null $content_zh
 * @property string|null $content_en
 * @property array<int, mixed>|null $blocks 区块 payload 列表 [{type, data}, ...]（Filament Builder 存的就是列表）
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property string|null $seo_og_image
 * @property int $sort
 * @property PageStatus $status
 * @property Carbon|null $published_at
 * @property bool $is_published 旧列，由 status 派生，阶段 4 删除
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SitePage extends Model
{
    /** @use HasFactory<SitePageFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SitePageFactory
    {
        return SitePageFactory::new();
    }

    /**
     * 模型事件注册
     *
     * saving 钩子把 is_published 镜像为 status 的派生值。旧列在保留期内不再被
     * 任何读路径使用，但让它与 status 一致可避免下游（或回滚后的旧代码）
     * 读到一个早已停止更新的布尔值。
     */
    protected static function booted(): void
    {
        static::saving(function (self $page): void {
            $page->is_published = $page->status === PageStatus::PUBLISHED;
        });
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks'       => 'array',
            'status'       => PageStatus::class,
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * 版本快照（#15 版本回滚使用）
     *
     * @return HasMany<SitePageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(SitePageRevision::class, 'page_id')->latest('id');
    }

    /**
     * 作用域：仅返回对公众可见的页面
     *
     * 已发布 且（无发布时间 或 发布时间已到）。后半段即定时发布：
     * status=scheduled 且 published_at 在未来的页面不满足 status=published，
     * 天然被排除；已发布但 published_at 被设成未来的页面同样不可见。
     *
     * 该 scope 是前台唯一的可见性判断入口，SiteFrontController::page() 与
     * SitemapController 都走它，因此 draft / review / scheduled（未到期）/ archived
     * 四态自动既不可访问也不进站点地图。
     *
     * @param  Builder<SitePage>  $query
     * @return Builder<SitePage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::PUBLISHED)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }
}
