<?php

namespace FilamentAdmin\Models;

use Database\Factories\AdminUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use FilamentAdmin\Enums\AdminUserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticatable;

/**
 * 管理员用户模型
 *
 * 支持 account 或 email 登录，集成 Filament 面板认证。
 *
 * @property int $id
 * @property string $account
 * @property string $email
 * @property string $password
 * @property string $nickname
 * @property AdminUserStatus|null $status
 * @property int|null $department_id
 * @property string|null $avatar
 * @property string|null $mobile
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $login_failures
 * @property bool $onboarding_completed
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class AdminUser extends Authenticatable implements FilamentUser, HasMedia, HasName
{
    use HasApiTokens;

    /** @use HasFactory<AdminUserFactory> */
    use HasFactory; // 提供 Sanctum API Token 能力
    use HasRoles; // 提供角色与权限管理方法
    use InteractsWithMedia; // 提供媒体库操作方法
    use SoftDeletes;
    use TwoFactorAuthenticatable; // 提供 TOTP 双因素认证方法

    /** @var string */
    protected $table = 'admin_users';

    /** @var list<string> */
    protected $guarded = [];

    /**
     * 隐藏的属性（序列化时不输出）
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at'           => 'datetime',
            'password'                => 'hashed',
            'status'                  => AdminUserStatus::class,
            'onboarding_completed'    => 'boolean',
        ];
    }

    /**
     * 返回 Filament 界面显示的用户名称
     *
     * 优先使用昵称，回退到账号。
     */
    public function getFilamentName(): string
    {
        return $this->nickname ?? $this->account ?? '';
    }

    /**
     * 判断用户是否可访问 Filament 面板
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return ($this->status ?? AdminUserStatus::Active) === AdminUserStatus::Active
            && ! $this->trashed();
    }

    /**
     * 所属主部门
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * 登录日志关系
     *
     * @return HasMany<LoginLog, $this>
     */
    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    /**
     * 注册媒体 Collections
     *
     * avatar：管理员头像，单文件，仅允许常见图片格式。
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }
}
