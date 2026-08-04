<?php

namespace Filamentboot\FilamentbootSite\Cms\Models;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Services\GatedAssetRegistry;
use Filamentboot\FilamentbootSite\Database\Factories\SitePageFactory;
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
     * 模型事件：页面变动时清掉可索取资料登记表缓存
     *
     * 登记表由「已发布页面的 gated-download 区块」推导（见 Cms\Services\GatedAssetRegistry），
     * 所以换文件、改标题、发布或下线页面都会让它过期。
     *
     * 不精确清而是整表清：登记表就一个缓存键，一次 forget 的成本可以忽略，
     * 而精确清要判断「这次改动有没有动到 gated-download 区块」——那个判断写错的后果
     * 是「资料下不了」或者更糟的「下线了还能下」，不值得为省一次缓存重建去赌。
     *
     * 照 SiteMenu / SiteMenuItem 对 MenuResolver 的同一套做法：
     * rememberForever + 模型事件失效，不靠 TTL 熬。
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            GatedAssetRegistry::forget();
        });

        static::deleted(function (): void {
            GatedAssetRegistry::forget();
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
