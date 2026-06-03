# 安装指南

FilamentAdmin 当前以 **Composer Library 包** 形态发布，目标安装方式如下：

```bash
composer require filament-admin/filament-admin
```

## 环境要求

| 软件 | 版本要求 |
|------|---------|
| PHP | ^8.3 |
| Laravel | ^13.0 |
| MySQL | ^8.0 |
| Redis | ^7.0 |
| Composer | ^2.0 |

## 安装步骤

### 1. 安装包

```bash
composer require filament-admin/filament-admin
```

### 2. 注册主包

若宿主项目未启用自动发现，请确认已注册：

```php
FilamentAdmin\FilamentAdminServiceProvider::class
```

并在你的 `AdminPanelProvider` 中注册：

```php
use FilamentAdmin\FilamentAdminPlugin;

->plugin(FilamentAdminPlugin::make())
```

### 3. 配置管理员认证

宿主项目需要提供 `admin` guard、对应的 `admin_users` 表以及 Filament Admin Panel 注册入口。

当前主包依赖以下基本约定：

- guard 名称：`admin`
- 后台入口：`/admin`
- 管理员模型：`AdminUser`

### 4. 执行迁移与初始化

```bash
php artisan migrate
php artisan db:seed
```

若项目提供了默认超级管理员初始化命令或 Seeder，请按项目实际文档执行。

### 5. 访问后台

完成初始化后，访问你的后台地址：

```text
/admin
```

## 当前限制

- 当前主包发布对象不包含 `PluginPlatform`
- 当前主包不包含演示站仓库内容
- 若需要插件市场能力，应等待独立包形态的后续发布

## 常见问题

**Q: `composer require` 失败**

A: 先确认 Packagist 条目、PHP 版本、Laravel 版本和 Composer 网络环境是否满足要求。

**Q: 安装后没有后台入口**

A: 确认宿主项目已正确注册 `FilamentAdminPlugin::make()`。

**Q: 安装后仍引用旧的本地插件市场代码**

A: 清理旧的 path repository、本地联调依赖和历史 `vendor/` 后重新安装。
