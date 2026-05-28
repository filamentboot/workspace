# FilamentAdmin Phase 1: 认证与基础设施 - 详细设计

**日期**: 2026-05-28  
**作者**: OpenCode (Claude Sonnet 4.5)  
**状态**: 待审核  
**版本**: 1.1

---

## 1. 概述

本文档详细描述 FilamentAdmin v1 Phase 1（认证与基础设施）的技术设计方案。Phase 1 的核心目标是实现安全、可靠的管理员登录系统，包括双因素认证（2FA）和登录日志记录功能。

### 1.1 核心功能
- 管理员登录/登出（**自定义登录页，支持 username 或 email 登录**）
- 双因素认证（2FA，默认禁用，用户可在个人资料中启用）
- 登录日志记录（成功与失败的登录尝试）
- 速率限制（Filament 5 内置：5 次/分钟）
- 防枚举攻击（username/email 不存在时返回相同错误提示）

### 1.2 非功能需求
- **测试覆盖率**: 整体 ≥60%，安全关键路径（登录、认证）100%
- **代码质量**: Larastan level 6 + Laravel Pint (PSR-12)
- **开发模式**: TDD 严格模式（测试先行）
- **开发周期**: 6 周（25-30 小时，兼职）

---

## 2. 技术栈与依赖

### 2.1 核心框架
- **Laravel 11**: 认证基础设施
- **Filament 5**: 管理面板框架
- **PHP 8.2+**: 参照 `composer.json` 的 `require.php` 字段

### 2.2 关键依赖包
- `stephenjude/filament-two-factor-authentication`: 2FA 插件
- `pestphp/pest`: 测试框架
- `larastan/larastan`: 静态分析（level 6）
- `laravel/pint`: 代码格式化（PSR-12）

### 2.3 开发环境
- **数据库**: MySQL 8.0 (127.0.0.1:3380, root/123456)
- **测试数据库**: SQLite in-memory
- **Redis**: 127.0.0.1:6379 (密码: 123456)

---

## 3. 数据库设计

### 3.1 admin_users 表

管理员用户表，替代 Laravel 默认的 `users` 表（见 `doc/需求.md:588`）。

```php
Schema::create('admin_users', function (Blueprint $table) {
    $table->id();
    $table->string('username')->unique();
    $table->string('email')->unique();
    $table->string('password');
    $table->string('name');
    
    // 2FA 字段（stephenjude 插件要求）
    $table->text('two_factor_secret')->nullable();
    $table->text('two_factor_recovery_codes')->nullable();
    $table->timestamp('two_factor_confirmed_at')->nullable();
    
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});
```

**字段说明**:
- `username`: 登录用户名，唯一索引
- `email`: 邮箱地址，唯一索引
- `name`: 显示名称
- `two_factor_*`: 2FA 相关字段（插件自动管理）
- `deleted_at`: 软删除支持

### 3.2 login_logs 表

记录所有登录尝试（成功与失败），独立于 `activity_log` 表（见 `doc/需求.md:593`）。

```php
Schema::create('login_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
    $table->string('username')->nullable(); // 失败时可能无有效 user_id
    $table->enum('status', ['success', 'failed']);
    $table->string('ip_address', 45);
    $table->text('user_agent')->nullable();
    $table->string('failure_reason')->nullable(); // 失败原因：invalid_credentials, rate_limited 等
    $table->timestamp('created_at');
    
    $table->index(['admin_user_id', 'created_at']);
    $table->index(['status', 'created_at']);
    $table->index('ip_address');
});
```

**字段说明**:
- `admin_user_id`: 外键，失败登录时可能为 null
- `username`: 尝试登录的用户名（失败时用于审计）
- `status`: 登录状态（success | failed）
- `ip_address`: 客户端 IP（支持 IPv6，长度 45）
- `user_agent`: 浏览器 User-Agent
- `failure_reason`: 失败原因（invalid_credentials, rate_limited, account_locked 等）

