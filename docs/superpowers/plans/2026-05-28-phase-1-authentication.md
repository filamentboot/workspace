> ## 历史说明
>
> 本计划已于 **2026-05-28** 执行完毕，对应标签 `v1.0.0-phase1`，49 个测试全通过。
>
> 实际实现与本计划存在以下历史偏差，**以 `AGENTS.md` 与代码为准，本文件保留作为历史规划记录，不再更新**：
>
> 1. 后台 guard 名实际为 `admin`（计划文档曾出现 `admin_user` / `web` 等混用）
> 2. AdminUser 模型实际位置 `app/Models/AdminUser.php`，已含 `SoftDeletes` + `TwoFactorAuthenticatable` + `FilamentUser`
> 3. 登录页实际位于 `app/Filament/Pages/Auth/Login.php`，支持 username 或 email 双登录
> 4. 2FA 包采用 `stephenjude/filament-two-factor-authentication`（已在 AdminPanelProvider 注册）
> 5. Provider 注册在 `bootstrap/providers.php`（非 `config/app.php`）
> 6. 异常处理在 `bootstrap/app.php` 的 `->withExceptions()` 块（非 `app/Exceptions/Handler.php`）
> 7. 控制台调度写在 `routes/console.php`（非 `app/Console/Kernel.php`）
> 8. `LogAdminLogin` 监听器通过 Laravel 自动发现注册，不在 AppServiceProvider 手动 `Event::listen`
>
> 后续开发请参考 `AGENTS.md` 与已实现的代码作为事实来源。

---

# Phase 1: 认证与基础设施 - 实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现安全的管理员登录系统，支持 username/email 登录、2FA、登录日志记录

**Architecture:** 基于 Filament 5 + Laravel 11，使用自定义登录页支持灵活登录方式，通过 Laravel 原生事件记录登录日志，集成 stephenjude/filament-two-factor-authentication 插件实现 2FA

**Tech Stack:** Laravel 11, Filament 5, Pest, Larastan (level 6), Laravel Pint, stephenjude/filament-two-factor-authentication

**Design Doc:** `/home/john/projects/personal/filament-admin/docs/superpowers/specs/2026-05-28-phase-1-authentication-design.md`

---

## 文件结构规划

### 新建文件
```
app/
├── Models/
│   ├── AdminUser.php                    # 管理员模型
│   └── LoginLog.php                     # 登录日志模型
├── Filament/
│   └── Pages/
│       └── Auth/
│           └── Login.php                # 自定义登录页
└── Listeners/
    └── LogAdminLogin.php                # 登录日志监听器

database/
├── migrations/
│   ├── 2026_05_28_000001_create_admin_users_table.php
│   └── 2026_05_28_000002_create_login_logs_table.php
└── factories/
    ├── AdminUserFactory.php
    └── LoginLogFactory.php

tests/
├── Feature/
│   └── Auth/
│       ├── AdminLoginTest.php
│       ├── AdminLogoutTest.php
│       └── TwoFactorAuthenticationTest.php
└── Unit/
    ├── Models/
    │   ├── AdminUserTest.php
    │   └── LoginLogTest.php
    └── Listeners/
        └── LogAdminLoginTest.php
```

### 修改文件
```
config/auth.php                          # 添加 admin guard 配置
app/Providers/EventServiceProvider.php   # 注册登录日志监听器
app/Providers/Filament/AdminPanelProvider.php  # 配置自定义登录页
tests/Pest.php                           # 配置 Pest 测试框架
phpstan.neon                             # 配置 Larastan
pint.json                                # 配置 Pint
composer.json                            # 安装依赖包
```

---

## Week 1: 测试框架搭建

### Task 1.1: 安装 Pest 测试框架

**Files:**
- Modify: `composer.json`
- Create: `tests/Pest.php`

- [ ] **Step 1: 安装 Pest 和相关依赖**

```bash
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

- [ ] **Step 2: 配置 Pest**

修改 `tests/Pest.php`:
```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');

// 全局辅助函数
function assertDatabaseHasInOrder(string $table, array $data): void
{
    $query = DB::table($table);
    foreach ($data as $key => $value) {
        $query->where($key, $value);
    }
    expect($query->exists())->toBeTrue();
}
```

- [ ] **Step 3: 编写冒烟测试**

创建 `tests/Feature/SmokeTest.php`:
```php
<?php

test('application returns successful response', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});

test('database connection works', function () {
    expect(DB::connection()->getDatabaseName())->not->toBeNull();
});
```

- [ ] **Step 4: 运行冒烟测试**

```bash
php artisan test
```

Expected output: 2 tests pass

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock tests/Pest.php tests/Feature/SmokeTest.php
git commit -m "build: 安装 Pest 测试框架并添加冒烟测试"
```

---

### Task 1.2: 安装并配置 Larastan

**Files:**
- Modify: `composer.json`
- Create: `phpstan.neon`

- [ ] **Step 1: 安装 Larastan**

```bash
composer require larastan/larastan:^2.0 --dev
```

- [ ] **Step 2: 创建 phpstan.neon 配置**

创建 `phpstan.neon`:
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
        - storage
        - bootstrap/cache
    checkMissingIterableValueType: false
