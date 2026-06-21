<?php

namespace Filamentboot\FilamentbootSite\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filamentboot\FilamentbootSite\Database\Factories\SiteSolutionFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * 智能方案内容模型
 *
 * 支持软删除、媒体库（cover）、发布 scope、置顶 scope，
 * 关联多态标签（MorphToMany）。
 *
 * @property int $id
 * @property string $title_zh
 * @property string|null $title_en
 * @property string $slug
 * @property string|null $description_zh
 * @property string|null $description_en
 * @property string|null $content_zh
 * @property string|null $content_en
 * @property string|null $price_range
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property bool $is_featured
 * @property int $sort
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class SiteSolution extends Model implements HasMedia
{
    /** @use HasFactory<SiteSolutionFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     *
     * @return SiteSolutionFactory
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
     * 关联标签（多态正向关系）
     *
     * @return MorphToMany<SiteTag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(SiteTag::class, 'taggable', 'site_taggables', 'taggable_id', 'tag_id');
    }

    /**
     * 作用域：仅返回已发布内容
     *
     * @param Builder<SiteSolution> $query
     * @return Builder<SiteSolution>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * 作用域：仅返回置顶/精选内容
     *
     * @param Builder<SiteSolution> $query
     * @return Builder<SiteSolution>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
