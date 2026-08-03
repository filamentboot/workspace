# 升级指南

本文件列出 monorepo（演示项目 + 主包）层面的升级路径与 breaking changes。
主包 `laravelstack/filament-admin` 的详细升级说明另见 [packages/filament-admin/UPGRADING.md](packages/filament-admin/UPGRADING.md)。

---

## v0.4 → v0.5 升级指南

### 概述

v0.5 是 **breaking change 版本**。主要变更：
- `vendor:publish` 新增 5 个 tag（v0.4.x 无任何 publishes() 注册）
- `filament-admin:publish` 命令真实可用（v0.4.x 为空壳）
- 配置文件 `config/filament-admin.php` 新增结构

---

### Breaking Change 1：vendor:publish 新增 5 个 tag

**影响范围：** 所有使用 `laravelstack/filament-admin` 的项目

v0.4.x 中 `ServiceProvider::registerPublishes()` 未注册任何 tag，`vendor:publish` 无实际效果。
v0.5 新增 5 个 tag，升级后需**手动执行**以下命令发布资源：

```bash
php artisan vendor:publish --tag=filament-admin-config
php artisan vendor:publish --tag=filament-admin-migrations
php artisan vendor:publish --tag=filament-admin-views
php artisan vendor:publish --tag=filament-admin-lang
php artisan vendor:publish --tag=filament-admin-stubs
```

| Tag | 发布目标 | 说明 |
|-----|---------|------|
| `filament-admin-config` | `config/filament-admin.php` | 核心配置文件（必须发布） |
| `filament-admin-migrations` | `database/migrations/` | 数据库迁移文件（追加时间戳前缀） |
| `filament-admin-views` | `resources/views/vendor/filament-admin/` | 视图模板（按需发布可自定义） |
| `filament-admin-lang` | `lang/vendor/filament-admin/en/` 和 `zh_CN/` | 语言文件 |
| `filament-admin-stubs` | `stubs/vendor/filament-admin/` | Stub 模板（自定义 publish 输出时需要） |

---

### Breaking Change 2：filament-admin:publish 命令真实可用

**影响范围：** 使用自定义 Model/Resource 扩展的项目

v0.4.x 中 `filament-admin:publish` 命令为空壳（无实现），执行无效果。
v0.5 命令真实可用，完整 8 个选项：

```bash
# 发布指定 Model stub
php artisan filament-admin:publish --model=Product

# 发布指定 Resource stub（同时生成 3 个 Page 文件）
php artisan filament-admin:publish --resource=Product

# 发布全套内置资源（AdminUser / Department / Menu / LoginLog）
php artisan filament-admin:publish --all

# 只发布部分内置资源
php artisan filament-admin:publish --all --only=AdminUser,Department

# 排除部分内置资源
php artisan filament-admin:publish --all --except=Menu,LoginLog
```

**路径限制（安全修复）：** `--path` 选项现在强制要求目标路径位于 `app/` 之内，含 `..` 路径上溯或 `/` 开头的绝对路径均会被拒绝。

---

### Breaking Change 3：配置文件结构变化

**影响范围：** 已有 `config/filament-admin.php` 的项目

v0.5 配置文件 `filament-admin-config` 新增结构，建议重新发布：

```bash
php artisan vendor:publish --tag=filament-admin-config --force
```

新增配置项说明：

| 配置键 | 环境变量 | 默认值 | 说明 |
|--------|---------|------|------|
| `super_admin_role` | `SUPER_ADMIN_ROLE` | `super_admin` | 超级管理员角色名 |
| `log_retention_days` | `LOG_RETENTION_DAYS` | `90` | 操作日志保留天数（天） |

在 `.env` 中按需设置：

```dotenv
SUPER_ADMIN_ROLE=super_admin
LOG_RETENTION_DAYS=90
```

---

### Breaking Change 4：composer.json 版本约束建议

建议将版本约束更新为：

```json
{
    "require": {
        "laravelstack/filament-admin": "^0.5"
    }
}
```

---

### 升级步骤汇总

```bash
# 1. 更新 composer 依赖
composer require laravelstack/filament-admin:^0.5

# 2. 发布全部新资源（强制覆盖配置文件）
php artisan vendor:publish --tag=filament-admin-config --force
php artisan vendor:publish --tag=filament-admin-migrations
php artisan vendor:publish --tag=filament-admin-views
php artisan vendor:publish --tag=filament-admin-lang
php artisan vendor:publish --tag=filament-admin-stubs

# 3. 运行迁移
php artisan migrate

# 4. 清除缓存
php artisan config:clear
php artisan cache:clear
php artisan filament:optimize-clear   # 清除 Filament 组件与 Blade 图标缓存
```

---

## 历史升级记录

### v0.3 → v0.4

- Composer 坐标变更：`filament-admin/filament-admin` → `laravelstack/filament-admin`
- 移除 `packages/plugin-platform/`（插件市场能力从主包解耦）