```

- [ ] **Step 3: 运行 Larastan 检查**

```bash
vendor/bin/phpstan analyse
```

Expected: 初次运行可能有一些警告，记录下来后续修复

- [ ] **Step 4: 添加 composer script**

修改 `composer.json`，在 `scripts` 部分添加:
```json
{
    "scripts": {
        "phpstan": "vendor/bin/phpstan analyse",
        "test": "php artisan test"
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock phpstan.neon
git commit -m "build: 配置 Larastan 静态分析 (level 6)"
```

---

### Task 1.3: 安装并配置 Laravel Pint

**Files:**
- Modify: `composer.json`
- Create: `pint.json`

- [ ] **Step 1: 安装 Laravel Pint**

```bash
composer require laravel/pint --dev
```

- [ ] **Step 2: 创建 pint.json 配置**

创建 `pint.json`:
```json
{
    "preset": "laravel",
    "rules": {
        "psr12": true,
        "array_syntax": {
            "syntax": "short"
        },
        "ordered_imports": {
            "sort_algorithm": "alpha"
        }
    }
}
```

- [ ] **Step 3: 运行 Pint 检查**

```bash
vendor/bin/pint --test
```

Expected: 显示需要格式化的文件数量

- [ ] **Step 4: 格式化现有代码**

```bash
vendor/bin/pint
```

- [ ] **Step 5: 添加 composer script**

修改 `composer.json`，在 `scripts` 部分添加:
```json
{
    "scripts": {
        "phpstan": "vendor/bin/phpstan analyse",
        "test": "php artisan test",
        "format": "vendor/bin/pint",
        "format-check": "vendor/bin/pint --test"
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock pint.json .
git commit -m "build: 配置 Laravel Pint 代码格式化工具"
```

---

### Task 1.4: 配置测试数据库

**Files:**
- Modify: `phpunit.xml`
- Create: `tests/TestCase.php` (if not exists)

- [ ] **Step 1: 配置 phpunit.xml 使用 SQLite 内存数据库**

修改 `phpunit.xml`，在 `<php>` 部分添加:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 2: 编写数据库测试**

创建 `tests/Feature/DatabaseTest.php`:
```php
<?php

test('can create and query records in test database', function () {
    DB::table('migrations')->insert([
        'migration' => 'test_migration',
        'batch' => 1,
    ]);

    expect(DB::table('migrations')->where('migration', 'test_migration')->exists())
        ->toBeTrue();
});
```

- [ ] **Step 3: 运行数据库测试**

```bash
php artisan test --filter=DatabaseTest
```

Expected: 1 test pass

- [ ] **Step 4: Commit**

```bash
git add phpunit.xml tests/Feature/DatabaseTest.php
git commit -m "test: 配置测试数据库使用 SQLite in-memory"
```

---

### Task 1.5: Week 1 验收检查

**Files:**
- None (verification only)

- [ ] **Step 1: 运行所有测试**

```bash
php artisan test
```

Expected: 所有测试通过 (至少 3 个测试)

- [ ] **Step 2: 运行静态分析**

```bash
composer phpstan
```

Expected: No errors (可能有 warnings)

- [ ] **Step 3: 运行代码格式检查**

```bash
composer format-check
```

Expected: No files need formatting

- [ ] **Step 4: 创建 Week 1 总结 commit**

```bash
git log --oneline | head -5
```

Expected: 看到本周的 4-5 个 commits

如果所有检查通过，Week 1 完成。

---

## Week 2: AdminUser 模型与自定义登录页

### Task 2.1: 创建 admin_users 迁移

**Files:**
- Create: `database/migrations/2026_05_28_000001_create_admin_users_table.php`

- [ ] **Step 1: 生成迁移文件**

```bash
php artisan make:migration create_admin_users_table
```

- [ ] **Step 2: 编写迁移代码**

修改生成的迁移文件:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
```

- [ ] **Step 3: 运行迁移**

```bash
php artisan migrate
```

Expected: Migration successful

- [ ] **Step 4: 验证表结构**

```bash
php artisan db:show --table=admin_users
```

Expected: 显示所有字段

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*create_admin_users_table.php
git commit -m "feat: 创建 admin_users 表迁移"
```

---

### Task 2.2: 创建 AdminUser 模型（TDD）

**Files:**
- Create: `tests/Unit/Models/AdminUserTest.php`
- Create: `app/Models/AdminUser.php`
- Create: `database/factories/AdminUserFactory.php`

- [ ] **Step 1: 编写 AdminUser 模型测试**

创建 `tests/Unit/Models/AdminUserTest.php`:
```php
<?php

use App\Models\AdminUser;
use App\Models\LoginLog;

test('admin user can be created', function () {
    $user = AdminUser::factory()->create([
        'username' => 'testadmin',
        'email' => 'admin@example.com',
    ]);

    expect($user->username)->toBe('testadmin')
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->exists)->toBeTrue();
});

test('admin user has login logs relationship', function () {
    $user = AdminUser::factory()->create();
    
    expect($user->loginLogs())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('admin user hides sensitive attributes', function () {
    $user = AdminUser::factory()->create();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes')
        ->and($array)->not->toHaveKey('remember_token');
});

test('admin user password is hashed', function () {
    $user = AdminUser::factory()->create([
        'password' => 'plaintext',
    ]);

    expect($user->password)->not->toBe('plaintext')
        ->and(Hash::check('plaintext', $user->password))->toBeTrue();
});

test('admin user can access panel', function () {
    $user = AdminUser::factory()->create();
    
    expect($user->canAccessPanel(mock(\Filament\Panel::class)))->toBeTrue();
});
```

- [ ] **Step 2: 运行测试确认失败**

```bash
php artisan test --filter=AdminUserTest
```

Expected: FAIL - AdminUser class not found

- [ ] **Step 3: 创建 AdminUser 模型**

创建 `app/Models/AdminUser.php`:
```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * 隐藏的属性
     *
     * @var array<int, string>
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
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

- [ ] **Step 4: 创建 AdminUserFactory**

创建 `database/factories/AdminUserFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminUser>
 */
class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'password' => 'password', // 会被 hashed cast 自动哈希
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * 未验证邮箱的用户
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * 启用 2FA 的用户
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: 运行测试确认通过**

```bash
php artisan test --filter=AdminUserTest
```

Expected: 5 tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/AdminUser.php database/factories/AdminUserFactory.php tests/Unit/Models/AdminUserTest.php
git commit -m "feat: 创建 AdminUser 模型与工厂类"
```

---

### Task 2.3: 配置 admin guard

**Files:**
- Modify: `config/auth.php`

- [ ] **Step 1: 添加 admin guard 配置**

修改 `config/auth.php`:
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    // 新增 admin guard
    'admin' => [
        'driver' => 'session',
        'provider' => 'admin_users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],

    // 新增 admin_users provider
    'admin_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\AdminUser::class,
    ],
],

'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],

    // 新增 admin_users password broker
    'admin_users' => [
        'provider' => 'admin_users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

- [ ] **Step 2: 编写 guard 配置测试**

创建 `tests/Feature/Auth/AdminGuardTest.php`:
```php
<?php

use App\Models\AdminUser;

test('admin guard uses correct provider', function () {
    $config = config('auth.guards.admin');
    
    expect($config['driver'])->toBe('session')
        ->and($config['provider'])->toBe('admin_users');
});

test('admin users provider uses AdminUser model', function () {
    $config = config('auth.providers.admin_users');
    
    expect($config['driver'])->toBe('eloquent')
        ->and($config['model'])->toBe(AdminUser::class);
});

test('admin guard can authenticate admin user', function () {
    $user = AdminUser::factory()->create([
        'username' => 'testadmin',
        'password' => 'password',
    ]);

    $authenticated = Auth::guard('admin')->attempt([
        'username' => 'testadmin',
        'password' => 'password',
    ]);

    expect($authenticated)->toBeTrue()
        ->and(Auth::guard('admin')->user()->id)->toBe($user->id);
});
```

- [ ] **Step 3: 运行测试**

```bash
php artisan test --filter=AdminGuardTest
```

Expected: 3 tests PASS

- [ ] **Step 4: Commit**

```bash
git add config/auth.php tests/Feature/Auth/AdminGuardTest.php
git commit -m "feat: 配置 admin guard 和 admin_users provider"
```

---

### Task 2.4: 创建自定义登录页（TDD）

**Files:**
- Create: `tests/Feature/Auth/AdminLoginTest.php`
- Create: `app/Filament/Pages/Auth/Login.php`

- [ ] **Step 1: 编写自定义登录页测试**

创建 `tests/Feature/Auth/AdminLoginTest.php`:
```php
<?php

use App\Models\AdminUser;

test('allows login with username', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user, 'admin');
});

test('allows login with email', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response = $this->post('/admin/login', [
        'login' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user, 'admin');
});

test('fails login with invalid password', function () {
    AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest('admin');
});

test('prevents username enumeration attack', function () {
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
    $errors1 = $response1->getSession()->get('errors')->get('data.login');
    $errors2 = $response2->getSession()->get('errors')->get('data.login');
    
    expect($errors1)->toBe($errors2);
});
```

- [ ] **Step 2: 运行测试确认失败**

```bash
php artisan test --filter=AdminLoginTest
```

Expected: FAIL - Route not found or Login class not found

- [ ] **Step 3: 创建自定义登录页**

创建 `app/Filament/Pages/Auth/Login.php`:
```php
<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
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

- [ ] **Step 4: 运行测试确认通过**

```bash
php artisan test --filter=AdminLoginTest
```

Expected: 4 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/Auth/Login.php tests/Feature/Auth/AdminLoginTest.php
git commit -m "feat: 创建自定义登录页支持 username/email 登录"
```

---

### Task 2.5: 配置 Filament AdminPanelProvider

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] **Step 1: 修改 AdminPanelProvider**

修改 `app/Providers/Filament/AdminPanelProvider.php`:
```php
<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)  // 使用自定义登录页
            ->authGuard('admin')  // 使用 admin guard
            ->authPasswordBroker('admin_users')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

- [ ] **Step 2: 编写端到端登录测试**

创建 `tests/Feature/Auth/AdminLoginE2ETest.php`:
```php
<?php

use App\Models\AdminUser;

test('complete login flow with username', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    // 访问登录页
    $response = $this->get('/admin/login');
    $response->assertStatus(200);

    // 提交登录表单
    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'password',
    ]);

    // 应重定向到 admin dashboard
    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user, 'admin');
});

