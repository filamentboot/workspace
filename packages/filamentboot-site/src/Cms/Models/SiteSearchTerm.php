<?php

namespace Filamentboot\FilamentbootSite\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 站内搜索词统计
 *
 * 按词聚合的一行一词，不是逐次流水——设计理由见建表迁移。
 * 只由 Cms\Services\SearchTermLog 写入，后台只读。
 *
 * @property int $id
 * @property string $term
 * @property int $hits
 * @property int $last_result_count
 * @property Carbon|null $last_searched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SiteSearchTerm extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits'              => 'integer',
            'last_result_count' => 'integer',
            'last_searched_at'  => 'datetime',
        ];
    }

    /**
     * 作用域：最近一次搜索没有任何结果的词
     *
     * 这是本表最该被看的一档数据——**每一条都是一个内容缺口**，
     * 访客明确表达了需求而站上答不上来。
     *
     * @param  Builder<SiteSearchTerm>  $query
     * @return Builder<SiteSearchTerm>
     */
    public function scopeWithoutResults(Builder $query): Builder
    {
        return $query->where('last_result_count', 0);
    }
}