**索引策略**:
- `(admin_user_id, created_at)`: 查询用户登录历史
- `(status, created_at)`: 查询失败登录记录
- `(ip_address)`: IP 地址审计

---

## 4. 文件结构设计

### 4.1 设计原则

尽可能使用 Filament 5 和 Laravel 11 的默认机制，仅在必要时自定义（如支持 username/email 登录）。

**不创建的文件**:
- ❌ 自定义认证事件类（使用 Laravel 原生事件）
- ❌ 自定义中间件（使用 Filament 内置）

### 4.2 需要创建的文件

```
app/
├── Models/
│   ├── AdminUser.php                    # 管理员模型
│   └── LoginLog.php                     # 登录日志模型
├── Filament/
│   └── Pages/
│       └── Auth/
│           └── Login.php                # 自定义登录页（支持 username/email）
├── Listeners/
│   └── LogAdminLogin.php                # 登录日志监听器
└── Providers/
    └── EventServiceProvider.php         # 事件注册（已存在，修改）

config/
└── auth.php                             # 配置 admin guard（已存在，修改）

database/
├── migrations/
│   ├── YYYY_MM_DD_create_admin_users_table.php
│   └── YYYY_MM_DD_create_login_logs_table.php
└── factories/
    ├── AdminUserFactory.php
    └── LoginLogFactory.php

tests/
├── Feature/
│   ├── Auth/
│   │   ├── AdminLoginTest.php           # 测试 username/email 登录
│   │   ├── AdminLogoutTest.php
│   │   └── TwoFactorAuthenticationTest.php
│   └── LoginLogTest.php
└── Unit/
    ├── Models/
    │   ├── AdminUserTest.php
    │   └── LoginLogTest.php
    └── Listeners/
        └── LogAdminLoginTest.php
```

---

## 5. 认证流程设计

### 5.1 自定义登录页实现

**需求**：支持管理员使用 **username 或 email** 登录。

**实现** (`app/Filament/Pages/Auth/Login.php`):

```php
namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * 自定义表单字段
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('login')
                    ->label('用户名或邮箱')
                    ->required()
                    ->autofocus()
                    ->autocomplete('username'),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    /**
     * 获取认证凭据
     * 根据输入自动判断是 username 还是 email
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = $data['login'];
        
        // 判断输入是 email 还是 username
        $loginType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        return [
            $loginType => $login,
            'password' => $data['password'],
        ];
    }

    /**
     * 自定义认证失败消息（防止枚举攻击）
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => '用户名/邮箱或密码错误',
        ]);
    }
}
```

**安全考虑**：
- ✅ **防枚举攻击**：无论 username/email 是否存在，均返回相同错误提示
- ✅ **自动判断类型**：通过 `filter_var()` 判断输入是 email 还是 username
- ✅ **速率限制**：继承 Filament 内置的 5 次/分钟限制

### 5.2 Filament AdminPanelProvider 配置
### 5.2 Filament AdminPanelProvider 配置

```php
// app/Providers/Filament/AdminPanelProvider.php
use App\Filament\Pages\Auth\Login;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login(Login::class)  // 使用自定义登录页
        ->authGuard('admin')
        ->authPasswordBroker('admin_users')
        // ... 其他配置
}
```

### 5.3 Laravel Auth 配置

```php
// config/auth.php
'guards' => [
    'admin' => [
        'driver' => 'session',
        'provider' => 'admin_users',
    ],
    // ...
],

'providers' => [
    'admin_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\AdminUser::class,
    ],
    // ...
],

'passwords' => [
    'admin_users' => [
        'provider' => 'admin_users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
    // ...
],
```

### 5.4 登录日志记录流程

使用 **Laravel 原生事件** 监听登录成功/失败：

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \Illuminate\Auth\Events\Login::class => [
        \App\Listeners\LogAdminLogin::class,
    ],
    \Illuminate\Auth\Events\Failed::class => [
        \App\Listeners\LogAdminLogin::class,
    ],
    \Illuminate\Auth\Events\Logout::class => [
        // 可选：记录登出日志
    ],
];
```

**监听器实现** (`app/Listeners/LogAdminLogin.php`):

```php
namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

