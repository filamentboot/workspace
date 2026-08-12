<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models;

use Filamentboot\FilamentbootSite\Database\Factories\SiteProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 智能产品分类模型
 *
 * @property int $id
 * @property string $name_zh
 * @property string $slug
 * @property int|null $parent_id
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SiteProductCategory extends Model
{
    /** @use HasFactory<SiteProductCategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SiteProductCategoryFactory
    {
        return SiteProductCategoryFactory::new();
    }

    /**
     * 该分类下的智能产品
     *
     * @return HasMany<SiteProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(SiteProduct::class, 'category_id');
    }

    /**
     * 已发布的产品
     *
     * 存在的理由是给 withCount 用（与 NewsCategory::publishedArticles() 同理）：
     * withCount(['products' => fn (Builder $q) => $q->published()]) 里闭包参数只能被
     * 推成 Builder<Model>，PHPStan 看不见 published() 这个作用域。
     *
     * @return HasMany<SiteProduct, $this>
     */
    public function publishedProducts(): HasMany
    {
        return $this->products()->published();
    }
}
