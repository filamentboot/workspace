<?php

namespace Filamentboot\FilamentbootSite\Models;

use Filamentboot\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 页面版本快照模型（#11，供 #15 版本回滚使用）
 *
 * 快照一旦写入即不可变，因此只有 created_at 没有 updated_at。
 * 回滚时产生新快照而不是删除历史，保证审核链路可追溯。
 *
 * @property int $id
 * @property int $page_id
 * @property array<string, mixed> $payload
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property SitePage $page
 * @property AdminUser|null $author
 */
class SitePageRevision extends Model
{
    /** @var string */
    protected $table = 'site_page_revisions';

    /**
     * 快照不可变，无 updated_at 列
     */
    public const UPDATED_AT = null;

    /**
     * 可批量赋值的字段白名单
     *
     * @var list<string>
     */
    protected $fillable = [
        'page_id',
        'payload',
        'created_by',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * 所属页面
     *
     * @return BelongsTo<SitePage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'page_id');
    }

    /**
     * 操作人
     *
     * @return BelongsTo<AdminUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
