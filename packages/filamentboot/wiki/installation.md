# 安装指南

面向在自己的 Laravel 项目中 `composer require` 本包的消费者。

### Prerequisites（环境要求）

在开始之前，请确认宿主 Laravel 项目满足以下要求：

| 组件 | 最低版本 | 说明 |
|------|--------|------|
| PHP | 8.3+ | 必需扩展见下表 |
| Laravel | 13.8+ | 框架基础 |
| Filament | 5.0+ | 后台框架 |
| MySQL | 8.0+ | 主数据库（其他兼容数据库亦可） |
| Redis | 7.x+ | Session / Cache / Queue 驱动 |
| Node.js | 20+ | 前端资产构建（如需自定义前端） |
| Composer | 2.x | PHP 依赖管理器 |

**必需 PHP 扩展：**

| 扩展 | 说明 |
|------|------|
| `pdo_mysql` | MySQL 数据库驱动 |
| `mbstring` | 多字节字符串处理 |
| `bcmath` | 精确数学运算 |
| `gd` | 图片处理（头像上传等） |
| `redis` | Redis 客户端（phpredis） |
| `fileinfo` | 文件类型检测（媒体库） |
| `openssl` | 加密与 HTTPS 支持 |
| `intl` | `filament/support` 的硬依赖；缺失时批量删除/恢复/强删/导入的通知会抛 `RuntimeException`，价格类字段（`->money()`）会 500。不要用 `--ignore-platform-req=ext-intl` 绕过 |

---

### Quick Start（快速开始）

```bash
composer require filamentboot/filamentboot
php artisan filamentboot:install
```

`filamentboot:install` 一条命令依次做完：生成 `app/Providers/Filament/AdminPanelProvider.php`
（已含 `authGuard('admin')`、`FilamentbootPlugin::make()`，并自动注册进
`bootstrap/providers.php`）→ 向 `config/auth.php` 注入 `admin` guard 与 `admin_users`
provider → 发布配置与多语言文件 → 复制 favicon / 品牌 Logo 到 `public/` → 执行数据库迁移
→ 创建超级管理员账号。全程幂等，重复执行不会覆盖已存在的文件（除非加 `--force`）。

装完访问 `/admin`，使用默认账号登录。

---

### 默认账号

| 字段 | 值 |
|------|-----|
| 邮箱 | `admin@example.com` |
| 密码 | `password` |
| 用户名 | `admin` |
| 昵称 | `超级管理员` |

> ⚠ **安全提示：** 首次登录后请立即修改默认密码。默认账号 `admin@example.com / password` 仅供初始化使用，**请勿用于生产环境**。

---

### 手动模式

适合项目里已有 `AdminPanelProvider`、或只想要某一项资源的场景。**不含迁移 tag**：本包依赖
`loadMigrationsFrom()` 自动加载全部迁移，不提供 `vendor:publish` 迁移出口——发布一份到
`database/migrations/` 会被 `migrate` 同时扫描到两份，其中 `activity_log` 三件套是具名类，
直接编译期报 `Cannot redeclare class`。需要自定义迁移就手写新迁移文件，不要整包复制。

```bash
php artisan vendor:publish --tag=filamentboot-config
php artisan vendor:publish --tag=filamentboot-views
php artisan vendor:publish --tag=filamentboot-lang
php artisan vendor:publish --tag=filamentboot-stubs
php artisan vendor:publish --tag=filamentboot-brand
php artisan migrate
php artisan db:seed --class="Filamentboot\\Database\\Seeders\\SuperAdminSeeder"
```

> 说明：
> - `filamentboot-config` — 发布配置文件 `config/filamentboot.php`
> - `filamentboot-views` — 发布视图文件到 `resources/views/vendor/filamentboot/`
> - `filamentboot-lang` — 发布语言文件（en / zh_CN）到 `lang/vendor/filamentboot/`
> - `filamentboot-stubs` — 发布 Stub 模板到 `stubs/vendor/filamentboot/`
> - `filamentboot-brand` — 发布 favicon 与品牌 Logo 到 `public/`（`filamentboot:install` 已自动复制，此 tag 用于强制覆盖重置）
> - `filamentboot-theme` — 发布后台观感覆盖样式到 `resources/css/`（仅在需要二次定制时用；发布后须自行接管加载，或设 `FILAMENTBOOT_THEME=false` 关闭包内注入以免重复）

在你的宿主项目中创建或修改 `app/Providers/Filament/AdminPanelProvider.php`：

```php
<?php

namespace App\Providers\Filament;

use Filamentboot\FilamentbootPlugin;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * 后台 Filament Panel 服务提供者
 *
 * 注册 FilamentbootPlugin 并配置 admin guard 与路由。
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('admin')
            ->plugins([
                FilamentbootPlugin::make(),
            ]);
    }
}
```

在 `bootstrap/providers.php` 中注册：

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
```

---

### 配置说明

发布后的配置文件位于 `config/filamentboot.php`，支持以下环境变量覆盖：

| 环境变量 | 默认值 | 说明 |
|---------|------|------|
| `SUPER_ADMIN_ROLE` | `super_admin` | 超级管理员角色名（绕过所有权限检查） |
| `LOG_RETENTION_DAYS` | `90` | 操作日志保留天数 |

在 `.env` 中按需修改：

```dotenv
SUPER_ADMIN_ROLE=super_admin
LOG_RETENTION_DAYS=90
```

---

### 生产环境优化

上线前在服务器上执行：

```bash
php artisan storage:link          # 媒体库走 public 磁盘，缺软链会导致头像等附件 404
php artisan optimize              # 已含 filament:optimize（组件缓存 + Blade 图标缓存）
```

`php artisan optimize` 会经 Filament 的 `optimizes()` 钩子自动带上 `filament:optimize`
（即 `filament:cache-components` + `icons:cache`）。若部署脚本用的是分列的
`config:cache` / `route:cache` / `view:cache`，则需额外补一条 `php artisan filament:optimize`。

> 组件缓存会固化 Resource / Page 清单。**启用或停用插件后**需执行
> `php artisan filament:optimize-clear`（或 `php artisan optimize:clear`）重建，否则新插件界面不会出现。

---

### 升级指南

从 v0.4.x 升级到 v0.5.x，请参阅 [UPGRADING.md](../UPGRADING.md)。

v0.5 共提供 7 个 `vendor:publish` tag，升级后需**手动执行**上述第二步的发布命令以获取新版配置与模板。

---

## 扩展与自定义

如需自定义内置 Model 或 Resource，可使用 `filamentboot:publish` 命令生成可编辑副本：

```bash
# 发布指定 Resource stub
php artisan filamentboot:publish --resource=AdminUser

# 发布全套内置资源（AdminUser / Department / Menu / LoginLog）
php artisan filamentboot:publish --all
```

Stub 查找顺序：优先读 `stubs/vendor/filamentboot/{Name}.stub`（用户自定义），找不到则 fallback 到包内默认 stub。

详细命令选项请参阅 [README.md](../README.md)。
