# 权限体系 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现完整的角色权限系统，包含 Spatie Permission 集成、Filament Shield 自动权限、基于 Shield 自带的角色管理界面、BasePolicy 和超级管理员机制。

**Architecture:** 使用 spatie/laravel-permission 作为底层权限存储，bezhansalleh/filament-shield 4.x 自动为每个 Filament Resource/Page/Widget 生成权限点并提供自带的 RoleResource。超级管理员通过 `Gate::before()` 绕过所有权限检查。所有 Resource 的 Policy 继承 BasePolicy 抽象基类。Shield 权限命名格式统一配置为 `snake_case` + `_` 分隔符（如 `view_any_admin_user`），与 BasePolicy 拼接逻辑严格对齐。

**Tech Stack:** spatie/laravel-permission ^6.0, bezhansalleh/filament-shield ^4.0, Laravel 13, Filament 5, Pest

**框架版本说明：**
- Laravel 11+ 已移除框架自带的 `Illuminate\Foundation\Support\Providers\AuthServiceProvider` 基类，本项目（Laravel 13）必须直接继承 `Illuminate\Support\ServiceProvider`，在 `boot()` 中手动调用 `Gate::policy()` 注册 Policy 映射。
- Filament 5 表单 API 使用 `Filament\Schemas\Schema` + `->components([])`，**不是** Filament 3.x 的 `Filament\Forms\Form` + `->schema([])`。
- Filament Shield 4.x 自带 `BezhanSalleh\FilamentShield\Resources\Roles\RoleResource`，启用插件即可，**不要**自己再写一份 RoleResource。

---

## 文件结构

**新建文件：**
- `config/filament-admin.php` —— 项目核心配置（首次创建）
- `app/Providers/AuthServiceProvider.php` —— 注册 Policy + 超级管理员 Gate::before
- `app/Policies/BasePolicy.php` —— Policy 抽象基类
- `app/Policies/AdminUserPolicy.php` —— AdminUser 的 Policy
- `app/Policies/LoginLogPolicy.php` —— LoginLog 的 Policy（已有 LoginLog 模型）
- `database/seeders/SuperAdminSeeder.php` —— 初始超级管理员种子
- `tests/Feature/Permissions/SuperAdminTest.php`
- `tests/Feature/Permissions/PolicyTest.php`
- `docs/features/permissions.md`
- `docs/development/custom-permissions.md`

**修改文件：**
- `composer.json` —— 新增依赖
- `bootstrap/providers.php` —— 注册 AuthServiceProvider
- `app/Models/AdminUser.php` —— 加 `HasRoles` Trait
- `config/permission.php` —— 发布后会生成（无需手改，guard 通过 Role/Permission 创建时显式传 `guard_name`）
- `config/filament-shield.php` —— 发布后将 `case` 改为 `snake`，`separator` 改为 `_`
- `app/Providers/Filament/AdminPanelProvider.php` —— 注册 FilamentShieldPlugin

---

### Task 0: 创建 filament-admin 核心配置文件

**Files:** `config/filament-admin.php`

- [ ] **Step 1: 创建配置文件**

创建 `config/filament-admin.php`：

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 超级管理员角色
    |--------------------------------------------------------------------------
    |
    | 拥有此角色的管理员将绕过所有权限检查（通过 Gate::before 实现）。
    | 可通过 .env 中的 SUPER_ADMIN_ROLE 覆盖。
    |
    */
    'super_admin_role' => env('SUPER_ADMIN_ROLE', 'super_admin'),

    /*
    |--------------------------------------------------------------------------
    | 日志保留天数（占位，后续功能域使用）
    |--------------------------------------------------------------------------
    */
    'log_retention_days' => env('LOG_RETENTION_DAYS', 90),
];
```

- [ ] **Step 2: 验证配置可读**

运行：`php artisan tinker --execute="echo config('filament-admin.super_admin_role');"`
预期输出：`super_admin`

- [ ] **Step 3: 提交**

```bash
git add config/filament-admin.php
git commit -m "feat: 创建 filament-admin 核心配置文件"
```

---

### Task 1: 安装 Spatie Permission 并集成 AdminUser

**Files:** `composer.json`, `config/permission.php`（自动生成）, `app/Models/AdminUser.php`

- [ ] **Step 1: 安装包**

```bash
composer require spatie/laravel-permission:^6.0
```

预期：包安装成功，`composer.lock` 更新。

- [ ] **Step 2: 发布迁移和配置**

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

预期生成：`database/migrations/xxxx_create_permission_tables.php`、`config/permission.php`。

- [ ] **Step 3: 运行迁移**

```bash
php artisan migrate
```

预期：roles / permissions / model_has_roles / model_has_permissions / role_has_permissions 5 张表创建成功。

- [ ] **Step 4: 修改 AdminUser 加入 HasRoles Trait**

修改 `app/Models/AdminUser.php`，在 `use` 区块加入：

```php
use Spatie\Permission\Traits\HasRoles;
```

在 class 内部 trait 使用区块加入：

```php
class AdminUser extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory;

    use HasRoles;              // 新增：Spatie Permission 角色/权限关联
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    // ... 其余代码保持不变
}
```

- [ ] **Step 5: 写最小验证测试**

创建 `tests/Feature/Permissions/HasRolesTraitTest.php`：

```php
<?php