test('can logout after login', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $this->actingAs($user, 'admin');
    
    $response = $this->post('/admin/logout');
    
    $this->assertGuest('admin');
});
```

- [ ] **Step 3: 运行端到端测试**

```bash
php artisan test --filter=AdminLoginE2ETest
```

Expected: 2 tests PASS

- [ ] **Step 4: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php tests/Feature/Auth/AdminLoginE2ETest.php
git commit -m "feat: 配置 Filament 使用自定义登录页和 admin guard"
```

---

### Task 2.6: Week 2 验收检查

**Files:**
- None (verification only)

- [ ] **Step 1: 运行所有测试**

```bash
php artisan test
```

Expected: 所有测试通过 (至少 15 个测试)

- [ ] **Step 2: 手动测试登录功能**

```bash
# 创建测试用户
php artisan tinker
>>> $user = \App\Models\AdminUser::factory()->create(['username' => 'admin', 'email' => 'admin@test.com', 'password' => 'password']);
>>> exit
```

访问 http://localhost/admin/login，测试：
- 使用 username 'admin' 登录 ✓
- 使用 email 'admin@test.com' 登录 ✓
- 错误密码应返回统一错误提示 ✓

- [ ] **Step 3: 运行静态分析**

```bash
composer phpstan
```

Expected: No errors

- [ ] **Step 4: 运行代码格式检查**

```bash
composer format
```

- [ ] **Step 5: Commit Week 2 验收**

如果所有检查通过：
```bash
git log --oneline | head -10
```

Week 2 完成。

---

## Week 3: 登录日志功能

### Task 3.1: 创建 login_logs 迁移

**Files:**
- Create: `database/migrations/2026_05_28_000002_create_login_logs_table.php`

- [ ] **Step 1: 生成迁移文件**

```bash
php artisan make:migration create_login_logs_table
```

- [ ] **Step 2: 编写迁移代码**

修改生成的迁移文件:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->string('username')->nullable();
            $table->enum('status', ['success', 'failed']);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at');
            
            // 索引
            $table->index(['admin_user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
```

- [ ] **Step 3: 运行迁移**

```bash
php artisan migrate
```

Expected: Migration successful

- [ ] **Step 4: 验证表结构**

```bash
php artisan db:show --table=login_logs
```

Expected: 显示所有字段和索引

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*create_login_logs_table.php
git commit -m "feat: 创建 login_logs 表迁移"
```

---

### Task 3.2: 创建 LoginLog 模型（TDD）

**Files:**
- Create: `tests/Unit/Models/LoginLogTest.php`
- Create: `app/Models/LoginLog.php`
- Create: `database/factories/LoginLogFactory.php`

- [ ] **Step 1: 编写 LoginLog 模型测试**

创建 `tests/Unit/Models/LoginLogTest.php`:
```php
<?php

use App\Models\AdminUser;
use App\Models\LoginLog;

test('login log can be created', function () {
    $user = AdminUser::factory()->create();
    
    $log = LoginLog::factory()->create([
        'admin_user_id' => $user->id,
        'status' => 'success',
    ]);

    expect($log->status)->toBe('success')
        ->and($log->admin_user_id)->toBe($user->id)
        ->and($log->exists)->toBeTrue();
});

test('login log belongs to admin user', function () {
    $user = AdminUser::factory()->create();
    $log = LoginLog::factory()->create(['admin_user_id' => $user->id]);

    expect($log->adminUser->id)->toBe($user->id);
});

test('login log can have null admin_user_id for failed attempts', function () {
    $log = LoginLog::factory()->create([
        'admin_user_id' => null,
        'username' => 'nonexistent',
        'status' => 'failed',
    ]);

    expect($log->admin_user_id)->toBeNull()
        ->and($log->username)->toBe('nonexistent')
        ->and($log->adminUser)->toBeNull();
});

test('login log records ip address and user agent', function () {
    $log = LoginLog::factory()->create([
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0',
    ]);

    expect($log->ip_address)->toBe('192.168.1.1')
        ->and($log->user_agent)->toBe('Mozilla/5.0');
});
```

- [ ] **Step 2: 运行测试确认失败**

```bash
php artisan test --filter=LoginLogTest
```

Expected: FAIL - LoginLog class not found

- [ ] **Step 3: 创建 LoginLog 模型**

创建 `app/Models/LoginLog.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    use HasFactory;

    /**
     * 禁用 updated_at
     */
    public const UPDATED_AT = null;

    protected $guarded = [];

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
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}
```

- [ ] **Step 4: 创建 LoginLogFactory**

创建 `database/factories/LoginLogFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\AdminUser;
use App\Models\LoginLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoginLog>
 */
class LoginLogFactory extends Factory
{
    protected $model = LoginLog::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['success', 'failed']);

        return [
            'admin_user_id' => $status === 'success' ? AdminUser::factory() : null,
            'username' => fake()->userName(),
            'status' => $status,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'failure_reason' => $status === 'failed' ? 'invalid_credentials' : null,
            'created_at' => now(),
        ];
    }

    /**
     * 成功登录
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'failure_reason' => null,
            'admin_user_id' => AdminUser::factory(),
        ]);
    }

    /**
     * 失败登录
     */
    public function failed(?string $reason = 'invalid_credentials'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'failure_reason' => $reason,
            'admin_user_id' => null,
        ]);
    }
}
```

- [ ] **Step 5: 运行测试确认通过**

```bash
php artisan test --filter=LoginLogTest
```

Expected: 4 tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/LoginLog.php database/factories/LoginLogFactory.php tests/Unit/Models/LoginLogTest.php
git commit -m "feat: 创建 LoginLog 模型与工厂类"
```

---

### Task 3.3: 创建 LogAdminLogin 监听器（TDD）

**Files:**
- Create: `tests/Unit/Listeners/LogAdminLoginTest.php`
- Create: `app/Listeners/LogAdminLogin.php`

- [ ] **Step 1: 编写监听器单元测试**

创建 `tests/Unit/Listeners/LogAdminLoginTest.php`:
```php
<?php

use App\Listeners\LogAdminLogin;
use App\Models\AdminUser;
use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

test('logs successful login event', function () {
    $user = AdminUser::factory()->create(['username' => 'admin']);
    
    $event = new Login('admin', $user, false);
    
    $listener = new LogAdminLogin();
    $listener->handle($event);

    expect(LoginLog::count())->toBe(1);
    
    $log = LoginLog::first();
    expect($log->status)->toBe('success')
        ->and($log->admin_user_id)->toBe($user->id)
        ->and($log->username)->toBe('admin');
});

test('logs failed login event with username', function () {
    $event = new Failed('admin', null, ['username' => 'nonexistent', 'password' => 'wrong']);
    
    $listener = new LogAdminLogin();
    $listener->handle($event);

    expect(LoginLog::count())->toBe(1);
    
    $log = LoginLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->admin_user_id)->toBeNull()
        ->and($log->username)->toBe('nonexistent')
        ->and($log->failure_reason)->toBe('invalid_credentials');
});

test('logs failed login event with email', function () {
    $event = new Failed('admin', null, ['email' => 'test@example.com', 'password' => 'wrong']);
    
    $listener = new LogAdminLogin();
    $listener->handle($event);

    $log = LoginLog::first();
    expect($log->username)->toBe('test@example.com');
});

test('logs failed login event with login field', function () {
    $event = new Failed('admin', null, ['login' => 'admin', 'password' => 'wrong']);
    
    $listener = new LogAdminLogin();
    $listener->handle($event);

    $log = LoginLog::first();
    expect($log->username)->toBe('admin');
});

test('ignores non-admin guard events', function () {
    $event = new Login('web', AdminUser::factory()->make(), false);
    
    $listener = new LogAdminLogin();
    $listener->handle($event);

    expect(LoginLog::count())->toBe(0);
});

test('records ip address and user agent', function () {
    $user = AdminUser::factory()->create();
    
    $this->app['request']->server->set('REMOTE_ADDR', '192.168.1.100');
    $this->app['request']->server->set('HTTP_USER_AGENT', 'Test Browser');
    
    $event = new Login('admin', $user, false);
    
    $listener = new LogAdminLogin();
    $listener->handle($event);

    $log = LoginLog::first();
    expect($log->ip_address)->toBe('192.168.1.100')
        ->and($log->user_agent)->toBe('Test Browser');
});
```

- [ ] **Step 2: 运行测试确认失败**

```bash
php artisan test --filter=LogAdminLoginTest
```

Expected: FAIL - LogAdminLogin class not found

- [ ] **Step 3: 创建 LogAdminLogin 监听器**

创建 `app/Listeners/LogAdminLogin.php`:
```php
<?php

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
            ?? $event->credentials['login']
            ?? null;
    }

    /**
     * 确定登录失败原因
     */
    private function determineFailureReason(Failed $event): string
    {
        // 简化版：统一返回 invalid_credentials
        // 实际可通过 RateLimiter::tooManyAttempts() 检测速率限制
        return 'invalid_credentials';
    }
}
```

- [ ] **Step 4: 运行测试确认通过**

```bash
php artisan test --filter=LogAdminLoginTest
```

Expected: 6 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Listeners/LogAdminLogin.php tests/Unit/Listeners/LogAdminLoginTest.php
git commit -m "feat: 创建 LogAdminLogin 监听器"
```

