<?php

namespace Filamentboot\FilamentbootSite\Models;

use Filamentboot\FilamentbootSite\Database\Factories\ContactMessageFactory;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property array<int, mixed>|null $extra 自定义字段答案，有序列表 [{label, value}]
 *
 * extra 的类型刻意写宽（同 SitePage::$blocks）：写入侧的精确形状由
 * Services\ContactSubmission::extraAnswers() 的返回类型保证，但读取侧拿到的是
 * JSON 列的实际内容——seeder、tinker 与历史行都不受那条写入路径约束，
 * 所以消费方（后台展示、导出、通知邮件）必须自己判形状。
 * @property ContactMessageStatus $status
 * @property int|null $assigned_to
 * @property string|null $ip
 * @property string|null $source
 * @property string|null $landing_url
 * @property string|null $referer
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /** @var string */
    protected $table = 'site_contact_messages';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 解析对应的工厂（因命名空间非 Laravel 默认推导路径）
     */
    protected static function newFactory(): ContactMessageFactory
    {
        return ContactMessageFactory::new();
    }

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'extra'  => 'array',
        ];
    }

    /**
     * 跟进人（A4）
     *
     * @return BelongsTo<AdminUser, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_to');
    }

    /**
     * 跟进备注时间线（A4）
     *
     * @return HasMany<ContactMessageNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ContactMessageNote::class, 'message_id')->latest('id');
    }

    /**
     * 转化入口的中文名
     *
     * 未在 config('filamentboot-site.contact.sources') 登记的来源回落原始 key，
     * 保证新增 CTA 未同步配置时后台仍能看到来源而不是空白。
     */
    public function sourceLabel(): ?string
    {
        if ($this->source === null || $this->source === '') {
            return null;
        }

        /** @var array<string, string> $labels */
        $labels = config('filamentboot-site.contact.sources', []);

        return $labels[$this->source] ?? $this->source;
    }

    /**
     * 后台筛选用的来源选项
     *
     * 配置中登记的来源 + 库里实际出现过的来源取并集，
     * 避免历史数据里的来源因未登记而无法筛选。
     *
     * @return array<string, string>
     */
    public static function sourceFilterOptions(): array
    {
        /** @var array<string, string> $labels */
        $labels = config('filamentboot-site.contact.sources', []);

        /** @var list<string> $used */
        $used = static::query()
            ->whereNotNull('source')
            ->distinct()
            ->pluck('source')
            ->all();

        foreach ($used as $source) {
            $labels[$source] ??= $source;
        }

        return $labels;
    }
}
