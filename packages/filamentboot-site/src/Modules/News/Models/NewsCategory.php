<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Models;

use Filamentboot\FilamentbootSite\Database\Factories\NewsCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 资讯分类模型
 *
 * 结构与案例分类、产品分类一致，首版扁平，parent_id 预留。
 *
 * @property int $id
 * @property string $name_zh
 * @property string $slug
 * @property int|null $parent_id
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NewsCategory extends Model
{
    /** @use HasFactory<NewsCategoryFactory> */
    use HasFactory;

    /** @var string */
    protected $table = 'site_news_categories';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): NewsCategoryFactory
    {
        return NewsCategoryFactory::new();
    }

    /**
     * 该分类下的资讯文章
     *
     * @return HasMany<NewsArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'category_id');
    }

    /**
     * 已发布的文章
     *
     * 存在的理由是给 withCount 用。写成 withCount(['articles' => fn (Builder $q) => $q->published()])
     * 时闭包参数只能被推成 Builder<Model>，PHPStan 看不见 published() 这个作用域；
     * 而把判据在闭包里重写一遍，等于让「已发布」在两处各有一份定义。
     * 收进一个具体到模型的关系方法，两个问题一起没有。
     *
     * @return HasMany<NewsArticle, $this>
     */
    public function publishedArticles(): HasMany
    {
        return $this->articles()->published();
    }
}
