<?php

namespace Filamentboot\FilamentbootSite\Models;

use Filamentboot\FilamentbootSite\Database\Factories\SiteTagFactory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * 内容标签模型（多态标签，自建实现，不依赖 spatie/laravel-tags）
 *
 * 通过 site_taggables 多态中间表关联 SiteCase、SiteSolution、SitePackage、
 * SiteProduct、NewsArticle 五类内容。
 *
 * ⚠️ 反向关系必须与「哪些模型上有 tags() 正向关系」一一对应。资讯这一条
 * 长期是缺的：NewsArticle 上有 tags()，本类却没有对应的 news()，于是
 * 「从标签查内容」这个方向拿不到资讯——而资讯恰恰是打标签最多的一类
 * （58 条关联里占 22 条）。加内容类型时两边都要补。
 *
 * @property int $id
 * @property string $name_zh
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SiteTag extends Model
{
    /** @use HasFactory<SiteTagFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SiteTagFactory
    {
        return SiteTagFactory::new();
    }

    /**
     * 关联的装修案例（多态反向关系）
     *
     * @return MorphToMany<SiteCase, $this>
     */
    public function cases(): MorphToMany
    {
        return $this->morphedByMany(SiteCase::class, 'taggable', 'site_taggables', 'tag_id', 'taggable_id');
    }

    /**
     * 关联的智能方案（多态反向关系）
     *
     * @return MorphToMany<SiteSolution, $this>
     */
    public function solutions(): MorphToMany
    {
        return $this->morphedByMany(SiteSolution::class, 'taggable', 'site_taggables', 'tag_id', 'taggable_id');
    }

    /**
     * 关联的全屋套餐（多态反向关系）
     *
     * @return MorphToMany<SitePackage, $this>
     */
    public function packages(): MorphToMany
    {
        return $this->morphedByMany(SitePackage::class, 'taggable', 'site_taggables', 'tag_id', 'taggable_id');
    }

    /**
     * 关联的智能产品（多态反向关系）
     *
     * @return MorphToMany<SiteProduct, $this>
     */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(SiteProduct::class, 'taggable', 'site_taggables', 'tag_id', 'taggable_id');
    }

    /**
     * 关联的资讯文章（多态反向关系）
     *
     * @return MorphToMany<NewsArticle, $this>
     */
    public function news(): MorphToMany
    {
        return $this->morphedByMany(NewsArticle::class, 'taggable', 'site_taggables', 'tag_id', 'taggable_id');
    }
}
