# 安装指南

本文档区分两种使用方式：

1. **演示项目开发**
2. **主包消费者安装**

## 1. 演示项目开发

当前仓库根目录是 Laravel 演示项目，主包位于 `packages/filament-admin`。

安装依赖：

```bash
composer install
```

演示项目通过本地 `path repository` 自动加载：

```text
packages/filament-admin
```

## 2. 主包消费者安装

对外安装命令：

```bash
composer require laravelstack/filament-admin
```

### 注册主包

若宿主项目未启用自动发现，请确认已注册：

```php
FilamentAdmin\FilamentAdminServiceProvider::class
```

并在你的 `AdminPanelProvider` 中注册：

```php
use FilamentAdmin\FilamentAdminPlugin;

->plugin(FilamentAdminPlugin::make())
```

### 配置管理员认证

宿主项目需要提供：

- `admin` guard
- `admin_users` 表
- Filament Admin Panel 入口

### 初始化

```bash
php artisan migrate
php artisan db:seed
```

## 当前限制

- 当前主包不包含 `PluginPlatform`
- 插件市场能力属于后续独立包
- 根仓库不是最终对外包仓库