class LogAdminLogin
{
    /**
     * 处理登录成功或失败事件
     */
    public function handle(Login|Failed $event): void
    {
        // 仅处理 admin guard 的事件
        if ($event->guard !== 'admin') {
            return;
        }

        $status = $event instanceof Login ? 'success' : 'failed';
        
        LoginLog::create([
            'admin_user_id' => $event->user?->id,
            // 优先记录 username，不存在则记录 email
            'username' => $this->extractUsername($event),
            'status' => $status,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'failure_reason' => $status === 'failed' 
                ? $this->determineFailureReason($event)
                : null,
        ]);
    }

    /**
     * 提取用户名（支持 username 或 email 登录）
     */
    private function extractUsername(Login|Failed $event): ?string
    {
        if ($event->user) {
            // 成功登录时，优先使用 username
            return $event->user->username ?? $event->user->email;
        }

        // 失败登录时，从凭据中提取
        return $event->credentials['username'] 
            ?? $event->credentials['email'] 
            ?? $event->credentials['login'] // 自定义登录页使用 'login' 字段
            ?? null;
    }

    /**
     * 确定登录失败原因
     */
    private function determineFailureReason(Failed $event): string
    {
        // 根据 Filament 的限流逻辑判断
        // 可通过 RateLimiter::tooManyAttempts() 检测
        return 'invalid_credentials'; // 简化版，实际可扩展
    }
}
```

**关键设计决策**:
- ✅ 使用 Laravel `Login` 和 `Failed` 事件（不是自定义事件）
- ✅ 监听器通过 `$event->guard` 过滤，仅处理 `admin` guard
- ✅ `Failed` 事件中 `$event->user` 为 null，需从 `$event->credentials` 获取用户名
- ✅ Filament 5 内置速率限制（5 次/分钟），无需额外配置

---

## 6. 2FA 集成设计

### 6.1 插件选型

使用 `stephenjude/filament-two-factor-authentication` 插件（见 `doc/需求.md:471-475`）。

**选择理由**:
1. Filament 生态官方推荐
2. 支持 TOTP (Time-based One-Time Password)
3. 提供恢复码（recovery codes）
4. 活跃维护，文档完善

### 6.2 安装与配置

```bash
composer require stephenjude/filament-two-factor-authentication
php artisan vendor:publish --tag="filament-two-factor-authentication-config"
# 注意：由于使用自定义 admin_users 表，2FA 字段已在第 3.1 节的迁移中手动定义
php artisan migrate
```

**配置策略**:
- **默认状态**: 禁用（新用户注册后 `two_factor_confirmed_at` 为 null）
- **启用方式**: 用户在个人资料页面（Filament Profile 页）手动启用
- **强制策略**: Phase 1 不强制启用，Phase 2 可根据角色强制（如超级管理员）

### 6.3 AdminUser 模型集成

```php
// app/Models/AdminUser.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticatable;

class AdminUser extends Authenticatable implements FilamentUser
{
    use HasFactory, SoftDeletes, TwoFactorAuthenticatable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Filament 面板访问权限
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // Phase 1: 所有 admin_users 均可访问
    }

    /**
     * 登录日志关系
     */
    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }
}
```

### 6.4 2FA 启用流程

1. 用户登录后访问 Filament Profile 页面
2. 点击「启用双因素认证」
3. 扫描 QR 码（插件自动生成）
4. 输入验证码确认
5. 保存恢复码（插件自动生成 8 个）
6. `two_factor_confirmed_at` 字段被设置为当前时间

**测试覆盖**:
- 启用 2FA 流程（成功/失败）
- 使用 2FA 登录（验证码正确/错误）
- 使用恢复码登录
- 禁用 2FA

---

## 7. 测试策略

### 7.1 TDD 严格模式（方案 B）

**原则**: **测试先行，红-绿-重构循环**

```
编写失败测试 (Red) → 实现代码使测试通过 (Green) → 重构优化 (Refactor)
```

### 7.2 测试工具配置

**Pest 配置** (`tests/Pest.php`):
```php
uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');
```

**Larastan 配置** (`phpstan.neon`):
```yaml
includes:
    - ./vendor/larastan/larastan/extension.neon

