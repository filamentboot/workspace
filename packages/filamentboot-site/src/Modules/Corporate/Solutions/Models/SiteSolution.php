<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Revisions\HasRevisions;
use Filamentboot\FilamentbootSite\Cms\Revisions\Revisionable;
use Filamentboot\FilamentbootSite\Concerns\HasCoverImage;
use Filamentboot\FilamentbootSite\Database\Factories\SiteSolutionFactory;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 智能方案内容模型
 *
 * 支持软删除、媒体库（cover）、发布 scope、置顶 scope，
 * 关联多态标签（MorphToMany）。
 *
 * @property int $id
 * @property string $title_zh
 * @property string $slug
 * @property string|null $description_zh
 * @property string|null $content_zh
 * @property string|null $price_range
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property bool $is_featured
 * @property int $sort
 * @property PageStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SiteSolution extends Model implements HasMedia, Revisionable
{
    use HasCoverImage;

    /** @use HasFactory<SiteSolutionFactory> */
    use HasFactory;

    use HasRevisions;
    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SiteSolutionFactory
    {
        return SiteSolutionFactory::new();
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
            'is_featured'  => 'boolean',
            'status'       => PageStatus::class,
        ];
    }

    /**
     * 注册媒体库集合
     *
     * cover：单文件封面图。
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile();
    }

    /**
     * 注册媒体转换尺寸（thumb/card/og）
     *
     * @param  Media|null  $media  触发转换的媒体实例（Spatie 回调签名要求）
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerCoverConversions();
    }

    /**
     * 关联标签（多态正向关系）
     *
     * @return MorphToMany<SiteTag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(SiteTag::class, 'taggable', 'site_taggables', 'taggable_id', 'tag_id');
    }

    /**
     * 进入快照的字段（批次 1.5c）
     *
     * @return list<string>
     */
    public static function revisionTrackedFields(): array
    {
        return [
            'title_zh',
            'slug',
            'description_zh',
            'content_zh',
            'price_range',
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
            'slug',
            'description_zh',
            'content_zh',
            'price_range',
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
            'slug'            => 'URL Slug',
            'description_zh'  => '简介',
            'content_zh'      => '正文',
            'price_range'     => '价格区间',
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
     * @param  Builder<SiteSolution>  $query
     * @return Builder<SiteSolution>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * 作用域：仅返回置顶/精选内容
     *
     * @param  Builder<SiteSolution>  $query
     * @return Builder<SiteSolution>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