---

### Task 3.4: 注册事件监听器

**Files:**
- Modify: `app/Providers/EventServiceProvider.php`

- [ ] **Step 1: 注册事件监听器**

修改 `app/Providers/EventServiceProvider.php`:
```php
<?php

namespace App\Providers;

use App\Listeners\LogAdminLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        
        // 登录日志记录
        Login::class => [
            LogAdminLogin::class,
        ],
        Failed::class => [
            LogAdminLogin::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
```

- [ ] **Step 2: 编写集成测试**

修改 `tests/Feature/Auth/AdminLoginTest.php`，添加登录日志验证:
```php
test('logs successful login to database', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'password',
    ]);

    expect(LoginLog::count())->toBe(1);
    
    $log = LoginLog::first();
    expect($log->status)->toBe('success')
        ->and($log->admin_user_id)->toBe($user->id)
        ->and($log->username)->toBe('admin');
});

test('logs failed login to database', function () {
    $this->post('/admin/login', [
        'login' => 'nonexistent',
        'password' => 'wrong',
    ]);

    expect(LoginLog::count())->toBe(1);
    
    $log = LoginLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->admin_user_id)->toBeNull()
        ->and($log->failure_reason)->toBe('invalid_credentials');
});
```

- [ ] **Step 3: 运行集成测试**

```bash
php artisan test --filter=AdminLoginTest
```

Expected: 所有测试通过（包括新增的 2 个测试）

- [ ] **Step 4: Commit**

```bash
git add app/Providers/EventServiceProvider.php tests/Feature/Auth/AdminLoginTest.php
git commit -m "feat: 注册登录日志事件监听器"
```

---

### Task 3.5: Week 3 验收检查

**Files:**
- None (verification only)

- [ ] **Step 1: 运行所有测试**

```bash
php artisan test
```

Expected: 所有测试通过（至少 25 个测试）

- [ ] **Step 2: 手动验证登录日志**

```bash
php artisan tinker
>>> $user = \App\Models\AdminUser::first();
>>> Auth::guard('admin')->attempt(['username' => $user->username, 'password' => 'password']);
>>> \App\Models\LoginLog::latest()->first();
```

Expected: 看到新创建的登录日志记录

- [ ] **Step 3: 验证失败登录日志**

访问 http://localhost/admin/login，使用错误密码登录，然后：
```bash
php artisan tinker
>>> \App\Models\LoginLog::where('status', 'failed')->latest()->first();
```

Expected: 看到失败的登录日志记录

- [ ] **Step 4: 运行静态分析**

```bash
composer phpstan
```

Expected: No errors

- [ ] **Step 5: 格式化代码**

```bash
composer format
```

Week 3 完成。

---

## Week 4: 2FA 集成

### Task 4.1: 安装 2FA 插件

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: 安装 stephenjude/filament-two-factor-authentication**

```bash
composer require stephenjude/filament-two-factor-authentication
```

- [ ] **Step 2: 发布配置文件**

```bash
php artisan vendor:publish --tag="filament-two-factor-authentication-config"
```

- [ ] **Step 3: 验证迁移文件（不运行）**

```bash
ls -la vendor/stephenjude/filament-two-factor-authentication/database/migrations/
```

Expected: 看到插件的迁移文件

注意：我们的 admin_users 表已经包含 2FA 字段，不需要运行插件的迁移

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/filament-two-factor-authentication.php
git commit -m "build: 安装 stephenjude/filament-two-factor-authentication 插件"
```

---

### Task 4.2: 集成 2FA 到 AdminUser 模型（TDD）

**Files:**
- Modify: `app/Models/AdminUser.php`
- Create: `tests/Feature/Auth/TwoFactorAuthenticationTest.php`

- [ ] **Step 1: 编写 2FA 功能测试**

创建 `tests/Feature/Auth/TwoFactorAuthenticationTest.php`:
```php
<?php

use App\Models\AdminUser;

