# 权限体系功能文档

## 架构总览

```
Filament Shield 4.x          ←  自动生成权限点 + 提供 RoleResource UI
        ↓
spatie/laravel-permission     ←  底层权限存储（roles / permissions 表）
        ↓
BasePolicy 抽象基类            ←  统一权限点拼接逻辑（view_any_{resource}）
        ↓
Gate::before（AuthServiceProvider）  ←  超级管理员绕过所有 Policy 检查
```

### 各组件职责

| 组件 | 职责 |
|------|------|
| `spatie/laravel-permission` | 角色/权限的 CRUD、关联、检查底层实现 |
| `bezhansalleh/filament-shield` | 为每个 Resource/Page/Widget 自动生成权限点；提供角色管理 UI |
| `BasePolicy` | 抽象基类，子类继承后自动检查对应权限点，无需逐条手写 |
| `AuthServiceProvider` | 注册 Policy 映射；通过 `Gate::before` 实现超级管理员绕过 |
| `config/filament-admin.php` | 存放超级管理员角色名（默认 `super_admin`，可通过 `.env` 覆盖） |

---

## 权限命名规范

**格式：** `{action}_{resource_snake_case}`

| 权限点 | 说明 |
|--------|------|
| `view_any_admin_user` | 列表查看管理员 |
| `view_admin_user` | 查看单个管理员 |
| `create_admin_user` | 创建管理员 |
| `update_admin_user` | 更新管理员 |
| `delete_admin_user` | 删除管理员 |
| `delete_any_admin_user` | 批量删除管理员 |
| `restore_admin_user` | 恢复软删除管理员 |
| `restore_any_admin_user` | 批量恢复软删除管理员 |
| `force_delete_admin_user` | 强制删除管理员 |
| `force_delete_any_admin_user` | 批量强制删除管理员 |
| `view_any_login_log` | 列表查看登录日志 |
| `view_login_log` | 查看单条登录日志 |
| ... | 以此类推 |

**配置来源：** `config/filament-shield.php`
```php
'permissions' => [
    'separator' => '_',
    'case'      => 'snake',
    'generate'  => true,
],
```

> **重要：** 此配置与 `BasePolicy::resourceName()` 的拼接逻辑严格对齐。修改此配置会导致 Policy 权限点名称不匹配。

---

## 超级管理员机制

超级管理员通过 `Gate::before()` 拦截实现，拥有 `super_admin` 角色的用户会绕过所有 Policy 检查。

### 配置角色名

默认角色名为 `super_admin`，可通过 `.env` 覆盖：

```env
SUPER_ADMIN_ROLE=super_admin
```

### 实现原理

`app/Providers/AuthServiceProvider.php`：

```php
Gate::before(function (Authenticatable $user, string $ability) use ($superAdminRole) {
    if (! method_exists($user, 'hasRole')) {
        return null;  // 非 HasRoles 用户（如默认 User 模型）跳过
    }
    return $user->hasRole($superAdminRole) ? true : null;  // null = 继续后续检查
});
```

### 创建超级管理员

```bash
php artisan db:seed --class=SuperAdminSeeder
```

默认账号：`admin@example.com` / `password`

---

## 如何为新 Resource 生成权限

### 自动生成（推荐）

创建 Filament Resource 后运行 Shield 生成命令：

```bash
# 1. 创建 Resource
php artisan make:filament-resource Foo

# 2. 生成权限点
php artisan shield:generate --resource=FooResource --panel=admin

# 3. 在 Shield UI 给角色分配权限
# 访问 /admin/shield/roles，编辑对应角色，勾选新权限点
```

### 添加自定义 Policy（可选）

如需在自动生成的 Policy 基础上添加业务逻辑，创建继承 `BasePolicy` 的子类：

```php
// app/Policies/FooPolicy.php
namespace App\Policies;

class FooPolicy extends BasePolicy
{
    // 覆盖需要自定义逻辑的方法
    public function create(Authenticatable $user): bool
    {
        // 额外业务逻辑
        return parent::create($user) && $user->isVerified();
    }
}
```

然后在 `AuthServiceProvider.$policies` 注册：

```php
protected array $policies = [
    \App\Models\AdminUser::class => \App\Policies\AdminUserPolicy::class,
    \App\Models\LoginLog::class  => \App\Policies\LoginLogPolicy::class,
    \App\Models\Foo::class       => \App\Policies\FooPolicy::class,  // 新增
];
```

---

## 角色管理 UI

Shield 自带 RoleResource，路径：`/admin/shield/roles`

**导航位置：** 系统管理 → 角色管理

**功能：**
- 创建、编辑、删除角色
- 为角色分配权限（按 Resource/Page/Widget 分组的 CheckboxList）
- 查看角色下的成员数量

---

## 常见操作

### 代码中创建角色并分配权限

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// 清除缓存（重要）
app(PermissionRegistrar::class)->forgetCachedPermissions();

// 创建角色（必须指定 guard_name）
$role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);

// 分配权限
$role->givePermissionTo([
    'view_any_admin_user',
    'view_admin_user',
]);

// 给管理员绑定角色
$admin->assignRole($role);
```

### 检查权限

```php
// 检查当前登录用户
$user->can('view_any_admin_user');
$user->hasRole('editor');
$user->hasPermissionTo('create_admin_user');

// 在 Blade 中
@can('view_any_admin_user')
    ...
@endcan
```

---

## 故障排查

### 权限不生效

**检查清单：**

1. **guard_name 是否为 `admin`**

```php
Role::where('name', 'editor')->first()->guard_name; // 应为 'admin'
```

2. **权限缓存是否清除**

```php
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
```

或清除 Redis 缓存：
```bash
php artisan cache:clear
```

3. **Shield 配置的 case/separator 是否被覆盖**

确认 `config/filament-shield.php` 中：
```php
'permissions' => ['separator' => '_', 'case' => 'snake']
```

4. **Policy 映射是否注册**

确认 `AuthServiceProvider.$policies` 中有对应模型的映射。

5. **权限点名称是否拼写正确**

```bash
php artisan tinker
>>> \Spatie\Permission\Models\Permission::where('guard_name', 'admin')->pluck('name')
```

### 测试中权限缓存污染

每个测试的 `beforeEach` 必须清除缓存：

```php
beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});
```
