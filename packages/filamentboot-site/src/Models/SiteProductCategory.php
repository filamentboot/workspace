<?php

namespace Filamentboot\FilamentbootSite\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filamentboot\FilamentbootSite\Database\Factories\SiteProductCategoryFactory;

/**
 * 智能产品分类模型
 *
 * @property int $id
 * @property string $name_zh
 * @property string|null $name_en
 * @property string $slug
 * @property int|null $parent_id
 * @property int $sort
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SiteProductCategory extends Model
{
    /** @use HasFactory<SiteProductCategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     *
     * @return SiteProductCategoryFactory
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
}
