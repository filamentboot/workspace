<?php

namespace Filamentboot\FilamentbootSite\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filamentboot\FilamentbootSite\Database\Factories\SiteCaseFactory;
use Filamentboot\FilamentbootSite\Enums\CaseStyle;
use Filamentboot\FilamentbootSite\Enums\HouseType;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * 装修案例内容模型
 *
 * 支持软删除、媒体库（cover/gallery）、发布 scope、置顶 scope，
 * 关联分类（BelongsTo）与多态标签（MorphToMany）。
 *
 * @property int $id
 * @property string $title_zh
 * @property string|null $title_en
 * @property string $slug
 * @property CaseStyle|null $style
 * @property HouseType|null $house_type
 * @property string|null $area
 * @property string|null $budget_range
 * @property string|null $smart_features
 * @property string|null $description_zh
 * @property string|null $description_en
 * @property string|null $content_zh
 * @property string|null $content_en
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property int|null $category_id
 * @property array<int, mixed>|null $gallery
 * @property bool $is_featured
 * @property int $sort
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SiteCase extends Model implements HasMedia
{
    /** @use HasFactory<SiteCaseFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     *
     * @return SiteCaseFactory
     */
    protected static function newFactory(): SiteCaseFactory
    {
        return SiteCaseFactory::new();
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery'      => 'array',
            'published_at' => 'datetime',
            'is_featured'  => 'boolean',
            'style'        => CaseStyle::class,
            'house_type'   => HouseType::class,
        ];
    }

    /**
     * 注册媒体库集合
     *
     * cover：单文件封面图；gallery：多文件图集。
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    /**
     * 所属分类
     *
     * @return BelongsTo<SiteCaseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SiteCaseCategory::class, 'category_id');
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
     * 作用域：仅返回已发布内容（published_at 不为 null 且不晚于当前时间）
     *
     * @param Builder<SiteCase> $query
     * @return Builder<SiteCase>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * 作用域：仅返回置顶/精选内容
     *
     * @param Builder<SiteCase> $query
     * @return Builder<SiteCase>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
