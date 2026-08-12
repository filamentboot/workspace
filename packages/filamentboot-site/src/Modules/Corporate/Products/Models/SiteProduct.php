<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Revisions\HasRevisions;
use Filamentboot\FilamentbootSite\Cms\Revisions\Revisionable;
use Filamentboot\FilamentbootSite\Concerns\HasCoverImage;
use Filamentboot\FilamentbootSite\Database\Factories\SiteProductFactory;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * 智能产品内容模型
 *
 * 支持软删除、媒体库（cover/gallery）、published_at 发布 scope、置顶 scope，
 * 关联分类（BelongsTo）与多态标签（MorphToMany）。
 *
 * @property int $id
 * @property string $title_zh
 * @property string $slug
 * @property string|null $description_zh
 * @property string|null $content_zh
 * @property float|null $price
 * @property string|null $brand
 * @property int|null $category_id
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
class SiteProduct extends Model implements HasMedia, Revisionable
{
    use HasCoverImage;

    /** @use HasFactory<SiteProductFactory> */
    use HasFactory;

    use HasRevisions;
    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SiteProductFactory
    {
        return SiteProductFactory::new();
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured'  => 'boolean',
            'published_at' => 'datetime',
            'price'        => 'decimal:2',
            'status'       => PageStatus::class,
        ];
    }

    /**
     * 注册媒体库集合
     *
     * cover：单文件封面图；gallery：多文件图集（详情页主图轮播）。
     *
     * gallery 的读取与转换由 HasCoverImage 统一提供（galleryUrls()，
     * 且 thumb/card 两档转换已 performOnCollections('cover','gallery')），
     * 此处只需注册集合本身。
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile();

        $this->addMediaCollection('gallery');
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
     * 所属分类
     *
     * @return BelongsTo<SiteProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SiteProductCategory::class, 'category_id');
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
            'price',
            'brand',
            'category_id',
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
            'price',
            'brand',
            'category_id',
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
            'price'           => '价格',
            'brand'           => '品牌',
            'category_id'     => '所属分类',
            'seo_title'       => 'SEO 标题',
            'seo_description' => 'SEO 描述',
            'seo_keywords'    => 'SEO 关键词',
            'status'          => '发布状态',
            'published_at'    => '发布时间',
        ];
    }

    /**
     * 作用域：仅返回已发布产品（published_at 非空且不晚于当前时间）
     *
     * @param  Builder<SiteProduct>  $query
     * @return Builder<SiteProduct>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * 作用域：仅返回置顶/精选产品
     *
     * @param  Builder<SiteProduct>  $query
     * @return Builder<SiteProduct>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