parameters:
    level: 6
    paths:
        - app
        - database
    excludePaths:
        - vendor
```

**Pint 配置** (`pint.json`):
```json
{
    "preset": "laravel",
    "rules": {
        "psr12": true
    }
}
```

### 7.3 测试覆盖目标

| 类型 | 覆盖率目标 | 说明 |
|------|-----------|------|
| **整体** | ≥60% | 所有代码（见 `doc/需求.md:639`） |
| **安全关键路径** | 100% | 登录、登出、2FA、密码重置 |
| **模型** | ≥80% | AdminUser, LoginLog |
| **监听器** | 100% | LogAdminLogin |

### 7.4 测试示例

**单元测试** (`tests/Unit/Models/AdminUserTest.php`):
```php
it('has login logs relationship', function () {
    $user = AdminUser::factory()->create();
    LoginLog::factory()->create(['admin_user_id' => $user->id]);
    
    expect($user->loginLogs)->toHaveCount(1);
});

it('hides sensitive attributes', function () {
    $user = AdminUser::factory()->create();
    
    expect($user->toArray())
        ->not->toHaveKey('password')
        ->not->toHaveKey('two_factor_secret');
});
```

**功能测试** (`tests/Feature/Auth/AdminLoginTest.php`):
```php
it('allows login with username', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->post('/admin/login', [
        'login' => 'admin',  // 使用 username
        'password' => 'password',
    ]);

    $this->assertAuthenticated('admin');
    
    expect(LoginLog::latest()->first())
        ->status->toBe('success')
        ->admin_user_id->toBe($user->id)
        ->username->toBe('admin');
});

it('allows login with email', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->post('/admin/login', [
        'login' => 'admin@example.com',  // 使用 email
        'password' => 'password',
    ]);

    $this->assertAuthenticated('admin');
    
    expect(LoginLog::latest()->first())
        ->status->toBe('success')
        ->admin_user_id->toBe($user->id);
});

it('logs failed login with invalid credentials', function () {
    $this->post('/admin/login', [
        'login' => 'nonexistent',
        'password' => 'wrong',
    ]);

    expect(LoginLog::latest()->first())
        ->status->toBe('failed')
        ->failure_reason->toBe('invalid_credentials')
        ->admin_user_id->toBeNull();
});

it('prevents username enumeration attack', function () {
    AdminUser::factory()->create([
        'username' => 'existing',
        'password' => 'password',
    ]);

    // 不存在的用户
    $response1 = $this->post('/admin/login', [
        'login' => 'nonexistent',
        'password' => 'wrong',
    ]);

    // 存在的用户，错误密码
    $response2 = $this->post('/admin/login', [
        'login' => 'existing',
        'password' => 'wrong',
    ]);

    // 两者应返回相同的错误提示
    expect($response1->getSession()->get('errors')->get('data.login'))
        ->toBe($response2->getSession()->get('errors')->get('data.login'));
});

it('enforces rate limiting after 5 failed attempts', function () {
    // Filament 5 内置：5 次/分钟
    for ($i = 0; $i < 6; $i++) {
        $response = $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'wrong',
        ]);
    }

    $response->assertStatus(429); // Too Many Requests
    
    expect(LoginLog::where('failure_reason', 'rate_limited')->count())->toBe(1);
});
```

**2FA 测试** (`tests/Feature/Auth/TwoFactorAuthenticationTest.php`):
```php
it('allows user to enable 2FA', function () {
    $user = AdminUser::factory()->create();
    actingAs($user, 'admin');

    // 启用 2FA 流程测试（插件提供的路由）
    $response = $this->post('/admin/two-factor-authentication/enable');
    
    expect($user->fresh())
        ->two_factor_secret->not->toBeNull()
        ->two_factor_confirmed_at->not->toBeNull();
});

