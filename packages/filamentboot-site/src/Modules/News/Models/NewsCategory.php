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
 * @property string|null $name_en
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
}