use App\Models\AdminUser;
use Spatie\Permission\Models\Role;

it('AdminUser 可以分配角色', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $admin = AdminUser::factory()->create();

    $admin->assignRole($role);

    expect($admin->hasRole('editor'))->toBeTrue();
});
```

- [ ] **Step 6: 运行测试**

```bash
php artisan test tests/Feature/Permissions/HasRolesTraitTest.php
```

预期：1 passed。

- [ ] **Step 7: 提交**

```bash
git add composer.json composer.lock config/permission.php database/migrations app/Models/AdminUser.php tests/Feature/Permissions/HasRolesTraitTest.php
git commit -m "feat: 集成 spatie/laravel-permission，AdminUser 加 HasRoles Trait"
```

---

### Task 2: 超级管理员机制（Laravel 13 风格 AuthServiceProvider）

**Files:** `app/Providers/AuthServiceProvider.php`, `bootstrap/providers.php`, `database/seeders/SuperAdminSeeder.php`, `tests/Feature/Permissions/SuperAdminTest.php`

**重要说明：** Laravel 11+ 已移除框架自带的 `Illuminate\Foundation\Support\Providers\AuthServiceProvider` 基类。必须直接继承 `Illuminate\Support\ServiceProvider`，在 `boot()` 中手动用 `Gate::policy()` 注册 Policy 映射。

- [ ] **Step 1: 创建 AuthServiceProvider**

创建 `app/Providers/AuthServiceProvider.php`：

```php
<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * 授权服务提供者
 *
 * Laravel 11+ 已移除框架自带的 AuthServiceProvider 基类，
 * 这里直接继承 ServiceProvider，在 boot() 中手动注册 Policy 与 Gate::before。
 *
 * Policy 映射会在 boot() 中通过 Gate::policy() 注册。
 * 超级管理员通过 Gate::before() 绕过所有权限检查。
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Policy 映射表
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        // Task 4 会填入：App\Models\AdminUser::class => App\Policies\AdminUserPolicy::class,
    ];

    public function boot(): void
    {
        // 注册所有 Policy 映射
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 超级管理员绕过所有权限检查
        $superAdminRole = config('filament-admin.super_admin_role', 'super_admin');

        Gate::before(function (Authenticatable $user, string $ability) use ($superAdminRole) {
            // 防御：非 HasRoles 用户（如普通 User 模型）跳过判断
            if (! method_exists($user, 'hasRole')) {
                return null;
            }

            return $user->hasRole($superAdminRole) ? true : null;
        });
    }
}
```

- [ ] **Step 2: 注册 Provider**

修改 `bootstrap/providers.php`：

```php
<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    AdminPanelProvider::class,
];
```

- [ ] **Step 3: 创建 SuperAdminSeeder**

创建 `database/seeders/SuperAdminSeeder.php`：

```php
<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 超级管理员种子
 *
 * 创建 super_admin 角色（admin guard）并创建首个超级管理员账号。
 * 默认账号：admin@example.com / password
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 清除 Spatie Permission 缓存，确保角色创建生效
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleName = config('filament-admin.super_admin_role', 'super_admin');

        $role = Role::firstOrCreate([
            'name'       => $roleName,
            'guard_name' => 'admin',
        ]);

        $admin = AdminUser::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'username'          => 'admin',
                'name'              => '超级管理员',
                'password'          => 'password', // AdminUser 的 hashed cast 会自动 hash
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole($role);

        $this->command->info("✅ 超级管理员已创建：{$admin->email} / password");
    }
}
```

- [ ] **Step 4: 运行 Seeder 验证**

```bash
php artisan db:seed --class=SuperAdminSeeder
```

预期输出：`✅ 超级管理员已创建：admin@example.com / password`

- [ ] **Step 5: 写超级管理员测试**

创建 `tests/Feature/Permissions/SuperAdminTest.php`：

```php
<?php