test('admin user can enable 2FA', function () {
    $user = AdminUser::factory()->create();
    
    // 初始状态：2FA 未启用
    expect($user->two_factor_confirmed_at)->toBeNull();
    
    // 启用 2FA
    $user->update([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);
    
    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('admin user factory can create user with 2FA enabled', function () {
    $user = AdminUser::factory()->withTwoFactor()->create();
    
    expect($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->not->toBeNull();
});

test('admin user has 2FA methods available', function () {
    $user = AdminUser::factory()->create();
    
    // 验证插件提供的方法存在
    expect(method_exists($user, 'confirmTwoFactorAuth'))->toBeTrue()
        ->and(method_exists($user, 'disableTwoFactorAuth'))->toBeTrue();
});
```

- [ ] **Step 2: 运行测试确认失败**

```bash
php artisan test --filter=TwoFactorAuthenticationTest
```

Expected: FAIL - 2FA methods not found

- [ ] **Step 3: 修改 AdminUser 模型添加 2FA trait**

修改 `app/Models/AdminUser.php`:
```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticatable;

class AdminUser extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use SoftDeletes;
    use TwoFactorAuthenticatable;  // 添加 2FA trait

    protected $guarded = [];

    /**
     * 隐藏的属性
     *
     * @var array<int, string>
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
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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

- [ ] **Step 4: 运行测试确认通过**

```bash
php artisan test --filter=TwoFactorAuthenticationTest
```

Expected: 3 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/AdminUser.php tests/Feature/Auth/TwoFactorAuthenticationTest.php
git commit -m "feat: 集成 2FA 功能到 AdminUser 模型"
```

---

### Task 4.3: 配置 2FA 插件

**Files:**
- Modify: `config/filament-two-factor-authentication.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] **Step 1: 配置 2FA 插件**

修改 `config/filament-two-factor-authentication.php`:
```php
<?php

return [
    /*
     * 2FA 默认状态：关闭（用户手动启用）
     */
    'enabled' => env('TWO_FACTOR_ENABLED', false),

    /*
     * 强制启用 2FA（Phase 1 不强制）
     */
    'force' => env('TWO_FACTOR_FORCE', false),

    /*
     * 恢复码数量
     */
    'recovery_codes_count' => 8,

    /*
     * QR Code 大小
     */
    'qr_code_size' => 200,
];
```

- [ ] **Step 2: 注册 2FA 插件到 Filament**

修改 `app/Providers/Filament/AdminPanelProvider.php`，在 `panel()` 方法中添加:
```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login(Login::class)
        ->authGuard('admin')
        ->authPasswordBroker('admin_users')
        ->plugin(
            \Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationPlugin::make()
        )
        // ... 其他配置
        ;
}
```

- [ ] **Step 3: 编写 2FA 配置测试**

添加到 `tests/Feature/Auth/TwoFactorAuthenticationTest.php`:
```php
test('2FA is not forced by default', function () {
    expect(config('filament-two-factor-authentication.force'))->toBeFalse();
});

test('2FA is not enabled by default', function () {
    expect(config('filament-two-factor-authentication.enabled'))->toBeFalse();
});

test('users without 2FA can login', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user, 'admin');
});
```

- [ ] **Step 4: 运行测试**

```bash
php artisan test --filter=TwoFactorAuthenticationTest
```

Expected: 6 tests PASS

- [ ] **Step 5: Commit**

```bash
git add config/filament-two-factor-authentication.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/Auth/TwoFactorAuthenticationTest.php
git commit -m "feat: 配置 2FA 插件（默认禁用，用户可选启用）"
```

---

### Task 4.4: 测试完整 2FA 流程

**Files:**
- Modify: `tests/Feature/Auth/TwoFactorAuthenticationTest.php`

- [ ] **Step 1: 添加完整 2FA 流程测试**

添加到 `tests/Feature/Auth/TwoFactorAuthenticationTest.php`:
```php
test('user with 2FA enabled is prompted for code', function () {
    $user = AdminUser::factory()->withTwoFactor()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'password',
    ]);

    // 应重定向到 2FA 验证页面（不是直接登录）
    // 注意：具体行为取决于插件实现
    // 这里我们验证用户还未完全认证
    expect($response->status())->not->toBe(302)
        ->or(fn() => $this->assertGuest('admin'));
});

