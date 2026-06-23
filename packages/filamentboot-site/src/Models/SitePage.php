<?php

namespace Filamentboot\FilamentbootSite\Models;

use Filamentboot\FilamentbootSite\Database\Factories\SitePageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 静态页面内容模型
 *
 * 支持软删除，is_published 发布布尔 scope。
 * 静态页面无媒体库（无封面图，D-10-16 简化）。
 *
 * @property int $id
 * @property string $title_zh
 * @property string|null $title_en
 * @property string $slug
 * @property string|null $content_zh
 * @property string|null $content_en
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_keywords
 * @property int $sort
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class SitePage extends Model
{
    /** @use HasFactory<SitePageFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): SitePageFactory
    {
        return SitePageFactory::new();
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * 作用域：仅返回已发布页面
     *
     * @param  Builder<SitePage>  $query
     * @return Builder<SitePage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