use App\Models\AdminUser;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

it('超级管理员可绕过所有权限检查', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    expect(Gate::allows('viewAny', AdminUser::class))->toBeTrue()
        ->and(Gate::allows('create', AdminUser::class))->toBeTrue()
        ->and(Gate::allows('any.random.ability', new AdminUser()))->toBeTrue();
});

it('普通管理员未分配权限时无法通过 Gate', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    // 没有任何角色和权限，Gate::before 返回 null（不拦截），落入 Policy 默认拒绝
    expect(Gate::allows('viewAny', AdminUser::class))->toBeFalse();
});
```

- [ ] **Step 6: 运行测试**

```bash
php artisan test tests/Feature/Permissions/SuperAdminTest.php
```

预期：2 passed。

- [ ] **Step 7: 提交**

```bash
git add app/Providers/AuthServiceProvider.php bootstrap/providers.php database/seeders/SuperAdminSeeder.php tests/Feature/Permissions/SuperAdminTest.php
git commit -m "feat: 实现 Laravel 13 风格 AuthServiceProvider 与超级管理员 Gate::before"
```

---

### Task 3: 安装 Filament Shield 4.x 并配置 snake_case 权限命名

**Files:** `composer.json`, `config/filament-shield.php`, `app/Providers/Filament/AdminPanelProvider.php`

**关键设计：** Shield 4.x 默认使用 PascalCase + `:` 分隔符（如 `ViewAny:AdminUser`），但本项目 BasePolicy 使用 snake_case 拼接（如 `view_any_admin_user`）。必须在 `config/filament-shield.php` 中把 `permissions.case` 改为 `snake`、`permissions.separator` 改为 `_`，保证二者一致。

- [ ] **Step 1: 安装包**

```bash
composer require bezhansalleh/filament-shield:^4.0
```

预期：包安装成功。

- [ ] **Step 2: 发布配置**

```bash
php artisan vendor:publish --tag=filament-shield-config
```

预期生成：`config/filament-shield.php`。

- [ ] **Step 3: 修改 Shield 配置**

修改 `config/filament-shield.php`：

```php
// 1. 设置 auth_provider_model 为 AdminUser
'auth_provider_model' => \App\Models\AdminUser::class,

// 2. 权限命名风格改为 snake_case + 下划线分隔（与 BasePolicy 拼接对齐）
'permissions' => [
    'separator' => '_',
    'case'      => 'snake',
    'generate'  => true,
],

// 3. 资源 subject 用 model（默认）
'resources' => [
    'subject' => 'model',
    // 其余保持默认
],

