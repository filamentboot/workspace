<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Revisions\HasRevisions;
use Filamentboot\FilamentbootSite\Cms\Revisions\Revisionable;
use Filamentboot\FilamentbootSite\Database\Factories\SiteCityPageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * 城市页内容模型
 *
 * 一个区划一个页面。**没有 slug 列**——URL 段全部来自 `region`，
 * 见建表迁移的类注释。
 *
 * ## 正文正常是空的
 *
 * ⚠️ `content_zh` 是可选覆写，正常为 NULL。页面主体由模板从 `profile`、
 * 下辖区县、同省城市渲染出来——**不要真的去写三百多篇正文**。
 *
 * ## profile 的字段表在 config 里
 *
 * 本模型只知道 `profile` 是一组键值对，键的含义、标签、单位、类型全在
 * `config('filamentboot-site.city_pages.profile_fields')`。取值请走
 * `Modules\Corporate\Cities\CityProfile`，别直接读数组——那里做了归一与空值过滤。
 *
 * ⚠️ 与 `site_packages.items` 同一个已知限制：**JSON 列里的中文站内搜索搜不到**
 * （Eloquent 存成 `\uXXXX` 转义序列，`LIKE '%中文%'` 命不中）。城市页的检索
 * 靠标题与简介，概况表里的值搜不出来。
 *
 * @property int $id
 * @property string $region_code
 * @property string $title_zh
 * @property string|null $description_zh
 * @property string|null $content_zh
 * @property array<string, string>|null $profile
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property int $sort
 * @property PageStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read SiteRegion|null $region
 */
class SiteCityPage extends Model implements Revisionable
{
    /** @use HasFactory<SiteCityPageFactory> */
    use HasFactory;

    use HasRevisions;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SiteCityPageFactory
    {
        return SiteCityPageFactory::new();
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'profile'      => 'array',
            'sort'         => 'integer',
            'status'       => PageStatus::class,
        ];
    }

    /**
     * 所属区划
     *
     * @return BelongsTo<SiteRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(SiteRegion::class, 'region_code', 'code');
    }

    /**
     * 本页的前台地址
     *
     * **全站唯一一处拼城市页 URL 的地方**：控制器、站点地图、省页的城市列表、
     * llms.txt 都调它。散在各处拼 route() 的话，直辖市那条分支迟早有人漏掉。
     *
     * 挂在省级上的（四个直辖市）走两段 `/city/{省}`，挂在地级上的走三段。
     *
     * @throws RuntimeException 区划缺失、缺 slug 或层级不对
     */
    public function url(): string
    {
        $region = $this->region;

        if ($region === null || ($region->slug ?? '') === '') {
            throw new RuntimeException("城市页 {$this->region_code} 找不到区划或区划没有 slug");
        }

        if ($region->level === SiteRegion::LEVEL_PROVINCE) {
            return route('site.city.province', ['province' => $region->slug]);
        }

        $parent = $region->parent;

        if ($region->level !== SiteRegion::LEVEL_CITY || $parent === null || ($parent->slug ?? '') === '') {
            throw new RuntimeException("城市页 {$this->region_code} 挂在了不能建页的区划上");
        }

        return route('site.city.show', ['province' => $parent->slug, 'city' => $region->slug]);
    }

    /**
     * 进入快照的字段（批次 1.5c）
     *
     * region_code 不进快照：它是页面挂在哪个区划上的身份标识，不是可编辑
     * 的正文内容，回滚不该把一个页面悄悄改挂到另一个城市上。
     *
     * @return list<string>
     */
    public static function revisionTrackedFields(): array
    {
        return [
            'title_zh',
            'description_zh',
            'content_zh',
            'profile',
            'seo_title',
            'seo_description',
            'seo_keywords',
            'status',
            'published_at',
        ];
    }

    /**
     * 回滚时会被恢复的字段（批次 1.5c）
     *
     * @return list<string>
     */
    public static function revisionRestorableFields(): array
    {
        return [
            'title_zh',
            'description_zh',
            'content_zh',
            'profile',
            'seo_title',
            'seo_description',
            'seo_keywords',
        ];
    }

    /**
     * 字段名 → 中文标签（批次 1.5c）
     *
     * @return array<string, string>
     */
    public static function revisionFieldLabels(): array
    {
        return [
            'title_zh'        => '标题',
            'description_zh'  => '简介',
            'content_zh'      => '正文',
            'profile'         => '城市概况',
            'seo_title'       => 'SEO 标题',
            'seo_description' => 'SEO 描述',
            'seo_keywords'    => 'SEO 关键词',
            'status'          => '发布状态',
            'published_at'    => '发布时间',
        ];
    }

    /**
     * 作用域：仅返回已发布内容
     *
     * @param  Builder<SiteCityPage>  $query
     * @return Builder<SiteCityPage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
