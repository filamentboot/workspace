# 自定义权限开发指南

本文档面向开发者，说明如何为新的 Filament Resource 添加权限控制。

## 5 步为新 Resource 添加权限

### Step 1: 创建 Filament Resource

```bash
php artisan make:filament-resource Post
```

这会在 `app/Filament/Resources/PostResource.php` 生成基础 Resource 文件。

### Step 2: 运行 Shield 生成命令

```bash
php artisan shield:generate --resource=PostResource --panel=admin
```

Shield 会自动：
- 在数据库 `permissions` 表中插入该 Resource 的所有权限点（`view_any_post`、`view_post`、`create_post` 等）
- 在 `app/Policies/PostPolicy.php` 生成 Policy 文件

### Step 3: （可选）用 BasePolicy 子类替换 Shield 生成的 Policy

如果需要复用 BasePolicy 的统一权限拼接逻辑：

```php
// app/Policies/PostPolicy.php
namespace App\Policies;

/**
 * 文章 Policy
 *
 * 权限点前缀为 post（由 BasePolicy::resourceName() 自动推断）。
 */
class PostPolicy extends BasePolicy
{
    // 无自定义逻辑时无需重写任何方法
}
```

### Step 4: （可选）在 AuthServiceProvider 注册 Policy 映射

如果使用了自定义 Policy（Step 3），在 `app/Providers/AuthServiceProvider.php` 中注册：

```php
protected array $policies = [
    \App\Models\AdminUser::class => \App\Policies\AdminUserPolicy::class,
    \App\Models\LoginLog::class  => \App\Policies\LoginLogPolicy::class,
    \App\Models\Post::class      => \App\Policies\PostPolicy::class,  // 新增
];
```

> 如果直接使用 Shield 自动生成的 Policy（不创建 BasePolicy 子类），Laravel 的自动 Policy 发现机制会找到 `PostPolicy` 并绑定到 `Post` 模型，**无需**手动注册。

### Step 5: 在 Shield UI 给角色分配新权限点

1. 访问 `/admin/shield/roles`
2. 编辑目标角色
3. 在权限列表中找到新增的 Post 权限组
4. 勾选所需权限并保存
5. 验证：以该角色的账号登录，确认权限生效

---

## 插件如何注册自己的 Resource 权限

（与功能域 7 插件系统对接）

插件向系统注册新的 Filament Resource 时，需要同时为该 Resource 生成权限点。推荐在插件的 ServiceProvider `boot()` 方法中执行：

```php
public function boot(): void
{
    // 插件安装时生成权限点
    if ($this->app->runningInConsole()) {
        $this->commands([
            \App\Console\Commands\GeneratePluginPermissions::class,
        ]);
    }
}
```

或者在插件安装流程中调用：

```bash
php artisan shield:generate --resource=PluginFooResource --panel=admin
```

> **注意：** 插件生成的权限点命名规则与项目保持一致（snake_case + `_` 分隔），由 `config/filament-shield.php` 统一控制。

---

## BasePolicy::resourceName() 推断规则

| Policy 类名 | resourceName() 输出 | 权限点示例 |
|-------------|---------------------|-----------|
| `AdminUserPolicy` | `admin_user` | `view_any_admin_user` |
| `LoginLogPolicy` | `login_log` | `view_any_login_log` |
| `PostPolicy` | `post` | `view_any_post` |
| `BlogPostPolicy` | `blog_post` | `view_any_blog_post` |

推断逻辑：`str($className)->replaceLast('Policy', '')->snake()`

如需自定义，覆盖 `resourceName()` 方法：

```php
class MySpecialPolicy extends BasePolicy
{
    protected function resourceName(): string
    {
        return 'custom_resource_name';
    }
}
```