// 4. Pages 和 Widgets 排除项保持默认（已含 Dashboard / AccountWidget / FilamentInfoWidget）
```

- [ ] **Step 4: 在 AdminPanelProvider 注册 Shield Plugin**

修改 `app/Providers/Filament/AdminPanelProvider.php`，添加 use 和 plugin：

```php
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
```

在 `->plugin(TwoFactorAuthenticationPlugin::make()...)` 之后加：

```php
->plugin(
    FilamentShieldPlugin::make()
        ->navigationGroup('系统管理')
        ->navigationLabel('角色管理')
)
```

- [ ] **Step 5: 运行 Shield Setup（非交互方式）**

Shield 4.x 用 `shield:setup` 命令完成初始化。本项目用 `shield:install` 针对单个 panel：

```bash
php artisan shield:install admin --fresh
```

预期：根据当前 Resource/Page/Widget 生成 Policy 和 Permission 记录。如需重新生成，加 `--fresh`。

> 注意：`shield:install {panel}` 中的 `admin` 是本项目 Panel ID（见 `AdminPanelProvider::panel()` 中的 `->id('admin')`）。

- [ ] **Step 6: 验证权限点已生成**

```bash
php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::where('guard_name','admin')->pluck('name')->implode(\"\n\");"
```

预期输出包含（命名为 snake_case）：

```
view_any_admin_user
view_admin_user
create_admin_user
update_admin_user
delete_admin_user
...
view_any_login_log
...
```

> 如果输出仍是 PascalCase，说明 Step 3 配置没生效，需重跑 `php artisan config:clear` 后重新执行 Step 5。

- [ ] **Step 7: 重新分配超级管理员角色（拿到所有新权限）**

```bash
php artisan db:seed --class=SuperAdminSeeder
```

- [ ] **Step 8: 验证 Shield 自带 RoleResource 可访问**

启动开发服务器并以 admin@example.com / password 登录，访问 `/admin/shield/roles`，应能看到 Shield 自带的角色管理界面。

- [ ] **Step 9: 提交**

```bash
git add composer.json composer.lock config/filament-shield.php app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: 集成 filament-shield 4.x，权限命名统一为 snake_case"
```

---

### Task 4: BasePolicy 与 AdminUserPolicy / LoginLogPolicy

**Files:** `app/Policies/BasePolicy.php`, `app/Policies/AdminUserPolicy.php`, `app/Policies/LoginLogPolicy.php`, `app/Providers/AuthServiceProvider.php`（追加 policies 映射）, `tests/Feature/Permissions/PolicyTest.php`

**关键说明：** Shield 4.x 默认会在 `app_path('Policies')` 下自动生成 Policy 文件（Step 5 已生成）。本步骤要做的是：

1. 创建 BasePolicy 抽象基类
2. 用我们自己的 BasePolicy 子类**覆盖** Shield 自动生成的 Policy（更易扩展和复用）
3. 在 AuthServiceProvider 显式注册映射（不依赖 Laravel 的自动发现，保证一致性）

- [ ] **Step 1: 创建 BasePolicy**

创建 `app/Policies/BasePolicy.php`：

```php
<?php

namespace App\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 基础 Policy 抽象类
 *
 * 所有 Resource Policy 继承此类，默认实现检查 Spatie Permission 权限点。
 * 权限点命名格式：{action}_{resource_snake_case}
 *
 * 例如 AdminUserPolicy 的 viewAny 检查 view_any_admin_user 权限点。
 *
 * 命名格式与 Filament Shield 4.x 配置严格对齐：
 *   config('filament-shield.permissions.case')      = 'snake'
 *   config('filament-shield.permissions.separator') = '_'
 *
 * 超级管理员通过 Gate::before（AuthServiceProvider）拦截，不会进入 Policy。
 */
abstract class BasePolicy
{
    /**
     * 获取资源名称（用于权限点拼接）
     *
     * 子类可覆盖此方法自定义权限点前缀，默认从类名推断：
     * AdminUserPolicy -> admin_user
     * LoginLogPolicy  -> login_log
     */
    protected function resourceName(): string
    {
        $class = class_basename(static::class);

        return str($class)->replaceLast('Policy', '')->snake()->value();
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $user->can("view_any_{$this->resourceName()}");
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return $user->can("view_{$this->resourceName()}");
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can("create_{$this->resourceName()}");
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return $user->can("update_{$this->resourceName()}");
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return $user->can("delete_{$this->resourceName()}");
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $user->can("delete_any_{$this->resourceName()}");
    }

    public function restore(Authenticatable $user, Model $model): bool
    {
        return $user->can("restore_{$this->resourceName()}");
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return $user->can("restore_any_{$this->resourceName()}");
    }

    public function forceDelete(Authenticatable $user, Model $model): bool
    {
        return $user->can("force_delete_{$this->resourceName()}");
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return $user->can("force_delete_any_{$this->resourceName()}");
    }
}
```

> Policy 方法清单参照 Shield 4.x 的 `policies.methods` 默认配置，覆盖 viewAny/view/create/update/delete/deleteAny/restore/restoreAny/forceDelete/forceDeleteAny。

- [ ] **Step 2: 创建 AdminUserPolicy**

创建（或覆盖 Shield 自动生成的）`app/Policies/AdminUserPolicy.php`：

```php
<?php

namespace App\Policies;

use App\Models\AdminUser;

/**
 * 管理员用户 Policy
 *
 * 权限点前缀为 admin_user（由 BasePolicy::resourceName() 自动推断）。
 * 完整权限点：view_any_admin_user / view_admin_user / create_admin_user / ...
 */
class AdminUserPolicy extends BasePolicy
{
    // 无需重写任何方法，全部继承自 BasePolicy
}
```

- [ ] **Step 3: 创建 LoginLogPolicy**

创建（或覆盖 Shield 自动生成的）`app/Policies/LoginLogPolicy.php`：

```php
<?php

namespace App\Policies;

/**
 * 登录日志 Policy
 *
 * 权限点前缀为 login_log。
 */
class LoginLogPolicy extends BasePolicy
{
    // 无需重写任何方法
}
```

- [ ] **Step 4: 在 AuthServiceProvider 注册 Policy 映射**

修改 `app/Providers/AuthServiceProvider.php` 的 `$policies` 数组：

```php
protected array $policies = [
    \App\Models\AdminUser::class => \App\Policies\AdminUserPolicy::class,
    \App\Models\LoginLog::class  => \App\Policies\LoginLogPolicy::class,
];
```

- [ ] **Step 5: 写 Policy 测试**

创建 `tests/Feature/Permissions/PolicyTest.php`：

```php
<?php

use App\Models\AdminUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // 测试间清除权限缓存
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('拥有 view_any_admin_user 权限的角色可以列表查看管理员', function () {
    Permission::firstOrCreate([
        'name'       => 'view_any_admin_user',
        'guard_name' => 'admin',
    ]);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $role->givePermissionTo('view_any_admin_user');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    expect($admin->can('viewAny', AdminUser::class))->toBeTrue();
});

it('没有权限的管理员无法创建其他管理员', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    expect($admin->can('create', AdminUser::class))->toBeFalse();
});

