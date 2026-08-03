<?php

namespace Filamentboot\FilamentbootSite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * URL 重定向模型（#11，供 #18 301 重定向使用）
 *
 * 页面 slug 变更后旧 URL 必须能跳到新地址，否则已被搜索引擎收录的链接
 * 和外部引用一起变成 404。
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property int $hits
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SiteRedirect extends Model
{
    /** @var string */
    protected $table = 'site_redirects';

    /**
     * 可批量赋值的字段白名单
     *
     * @var list<string>
     */
    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
        'hits',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits'        => 'integer',
        ];
    }
}
