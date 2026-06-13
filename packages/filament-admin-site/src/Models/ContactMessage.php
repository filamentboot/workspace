<?php

namespace LaravelStack\FilamentAdminSite\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LaravelStack\FilamentAdminSite\Enums\ContactMessageStatus;

/**
 * 访客询盘消息模型
 *
 * 极简模型（D-10-15）：无软删除，status 使用 ContactMessageStatus 枚举 cast。
 * 状态流转：unread → contacted → closed。
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $message
 * @property ContactMessageStatus $status
 * @property string|null $ip
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ContactMessage extends Model
{
    use HasFactory;

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
            'status' => ContactMessageStatus::class,
        ];
    }
}