it('拥有 update 权限但无 delete 权限的角色可以更新但不能删除', function () {
    Permission::firstOrCreate(['name' => 'update_admin_user', 'guard_name' => 'admin']);
    $role = Role::create(['name' => 'updater', 'guard_name' => 'admin']);
    $role->givePermissionTo('update_admin_user');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);
    $target = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin');

    expect($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('delete', $target))->toBeFalse();
});
```

- [ ] **Step 6: 运行测试**

```bash
php artisan test tests/Feature/Permissions/
```

预期：5 tests passed（HasRolesTraitTest 1 + SuperAdminTest 2 + PolicyTest 3）。

- [ ] **Step 7: 提交**

```bash
git add app/Policies app/Providers/AuthServiceProvider.php tests/Feature/Permissions/PolicyTest.php
git commit -m "feat: 创建 BasePolicy 抽象基类与 AdminUser/LoginLog Policy"
```

---

### Task 5: 启用 Shield 自带 RoleResource 并配置

**Files:** `app/Providers/Filament/AdminPanelProvider.php`

**关键设计变更：** Filament Shield 4.x 已自带 `BezhanSalleh\FilamentShield\Resources\Roles\RoleResource`，启用插件后会自动出现在导航中。**不要**自己再写一份 RoleResource（会和自带的冲突，且违反"能用框架自带就用框架自带"原则）。

如需自定义 UI，可用 `php artisan shield:publish --panel=admin` 把 Shield 的 RoleResource 发布到本地 `app/` 目录再修改。本计划第一版直接使用自带界面。

- [ ] **Step 1: 确认 Shield Plugin 已在 Task 3 中注册并配置导航**

回顾 `app/Providers/Filament/AdminPanelProvider.php`，确认存在：

```php
->plugin(
    FilamentShieldPlugin::make()
        ->navigationGroup('系统管理')
        ->navigationLabel('角色管理')
)
```

如未配置，补上。

- [ ] **Step 2: 启动开发服务器验证**

```bash
php artisan serve
```

浏览器访问 `http://localhost:8000/admin`，登录 `admin@example.com / password`：

- 左侧导航出现"系统管理 → 角色管理"
- 点击进入 `/admin/shield/roles`，应看到 super_admin 角色
- 编辑 super_admin 角色，应看到完整的权限分配 UI（按 Resource/Page/Widget 分组的 CheckboxList）

- [ ] **Step 3: 编写 RoleResource 访问测试**

创建 `tests/Feature/Permissions/RoleResourceTest.php`：

```php
<?php

use App\Models\AdminUser;
use Spatie\Permission\Models\Role;

it('超级管理员可以访问 Shield 自带的角色管理页面', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    $response = $this->get('/admin/shield/roles');

    $response->assertSuccessful();
});

it('无权限的管理员访问角色管理页面被拦截', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = $this->get('/admin/shield/roles');

    // 没有 view_any_role 权限时，Filament 通常返回 403
    $response->assertForbidden();
});
```

- [ ] **Step 4: 运行测试**

```bash
php artisan test tests/Feature/Permissions/RoleResourceTest.php
```

预期：2 passed。

- [ ] **Step 5: 提交**

```bash
git add tests/Feature/Permissions/RoleResourceTest.php
git commit -m "feat: 启用 Shield 自带 RoleResource 并验证访问控制"
```

---

### Task 6: 功能文档

**Files:** `docs/features/permissions.md`, `docs/development/custom-permissions.md`

- [ ] **Step 1: 创建 `docs/features/permissions.md`**

