<?php

namespace Filamentboot\FilamentbootSite\Models;

use Filamentboot\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 询盘跟进备注模型（A4）
 *
 * 构成后台询盘详情页的跟进时间线，一条询盘多条备注。
 *
 * @property int $id
 * @property int $message_id
 * @property int|null $admin_user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property ContactMessage $message
 * @property AdminUser|null $author
 */
class ContactMessageNote extends Model
{
    /** @var string */
    protected $table = 'site_contact_message_notes';

    /**
     * 可批量赋值的字段白名单
     *
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'admin_user_id',
        'body',
    ];

    /**
     * 所属询盘
     *
     * @return BelongsTo<ContactMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'message_id');
    }

    /**
     * 记录人
     *
     * @return BelongsTo<AdminUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
