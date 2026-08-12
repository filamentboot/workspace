<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Models;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Revisions\HasRevisions;
use Filamentboot\FilamentbootSite\Cms\Revisions\Revisionable;
use Filamentboot\FilamentbootSite\Concerns\HasCoverImage;
use Filamentboot\FilamentbootSite\Database\Factories\NewsArticleFactory;
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
 * 资讯文章模型
 *
 * 支持软删除、媒体库（cover）、发布 scope、置顶 scope，
 * 关联分类（BelongsTo）与多态标签（MorphToMany）。
 *
 * 发布态用 published_at 而非 is_published 布尔（与 SiteCase 一致）：
 * 归档页要按年月分组，布尔字段撑不起来。
 *
 * 放在 Modules/News/ 而非 Models/：目录解耦阶段的目标结构就是按模块分目录，
 * 新模块直接建在目标位置，省掉日后搬迁。
 *
 * @property int $id
 * @property string $title_zh
 * @property string $slug
 * @property string|null $excerpt_zh
 * @property string|null $content_zh
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
class NewsArticle extends Model implements HasMedia, Revisionable
{
    use HasCoverImage;

    /** @use HasFactory<NewsArticleFactory> */
    use HasFactory;

    use HasRevisions;
    use InteractsWithMedia;
    use SoftDeletes;

    /** @var string */
    protected $table = 'site_news_articles';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): NewsArticleFactory
    {
        return NewsArticleFactory::new();
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
     * cover：单文件封面图。正文内嵌图走富文本编辑器自己的上传通道。
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
     * 所属分类
     *
     * @return BelongsTo<NewsCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    /**
     * 关联标签（多态正向关系，与案例/方案/产品共用 site_taggables）
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
            'excerpt_zh',
            'content_zh',
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
            'excerpt_zh',
            'content_zh',
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
            'excerpt_zh'      => '摘要',
            'content_zh'      => '正文',
            'category_id'     => '所属分类',
            'seo_title'       => 'SEO 标题',
            'seo_description' => 'SEO 描述',
            'seo_keywords'    => 'SEO 关键词',
            'status'          => '发布状态',
            'published_at'    => '发布时间',
        ];
    }

    /**
     * 作用域：仅返回已发布文章（published_at 不为 null 且不晚于当前时间）
     *
     * @param  Builder<NewsArticle>  $query
     * @return Builder<NewsArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * 作用域：仅返回置顶/精选文章
     *
     * @param  Builder<NewsArticle>  $query
     * @return Builder<NewsArticle>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
