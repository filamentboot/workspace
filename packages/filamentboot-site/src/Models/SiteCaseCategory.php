<?php

namespace Filamentboot\FilamentbootSite\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filamentboot\FilamentbootSite\Database\Factories\SiteCaseCategoryFactory;

/**
 * 装修案例分类模型
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
class SiteCaseCategory extends Model
{
    /** @use HasFactory<SiteCaseCategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     *
     * @return SiteCaseCategoryFactory
     */
    protected static function newFactory(): SiteCaseCategoryFactory
    {
        return SiteCaseCategoryFactory::new();
    }

    /**
     * 该分类下的装修案例
     *
     * @return HasMany<SiteCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(SiteCase::class, 'category_id');
    }
}
