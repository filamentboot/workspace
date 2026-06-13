<?php

namespace LaravelStack\FilamentAdminSite\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaravelStack\FilamentAdminSite\Database\Factories\SiteProductFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * 智能产品内容模型
 *
 * 支持软删除、媒体库（cover）、is_published 发布布尔 scope、置顶 scope，
 * 关联分类（BelongsTo）与多态标签（MorphToMany）。
 * 产品无 published_at，使用布尔 is_published（RESEARCH Pattern 2）。
 *
 * @property int $id
 * @property string $title_zh
 * @property string|null $title_en
 * @property string $slug
 * @property string|null $description_zh
 * @property string|null $description_en
 * @property float|null $price
 * @property string|null $brand
 * @property int|null $category_id
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property bool $is_featured
 * @property int $sort
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SiteProduct extends Model implements HasMedia
{
    /** @use HasFactory<SiteProductFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     *
     * @return SiteProductFactory
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
            'is_published' => 'boolean',
            'price'        => 'decimal:2',
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
     * 作用域：仅返回已发布产品（is_published = true）
     *
     * @param Builder<SiteProduct> $query
     * @return Builder<SiteProduct>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * 作用域：仅返回置顶/精选产品
     *
     * @param Builder<SiteProduct> $query
     * @return Builder<SiteProduct>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