it('requires 2FA code when enabled', function () {
    $user = AdminUser::factory()->withTwoFactor()->create();

    $this->post('/admin/login', [
        'login' => $user->username,  // 使用自定义 login 字段
        'password' => 'password',
    ]);

    // 应重定向到 2FA 验证页面
    $this->assertGuest('admin');
});
```

---

## 8. 实施时间线

**总周期**: 6 周（25-30 小时，每周 4-5 小时）

### Week 1: 测试框架搭建 (5小时)
- [ ] 安装 Pest, Larastan (level 6), Pint
- [ ] 配置测试数据库（SQLite in-memory）
- [ ] 创建测试基类 `Tests\TestCase`
- [ ] 编写冒烟测试（smoke test）验证环境

**验收标准**:
```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

### Week 2: admin_users 模型、自定义登录页 (6-7小时)
**TDD 流程**:
1. 编写测试：
   - `AdminUserTest` (字段、关系、工厂)
   - `AdminLoginTest` (username/email 登录、防枚举攻击)
2. 运行测试：红色 ❌
3. 实现代码：
   - 创建 `create_admin_users_table` 迁移
   - 创建 `AdminUser` 模型
   - 创建 `AdminUserFactory`
   - **创建自定义登录页** `app/Filament/Pages/Auth/Login.php`
   - 配置 `config/auth.php` 和 `AdminPanelProvider`
4. 运行测试：绿色 ✅
5. 重构代码（如有必要）

**验收标准**:
- AdminUser 模型测试通过
- 可通过工厂创建测试用户（username + email）
- 可使用 username 登录
- 可使用 email 登录
- 防枚举攻击测试通过
- `php artisan migrate` 成功

### Week 3: 登录日志功能 (4-5小时)
**TDD 流程**:
1. 编写测试：
   - `LoginLogTest` (模型)
   - `LogAdminLoginTest` (监听器)
   - `AdminLoginTest` (功能测试：成功/失败登录)
2. 运行测试：红色 ❌
3. 实现代码：
   - `create_login_logs_table` 迁移
   - `LoginLog` 模型
   - `LogAdminLogin` 监听器
   - 在 `EventServiceProvider` 注册事件
4. 运行测试：绿色 ✅
5. 重构

**验收标准**:
- 成功登录写入 `login_logs` (status='success')
- 失败登录写入 `login_logs` (status='failed')
- 速率限制触发时记录 `failure_reason='rate_limited'`

### Week 4: 2FA 集成 (3-4小时)
**TDD 流程**:
1. 编写测试：`TwoFactorAuthenticationTest` (启用、验证、恢复码)
2. 运行测试：红色 ❌
3. 实现代码：
   - 安装 `stephenjude/filament-two-factor-authentication`
   - 在 `AdminUser` 模型添加 `TwoFactorAuthenticatable` trait
   - 运行迁移（插件自动添加 2FA 字段）
   - 配置插件（默认禁用）
4. 运行测试：绿色 ✅
5. 重构

**验收标准**:
- 用户可在 Profile 页启用 2FA
- 启用后登录需要验证码
- 恢复码可正常使用
- 2FA 启用/禁用记录到 `login_logs`

### Week 5: 集成测试与边界场景 (3-4小时)
**测试覆盖**:
- [ ] 端到端测试：完整登录流程（无 2FA + 有 2FA）
- [ ] 边界场景：
  - 速率限制（6 次失败登录）
  - 无效凭据（用户名/密码错误）
  - 过期会话
  - 软删除用户登录
  - IP 地址记录（IPv4 + IPv6）

**验收标准**:
- 整体测试覆盖率 ≥60%
- 安全关键路径覆盖率 100%
- `vendor/bin/phpstan analyse` 无错误

### Week 6: 文档与收尾 (2-3小时)
- [ ] 编写用户文档：
  - 如何登录管理面板
  - 如何启用/禁用 2FA
  - 如何查看登录日志（Phase 2 实现 UI）