test('can disable 2FA', function () {
    $user = AdminUser::factory()->withTwoFactor()->create();
    
    // 禁用 2FA
    $user->disableTwoFactorAuth();
    
    expect($user->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and($user->fresh()->two_factor_secret)->toBeNull();
});
```

- [ ] **Step 2: 运行测试**

```bash
php artisan test --filter=TwoFactorAuthenticationTest
```

Expected: 8 tests PASS

注意：如果测试失败，可能是插件行为与预期不同，需要调整测试或验证插件兼容性

- [ ] **Step 3: 手动验证 2FA**

```bash
php artisan tinker
>>> $user = \App\Models\AdminUser::factory()->create(['username' => 'test2fa', 'password' => 'password']);
>>> $user->confirmTwoFactorAuth('test-secret');
>>> exit
```

访问 http://localhost/admin/login，使用 'test2fa' 登录，验证是否提示输入 2FA 验证码

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Auth/TwoFactorAuthenticationTest.php
git commit -m "test: 添加完整 2FA 流程测试"
```

---

### Task 4.5: Week 4 验收检查

**Files:**
- None (verification only)

- [ ] **Step 1: 运行所有测试**

```bash
php artisan test
```

Expected: 所有测试通过（至少 33 个测试）

- [ ] **Step 2: 验证 2FA 兼容性**

如果测试失败，检查：
1. 插件版本是否与 Filament 5 兼容
2. 自定义登录页是否与插件冲突
3. 查看插件文档确认正确集成方式

- [ ] **Step 3: 运行静态分析**

```bash
composer phpstan
```

Expected: No errors

- [ ] **Step 4: 格式化代码**

```bash
composer format
```

Week 4 完成。如果 2FA 插件与自定义登录页有兼容性问题，记录问题并在 Week 5 解决。

---

## Week 5: 集成测试与边界场景

### Task 5.1: 速率限制测试

**Files:**
- Create: `tests/Feature/Auth/RateLimitingTest.php`

- [ ] **Step 1: 编写速率限制测试**

创建 `tests/Feature/Auth/RateLimitingTest.php`:
```php
<?php

use App\Models\LoginLog;

test('enforces rate limiting after 5 failed attempts', function () {
    // Filament 5 内置：5 次/分钟
    for ($i = 0; $i < 5; $i++) {
        $response = $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'wrong',
        ]);
        
        // 前 5 次应该返回验证错误
        $response->assertSessionHasErrors();
    }

    // 第 6 次应该被限流
    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'wrong',
    ]);

    $response->assertStatus(429); // Too Many Requests
});

test('logs rate limited attempts', function () {
    // 触发 5 次失败登录
    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'wrong',
        ]);
    }

    // 验证日志数量
    expect(LoginLog::where('status', 'failed')->count())->toBe(5);
});

test('rate limit resets after cooldown', function () {
    // 触发速率限制
    for ($i = 0; $i < 6; $i++) {
        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'wrong',
        ]);
    }

    // 等待冷却时间（1 分钟）
    $this->travel(61)->seconds();

    // 应该可以再次尝试
    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors(); // 返回验证错误，而非 429
});
```

- [ ] **Step 2: 运行测试**

```bash
php artisan test --filter=RateLimitingTest
```

Expected: 3 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Auth/RateLimitingTest.php
git commit -m "test: 添加速率限制测试"
```

---

### Task 5.2: 边界场景测试

**Files:**
- Create: `tests/Feature/Auth/EdgeCasesTest.php`

- [ ] **Step 1: 编写边界场景测试**

创建 `tests/Feature/Auth/EdgeCasesTest.php`:
```php
<?php

use App\Models\AdminUser;
use App\Models\LoginLog;

test('soft deleted user cannot login', function () {
    $user = AdminUser::factory()->create([
        'username' => 'deleted',
        'password' => 'password',
    ]);
    
    $user->delete(); // 软删除

    $response = $this->post('/admin/login', [
        'login' => 'deleted',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest('admin');
});

test('logs IPv6 addresses correctly', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);
    
    // 模拟 IPv6 地址
    $this->app['request']->server->set('REMOTE_ADDR', '2001:0db8:85a3:0000:0000:8a2e:0370:7334');

    Auth::guard('admin')->attempt([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $log = LoginLog::latest()->first();
    expect($log->ip_address)->toBe('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
});

test('handles empty user agent', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);
    
    $this->app['request']->server->set('HTTP_USER_AGENT', null);

    Auth::guard('admin')->attempt([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $log = LoginLog::latest()->first();
    expect($log->user_agent)->toBeNull();
});

test('username is case sensitive', function () {
    AdminUser::factory()->create([
        'username' => 'Admin',
        'password' => 'password',
    ]);

    // 小写 admin 应该失败
    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors();
});

test('email is case insensitive', function () {
    $user = AdminUser::factory()->create([
        'email' => 'Admin@Example.Com',
        'password' => 'password',
    ]);

    // 小写 email 应该成功
    $response = $this->post('/admin/login', [
        'login' => 'admin@example.com',
        'password' => 'password',
    ]);

    // Laravel 默认 email 查询是 case-insensitive (取决于数据库)
    // 如果使用 MySQL，这个测试可能通过
    // 如果使用 PostgreSQL，可能需要额外处理
    
    // 这里我们假设 email 是 case-insensitive
    expect($response->status())->toBe(302);
});

test('empty login field shows validation error', function () {
    $response = $this->post('/admin/login', [
        'login' => '',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['data.login']);
});

test('empty password field shows validation error', function () {
    $response = $this->post('/admin/login', [
        'login' => 'admin',
        'password' => '',
    ]);

    $response->assertSessionHasErrors(['data.password']);
});
```

- [ ] **Step 2: 运行测试**

```bash
php artisan test --filter=EdgeCasesTest
```

Expected: 7 tests PASS（部分测试可能需要根据实际行为调整）

- [ ] **Step 3: 调整失败的测试**

如果某些测试失败（如 email case-insensitive），根据实际行为调整测试或实现

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Auth/EdgeCasesTest.php
git commit -m "test: 添加边界场景测试"
```

---

### Task 5.3: 端到端集成测试

**Files:**
- Create: `tests/Feature/Auth/CompleteFlowTest.php`

- [ ] **Step 1: 编写完整流程测试**

创建 `tests/Feature/Auth/CompleteFlowTest.php`:
```php
<?php

use App\Models\AdminUser;
use App\Models\LoginLog;

test('complete authentication flow without 2FA', function () {
    // 1. 创建用户
    $user = AdminUser::factory()->create([
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    // 2. 访问受保护页面，应重定向到登录页
    $response = $this->get('/admin');
    $response->assertRedirect('/admin/login');

    // 3. 访问登录页
    $response = $this->get('/admin/login');
    $response->assertStatus(200);

    // 4. 使用 username 登录
    $response = $this->post('/admin/login', [
        'login' => 'testuser',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user, 'admin');

    // 5. 验证登录日志
    $log = LoginLog::latest()->first();
    expect($log->status)->toBe('success')
        ->and($log->admin_user_id)->toBe($user->id)
        ->and($log->username)->toBe('testuser');

    // 6. 访问受保护页面，应成功
    $response = $this->get('/admin');
    $response->assertStatus(200);

    // 7. 登出
    $response = $this->post('/admin/logout');
    $this->assertGuest('admin');
});

test('complete authentication flow with email login', function () {
    $user = AdminUser::factory()->create([
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $this->post('/admin/login', [
        'login' => 'test@example.com',
        'password' => 'password123',
    ]);

    $this->assertAuthenticatedAs($user, 'admin');
    
    $log = LoginLog::latest()->first();
    expect($log->username)->toBe('testuser'); // 优先记录 username
});

test('failed login attempts are logged correctly', function () {
    // 3 次失败尝试
    for ($i = 0; $i < 3; $i++) {
        $this->post('/admin/login', [
            'login' => 'nonexistent',
            'password' => 'wrong',
        ]);
    }

    expect(LoginLog::where('status', 'failed')->count())->toBe(3);
    
    $logs = LoginLog::where('status', 'failed')->get();
    foreach ($logs as $log) {
        expect($log->username)->toBe('nonexistent')
            ->and($log->failure_reason)->toBe('invalid_credentials');
    }
});
```

- [ ] **Step 2: 运行测试**

```bash
php artisan test --filter=CompleteFlowTest
```

Expected: 3 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Auth/CompleteFlowTest.php
git commit -m "test: 添加端到端集成测试"
```

---

### Task 5.4: 测试覆盖率检查

**Files:**
- None (verification only)

- [ ] **Step 1: 运行测试覆盖率报告**

```bash
php artisan test --coverage
```

Expected: 显示覆盖率统计

- [ ] **Step 2: 检查覆盖率目标**

验证以下目标：
- 整体覆盖率 ≥60%
- app/Models/AdminUser.php: ≥80%
- app/Models/LoginLog.php: ≥80%
- app/Listeners/LogAdminLogin.php: 100%
- app/Filament/Pages/Auth/Login.php: ≥80%

- [ ] **Step 3: 补充缺失的测试**

如果覆盖率未达标，识别未覆盖的代码路径并补充测试

- [ ] **Step 4: 再次运行覆盖率检查**

```bash
php artisan test --coverage --min=60
```

Expected: Coverage threshold met

- [ ] **Step 5: Commit 覆盖率改进**

如果添加了新测试：
```bash
git add tests/
git commit -m "test: 补充测试以达到覆盖率目标"
```

---

### Task 5.5: Week 5 验收检查

**Files:**
- None (verification only)

- [ ] **Step 1: 运行完整测试套件**

```bash
php artisan test
```

Expected: 所有测试通过（至少 45 个测试）

- [ ] **Step 2: 验证测试覆盖率**

```bash
php artisan test --coverage --min=60
```

Expected: Coverage ≥60%

- [ ] **Step 3: 运行静态分析**

```bash
composer phpstan
```

Expected: No errors

- [ ] **Step 4: 格式化代码**

```bash
composer format
```

- [ ] **Step 5: 检查所有 commits**

```bash
git log --oneline | head -20
```

Expected: 看到 Week 1-5 的所有 commits

Week 5 完成。

---

## Week 6: 文档与收尾

### Task 6.1: 编写用户文档

**Files:**
- Create: `docs/features/authentication.md`

- [ ] **Step 1: 创建认证功能文档**

创建 `docs/features/authentication.md`:
```markdown
# 认证功能使用指南

## 概述

FilamentAdmin 提供安全的管理员认证系统，支持灵活的登录方式和可选的双因素认证（2FA）。

## 功能特性

- **灵活登录**：支持用户名或邮箱登录
- **双因素认证（2FA）**：可选的 TOTP 验证
- **登录日志**：记录所有登录尝试（成功与失败）
- **速率限制**：防止暴力破解（5 次/分钟）
- **防枚举攻击**：统一的错误提示

---

## 管理员登录

### 登录方式

访问 `/admin/login`，可使用以下任一方式登录：

1. **用户名登录**
   ```
   用户名或邮箱: admin
   密码: your-password
   ```

2. **邮箱登录**
   ```
   用户名或邮箱: admin@example.com
   密码: your-password
   ```

### 速率限制

连续 5 次登录失败后，将被限制 1 分钟。请确保输入正确的凭据。

---

## 双因素认证（2FA）

### 启用 2FA

1. 登录管理面板
2. 点击右上角头像 → **个人资料**
3. 找到「双因素认证」部分
4. 点击「启用双因素认证」
5. 使用认证器应用（如 Google Authenticator、Authy）扫描 QR 码
6. 输入认证器生成的 6 位验证码确认
7. **保存恢复码**（共 8 个，请妥善保管）

### 使用 2FA 登录

启用 2FA 后，登录流程变为：

1. 输入用户名/邮箱和密码
2. 输入认证器应用生成的 6 位验证码
3. 完成登录

### 恢复码

如果无法访问认证器应用，可使用恢复码登录：

1. 在 2FA 验证页面，点击「使用恢复码」
2. 输入任意一个恢复码
3. 完成登录

**注意**：每个恢复码只能使用一次，用完后会失效。

### 禁用 2FA

1. 登录管理面板
2. 点击右上角头像 → **个人资料**
3. 找到「双因素认证」部分
4. 点击「禁用双因素认证」

---

## 登录日志

### 查看日志

登录日志功能已实现，UI 查看界面将在 Phase 2 提供。

当前可通过数据库查看：

```sql
SELECT * FROM login_logs ORDER BY created_at DESC LIMIT 10;
```

### 日志字段说明

| 字段 | 说明 |
|------|------|
| `admin_user_id` | 管理员 ID（失败登录为 null） |
| `username` | 登录使用的用户名/邮箱 |
| `status` | 登录状态（success / failed） |
| `ip_address` | 客户端 IP 地址 |
| `user_agent` | 浏览器信息 |
| `failure_reason` | 失败原因（如 invalid_credentials） |
| `created_at` | 登录时间 |

---

## 常见问题

### Q: 忘记密码怎么办？

A: Phase 1 暂未实现密码重置功能，请联系系统管理员重置密码。

### Q: 可以同时使用 username 和 email 登录吗？

A: 是的，系统会自动识别输入的是 username 还是 email。

### Q: 2FA 是强制的吗？

A: 不是。Phase 1 中 2FA 默认关闭，用户可自行选择启用。

### Q: 丢失 2FA 恢复码怎么办？

A: 请联系系统管理员在数据库中重置 2FA 设置：

```sql
UPDATE admin_users 
SET two_factor_secret = NULL, 
    two_factor_recovery_codes = NULL, 
    two_factor_confirmed_at = NULL 
WHERE id = <user_id>;
```

---

## 安全建议

1. **使用强密码**：至少 12 位，包含大小写字母、数字和符号
2. **启用 2FA**：显著提升账户安全性
3. **妥善保管恢复码**：打印或存储在安全位置
4. **定期检查登录日志**：发现异常登录及时处理
5. **不共享账号**：每个管理员使用独立账号

---

## 技术细节

### 认证守卫

系统使用独立的 `admin` guard，与普通用户认证隔离。

配置文件：`config/auth.php`

### 数据表

- `admin_users`: 管理员账号表
- `login_logs`: 登录日志表

### 事件监听

登录日志通过监听 Laravel 原生事件实现：

- `Illuminate\Auth\Events\Login`: 登录成功
- `Illuminate\Auth\Events\Failed`: 登录失败

---

## 下一步

- Phase 2 将提供登录日志 UI 查看界面
- Phase 3 将实现密码重置功能
- Phase 4 将支持基于角色的 2FA 强制策略
```

- [ ] **Step 2: Commit**

```bash
git add docs/features/authentication.md
git commit -m "docs: 添加认证功能使用指南"
```

---

### Task 6.2: 代码审查与重构

**Files:**
- Various (based on review findings)

- [ ] **Step 1: 审查 AdminUser 模型**

检查清单：
- [ ] 所有方法有 PHPDoc 注释（中文）
- [ ] 类型声明完整
- [ ] 无冗余代码
- [ ] 遵循 PSR-12

- [ ] **Step 2: 审查 LoginLog 模型**

检查清单：
- [ ] 所有方法有 PHPDoc 注释（中文）
- [ ] 类型声明完整
- [ ] 关系方法正确
- [ ] 遵循 PSR-12

- [ ] **Step 3: 审查 LogAdminLogin 监听器**

检查清单：
- [ ] 所有方法有 PHPDoc 注释（中文）
- [ ] 错误处理完善
- [ ] 可读性良好
- [ ] 遵循 PSR-12

- [ ] **Step 4: 审查自定义登录页**

检查清单：
- [ ] 所有方法有 PHPDoc 注释（中文）
- [ ] 安全性检查（防枚举攻击）
- [ ] 用户体验良好
- [ ] 遵循 PSR-12

- [ ] **Step 5: 如有需要，进行重构**

如果发现问题，创建新的 commits 修复

- [ ] **Step 6: Commit 重构改进**

```bash
git add .
git commit -m "refactor: 代码审查与重构改进"
```

---

### Task 6.3: 最终验收测试

**Files:**
- None (verification only)

- [ ] **Step 1: 运行完整测试套件**

```bash
php artisan test
```

Expected: 所有测试通过

- [ ] **Step 2: 验证测试覆盖率**

```bash
php artisan test --coverage --min=60
```

Expected: Coverage ≥60%

- [ ] **Step 3: 验证安全关键路径 100% 覆盖**

检查以下文件的覆盖率：
- `app/Filament/Pages/Auth/Login.php`
- `app/Listeners/LogAdminLogin.php`
- `app/Models/AdminUser.php` (认证相关方法)

- [ ] **Step 4: 运行 Larastan 静态分析**

```bash
composer phpstan
```

Expected: No errors (level 6)

- [ ] **Step 5: 运行 Pint 代码格式检查**

```bash
composer format-check
```

Expected: All files formatted

---

### Task 6.4: 创建种子数据

**Files:**
- Create: `database/seeders/AdminUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: 创建 AdminUserSeeder**

创建 `database/seeders/AdminUserSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * 运行种子数据
     */
    public function run(): void
    {
        // 创建默认管理员（仅开发环境）
        if (app()->environment('local')) {
            AdminUser::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'username' => 'admin',
                    'name' => '系统管理员',
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );

            $this->command->info('已创建默认管理员账号：');
            $this->command->info('  用户名: admin');
            $this->command->info('  邮箱: admin@example.com');
            $this->command->info('  密码: password');
        }

        // 创建测试用户（仅开发环境）
        if (app()->environment('local')) {
            AdminUser::factory()->count(5)->create();
            $this->command->info('已创建 5 个测试管理员账号');
        }
    }
}
```

- [ ] **Step 2: 注册 Seeder**

修改 `database/seeders/DatabaseSeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);
    }
}
```

- [ ] **Step 3: 运行 Seeder**

```bash
php artisan db:seed
```

Expected: 创建默认管理员和测试账号

- [ ] **Step 4: 测试种子数据**

```bash
php artisan tinker
>>> \App\Models\AdminUser::count();
>>> exit
```

Expected: 至少 6 个用户

- [ ] **Step 5: Commit**

```bash
git add database/seeders/
git commit -m "feat: 添加管理员账号种子数据"
```

---

### Task 6.5: 准备部署清单

**Files:**
- Create: `docs/deployment/phase-1-checklist.md`

- [ ] **Step 1: 创建部署清单**

创建 `docs/deployment/phase-1-checklist.md`:
```markdown
# Phase 1 部署清单

## 环境要求

- PHP 8.2+
- MySQL 8.0+ / PostgreSQL 14+
- Composer 2.x
- Node.js 18+ (for Filament assets)

## 部署步骤

### 1. 代码部署

```bash
git clone <repository-url>
cd filament-admin
composer install --no-dev --optimize-autoloader
```

### 2. 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env` 文件：

```env
APP_NAME=FilamentAdmin
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=filament_admin
DB_USERNAME=root
DB_PASSWORD=

# 2FA 配置（可选）
TWO_FACTOR_ENABLED=false
TWO_FACTOR_FORCE=false
```

### 3. 数据库迁移

```bash
php artisan migrate --force
```

### 4. 创建管理员账号

**生产环境请勿使用 Seeder**，手动创建：

```bash
php artisan tinker
>>> $user = \App\Models\AdminUser::create([
...     'username' => 'admin',
...     'email' => 'admin@yourdomain.com',
...     'name' => '系统管理员',
...     'password' => 'secure-password-here',
...     'email_verified_at' => now(),
... ]);
>>> exit
```

### 5. 缓存优化

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. 文件权限

```bash
chmod -R 755 storage bootstrap/cache
```

### 7. Web 服务器配置

Nginx 示例配置：

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/filament-admin/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 8. 验证部署

访问 `https://yourdomain.com/admin/login`：

- [ ] 登录页正常显示
- [ ] 可使用 username 登录
- [ ] 可使用 email 登录
- [ ] 登录成功后进入 Dashboard
- [ ] 个人资料页可访问
- [ ] 可启用/禁用 2FA

---

## 安全检查

- [ ] `APP_DEBUG=false` 已设置
- [ ] 数据库凭据安全
- [ ] 文件权限正确（不是 777）
- [ ] HTTPS 已启用
- [ ] 默认密码已修改
- [ ] `.env` 文件未提交到 Git

---

## 回滚计划

如需回滚：

```bash
php artisan migrate:rollback --step=2
```

这将回滚 `admin_users` 和 `login_logs` 表的迁移。

---

## 监控指标

部署后监控以下指标：

- 登录成功率
- 失败登录次数
- 速率限制触发次数
- 平均响应时间

---

## 故障排查

### 登录页 404

检查 Web 服务器配置，确保路由正确。

### 数据库连接失败

检查 `.env` 中的数据库配置。

### 2FA QR 码不显示

确保 `two_factor_*` 字段已存在于 `admin_users` 表。

---

## 联系方式

如遇问题，请联系开发团队。
```

- [ ] **Step 2: Commit**

```bash
git add docs/deployment/phase-1-checklist.md
git commit -m "docs: 添加 Phase 1 部署清单"
```

---

### Task 6.6: Week 6 最终验收

**Files:**
- None (verification only)

- [ ] **Step 1: 完整测试套件**

```bash
php artisan test --coverage --min=60
```

Expected: All tests pass, coverage ≥60%

- [ ] **Step 2: 静态分析**

```bash
composer phpstan
```

Expected: No errors (level 6)

- [ ] **Step 3: 代码格式**

```bash
composer format-check
```

Expected: All files properly formatted

- [ ] **Step 4: Git 提交历史检查**

```bash
git log --oneline --graph
```

Expected: 清晰的提交历史，每个 commit 都有中文消息

- [ ] **Step 5: 文档完整性检查**

验证以下文档存在：
- [ ] `docs/features/authentication.md`
- [ ] `docs/deployment/phase-1-checklist.md`
- [ ] `docs/superpowers/specs/2026-05-28-phase-1-authentication-design.md`

- [ ] **Step 6: 功能完整性检查**

手动验证：
- [ ] 管理员可使用 username 登录
- [ ] 管理员可使用 email 登录
- [ ] 防枚举攻击生效
- [ ] 登录成功写入 login_logs
- [ ] 登录失败写入 login_logs
- [ ] 速率限制正常工作（5 次/分钟）
- [ ] 可启用 2FA
- [ ] 可禁用 2FA
- [ ] 2FA 启用后登录需要验证码

- [ ] **Step 7: 创建 Phase 1 完成标签**

```bash
git tag -a v1.0.0-phase1 -m "Phase 1: 认证与基础设施 - 完成"
git push origin v1.0.0-phase1
```

---

## 验收标准

Phase 1 完成需满足以下所有条件：

### 功能完整性
- [x] 管理员可通过 username 或 email 登录
- [x] 防止用户名枚举攻击
- [x] 管理员可启用/禁用 2FA
- [x] 所有登录尝试记录到 `login_logs` 表
- [x] 速率限制正常工作（5 次/分钟）

### 测试质量
- [x] 整体覆盖率 ≥60%
- [x] 安全关键路径覆盖率 100%
- [x] Larastan level 6 无错误
- [x] Pint (PSR-12) 格式检查通过

### 文档完整
- [x] 技术设计文档（已完成）
- [x] 用户操作文档（登录、2FA 使用）
- [x] 部署清单文档
- [x] 代码注释（PHPDoc 标准，中文）

### 可部署
- [x] 迁移文件可重复运行（`php artisan migrate:fresh --seed`）
- [x] 无硬编码凭据或配置
- [x] Git 提交记录清晰（中文 commit message）

---

## 执行建议

### 时间分配

- Week 1 (5h): 测试框架搭建
- Week 2 (7h): AdminUser 模型 + 自定义登录页
- Week 3 (5h): 登录日志功能
- Week 4 (4h): 2FA 集成
- Week 5 (4h): 集成测试与边界场景
- Week 6 (3h): 文档与收尾

**总计**: 28 小时

### 工作节奏

建议每周工作 2-3 天，每次 2-3 小时，保持连续性：

- **不推荐**: 一周做 1 小时，另一周做 10 小时
- **推荐**: 每周均匀分布 4-5 小时

### 中断与恢复

如果中断超过 1 周：

1. 运行 `php artisan test` 确保环境正常
2. 回顾 `git log --oneline -10` 查看进度
3. 阅读上次未完成的 Task
4. 从下一个 Step 继续

---

## 下一步：Phase 2

Phase 1 完成后，准备 Phase 2（权限管理）规划：

1. 安装 spatie/laravel-permission
2. 安装 bezhansalleh/filament-shield
3. 设计角色权限体系
4. 实现权限管理 UI

---

## 附录：常用命令

```bash
# 测试
php artisan test
php artisan test --coverage
php artisan test --filter=LoginTest

# 代码质量
composer phpstan
composer format
composer format-check

# 数据库
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed

# 清理缓存
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

**计划编写完成时间**: 2026-05-28  
**预计完成时间**: 2026-07-09 (6 周)  
**维护者**: 开发团队