完整内容应覆盖：

1. **架构总览** —— Spatie Permission（底层存储）+ Filament Shield（权限自动生成 + UI）+ BasePolicy（统一拼接逻辑）+ Gate::before（超级管理员）
2. **权限命名规范** —— `{action}_{resource_snake_case}`（如 `view_any_admin_user`），与 `config/filament-shield.php` 中的 `case: snake`、`separator: _` 严格对应
3. **核心保留角色** —— `super_admin`（可在 `config/filament-admin.php` 中配置角色名）
4. **超级管理员机制** —— `AuthServiceProvider::boot()` 中的 `Gate::before()` 拦截，hasRole 检查通过即放行所有 ability
5. **如何为新 Resource 生成权限** —— `php artisan shield:generate --resource=XxxResource --panel=admin`
6. **角色管理 UI** —— Shield 自带 RoleResource，路径 `/admin/shield/roles`
7. **常见操作** —— 创建角色、分配权限、绑定管理员（在 Shield UI 或代码中）
8. **故障排查** —— 权限不生效时检查：guard_name 是否为 admin、权限缓存是否清除（`PermissionRegistrar::forgetCachedPermissions()`）、shield 配置 case/separator 是否被覆盖

- [ ] **Step 2: 创建 `docs/development/custom-permissions.md`**

开发者指南，5 步为新 Resource 添加权限：

1. 创建 Filament Resource：`php artisan make:filament-resource Foo`
2. 运行 Shield 生成命令：`php artisan shield:generate --resource=FooResource --panel=admin`
3. （可选）创建自定义 Policy：在 `app/Policies/FooPolicy.php` 中继承 `BasePolicy`
4. （可选）在 `AuthServiceProvider::$policies` 中注册映射（如果使用自定义 Policy）
5. 在 Shield UI 中给角色分配新权限点，验证生效

附：**插件如何注册自己的 Resource 权限**（与功能域 7 插件系统对接）

- [ ] **Step 3: 提交**

```bash
git add docs/features/permissions.md docs/development/custom-permissions.md
git commit -m "docs: 添加权限体系功能文档与开发者指南"
```

---

### Task 7: 收尾验证

**Files:** 无新文件

- [ ] **Step 1: 代码格式检查**

```bash
./vendor/bin/pint
```

预期：所有文件通过，无 diff。

- [ ] **Step 2: 静态分析**

```bash
./vendor/bin/phpstan analyse
```

预期：无错误（level 6 已在 `phpstan.neon` 中配置，无需重复指定）。

- [ ] **Step 3: 完整测试**

```bash
php artisan test
```

预期：所有测试通过，本功能域至少 8 个测试通过（HasRolesTraitTest 1 + SuperAdminTest 2 + PolicyTest 3 + RoleResourceTest 2）。

- [ ] **Step 4: 检查测试覆盖率**

```bash
php artisan test --coverage --min=50
```

预期：覆盖率 ≥ 50%，命令退出码 0。

- [ ] **Step 5: 打 Git Tag**

```bash
git tag v0.2.0-权限体系
git push origin v0.2.0-权限体系
```

---

## 风险与降级方案

| 风险 | 应对 |
|------|------|
| `shield:install admin --fresh` 命令签名在 Shield 4.x 不同 | 改用交互式 `php artisan shield:setup --fresh` 并按提示选择 admin panel |
| Shield 生成的 Policy 与手写 BasePolicy 子类冲突 | 删除 Shield 自动生成的 Policy，保留 BasePolicy 子类。或反过来：删除子类，使用 Shield 生成的 Policy（但失去 BasePolicy 复用价值） |
| Shield 自带 RoleResource UI 不满足需求 | `php artisan shield:publish --panel=admin` 发布到本地后修改 |
| Filament 5 的 Schema API 与 Shield 4.x 内置 Resource 不兼容 | Shield 4.x README 明确支持 Filament 4.x & 5.x，如遇问题升级到最新补丁版本 |
| 测试中权限缓存未清导致跨测试污染 | 每个测试 `beforeEach` 调用 `PermissionRegistrar::forgetCachedPermissions()` |

---

## 完成定义

- [ ] 所有 7 个 Task 全部完成
- [ ] `php artisan test` 全绿
- [ ] `./vendor/bin/pint` 无 diff
- [ ] `./vendor/bin/phpstan analyse` 通过
- [ ] 测试覆盖率 ≥ 50%
- [ ] 文档可读且无 TBD
- [ ] Git Tag `v0.2.0-权限体系` 已打
