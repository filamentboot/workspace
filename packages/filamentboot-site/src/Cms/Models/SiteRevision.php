<?php

namespace Filamentboot\FilamentbootSite\Cms\Models;

use Filamentboot\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 内容版本快照模型（批次 1.5c，从仅服务 SitePage 的 SitePageRevision 泛化）
 *
 * `revisionable_type` + `revisionable_id` 多态关联，服务全部 7 类内容
 * （SitePage 及批次 1.5a 新增状态机的 6 类）。快照一旦写入即不可变，
 * 因此只有 created_at 没有 updated_at。回滚时产生新快照而不是删除历史，
 * 保证审核链路可追溯。
 *
 * @property int $id
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property array<string, mixed> $payload
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Model $revisionable
 * @property-read AdminUser|null $author
 */
class SiteRevision extends Model
{
    /** @var string */
    protected $table = 'site_revisions';

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
