<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models;

use Filamentboot\FilamentbootSite\Database\Factories\SiteCaseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 装修案例分类模型
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
class SiteCaseCategory extends Model
{
    /** @use HasFactory<SiteCaseCategoryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
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