- [ ] 代码审查与重构
- [ ] 运行完整测试套件
- [ ] 提交代码到 Git
- [ ] 准备 Phase 2 规划

**验收标准**:
```bash
php artisan test --coverage --min=60
vendor/bin/phpstan analyse
vendor/bin/pint --test
git log --oneline -10  # 检查提交记录
```

---

## 9. 风险与缓解措施

| 风险 | 影响 | 概率 | 缓解措施 |
|------|------|------|----------|
| **自定义登录页与 2FA 插件不兼容** | 高 | 中 | Week 2 完成后立即测试 2FA 集成，必要时联系插件作者 |
| **用户名枚举攻击** | 中 | 中 | 所有登录失败返回相同错误提示，Week 2 编写防枚举测试 |
| **测试覆盖率未达标** | 中 | 中 | Week 5 专门补充测试，使用 `--coverage` 监控 |
| **TDD 拖慢进度** | 低 | 中 | Week 1 设定基准速度，Week 3 复盘调整 |
| **Laravel 事件未触发** | 高 | 低 | Week 3 编写集成测试验证事件触发 |
| **速率限制逻辑误判** | 中 | 中 | Week 5 编写边界测试（正好 5 次、6 次） |

---

## 10. 验收标准（Definition of Done）

Phase 1 完成的标准：

- [x] **功能完整性**:
  - ✅ 管理员可通过 username 或 email 登录
  - ✅ 防止用户名枚举攻击
  - ✅ 管理员可启用/禁用 2FA
  - ✅ 所有登录尝试记录到 `login_logs` 表
  - ✅ 速率限制正常工作（5 次/分钟）

- [x] **测试质量**:
  - ✅ 整体覆盖率 ≥60%
  - ✅ 安全关键路径覆盖率 100%
  - ✅ Larastan level 6 无错误
  - ✅ Pint (PSR-12) 格式检查通过

- [x] **文档完整**:
  - ✅ 技术设计文档（本文档）
  - ✅ 用户操作文档（登录、2FA 使用）
  - ✅ 代码注释（PHPDoc 标准，中文）

- [x] **可部署**:
  - ✅ 迁移文件可重复运行（`php artisan migrate:fresh --seed`）
  - ✅ 无硬编码凭据或配置
  - ✅ Git 提交记录清晰（中文 commit message）

---

## 11. 下一步行动

1. **用户审核本设计文档**：确认设计方案是否符合需求
2. **使用 writing-plans skill**：将本设计转换为可执行的实施计划
3. **Week 1 启动**：搭建测试框架，建立 TDD 工作流
4. **定期回顾**：每周结束时检查进度，调整计划（如有必要）

---

## 附录

### A. 相关文档
- `/home/john/projects/personal/filament-admin/doc/需求.md`: 完整需求文档
- `/home/john/projects/personal/filament-admin/docs/superpowers/specs/2026-05-28-filament-admin-v1-development-plan.md`: v1 总体规划

### B. 参考资料
- [Filament 5 Documentation](https://filamentphp.com/docs/5.x/panels/users)
- [Laravel 11 Authentication](https://laravel.com/docs/11.x/authentication)
- [stephenjude/filament-two-factor-authentication](https://github.com/stephenjude/filament-two-factor-authentication)
- [Pest Testing Framework](https://pestphp.com/)

### C. 术语表
- **2FA**: Two-Factor Authentication（双因素认证）
- **TOTP**: Time-based One-Time Password（基于时间的一次性密码）
- **TDD**: Test-Driven Development（测试驱动开发）
- **PSR-12**: PHP 代码风格规范
- **Guard**: Laravel 认证守卫（定义认证来源）
- **Provider**: Laravel 认证提供者（定义用户数据来源）

---

**文档版本历史**:
- v1.0 (2026-05-28): 初始版本，使用 Filament 默认登录页
- v1.1 (2026-05-28): 更新为自定义登录页，支持 username/email 登录，增加防枚举攻击设计
