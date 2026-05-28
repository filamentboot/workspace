<?php

namespace App\Models;

use Database\Factories\AdminUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticatable;

/**
 * 管理员用户模型
 *
 * 支持 username 或 email 登录，集成 Filament 面板认证。
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $name
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class AdminUser extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory;

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
            'password'                => 'hashed',
        ];
    }

    /**
     * 判断用户是否可访问 Filament 面板
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // Phase 1: 所有 admin_users 均可访问
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
}
