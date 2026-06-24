<?php

namespace Filamentboot\Models;

use Filamentboot\Database\Factories\LoginLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 登录日志模型
 *
 * 记录每次登录尝试（成功与失败），不含更新时间戳。
 *
 * @property int $id
 * @property int|null $admin_user_id
 * @property string|null $username
 * @property string $status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 */
class LoginLog extends Model
{
    /** @use HasFactory<LoginLogFactory> */
    use HasFactory;

    /**
     * 禁用 updated_at，login_logs 仅有 created_at
     */
    public const UPDATED_AT = null;

    /**
     * 可批量赋值的字段白名单（显式指定，杜绝批量赋值越权写入）
     *
     * 排除：id（主键）、created_at（由框架时间戳管理）。
     *
     * @var list<string>
     */
    protected $fillable = [
        'admin_user_id',
        'username',
        'status',
        'ip_address',
        'user_agent',
        'failure_reason',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * 关联到管理员用户
     *
     * @return BelongsTo<AdminUser, $this>
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
