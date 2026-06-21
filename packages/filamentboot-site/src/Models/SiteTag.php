<?php

namespace Filamentboot\FilamentbootSite\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * 内容标签模型（多态标签，自建实现，不依赖 spatie/laravel-tags）
 *
 * 通过 site_taggables 多态中间表关联 SiteCase、SiteSolution、SiteProduct。
 *
 * @property int $id
 * @property string $name_zh
 * @property string|null $name_en
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SiteTag extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

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
     * 关联的智能产品（多态反向关系）
     *
     * @return MorphToMany<SiteProduct, $this>
     */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(SiteProduct::class, 'taggable', 'site_taggables', 'tag_id', 'taggable_id');
    }
}
