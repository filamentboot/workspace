# 安装指南

本文档区分两种使用方式：

1. **演示项目开发**（克隆本仓库在本地运行联调）
2. **主包消费者安装**（在你的 Laravel 项目中 `composer require` 本包）

---

## 1. 演示项目开发

当前仓库根目录是 Laravel 演示项目，主包位于 `packages/filamentboot`。

安装依赖并启动：

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class="Filamentboot\\Database\\Seeders\\SuperAdminSeeder"
npm install && npm run build
```

演示项目通过本地 `path repository` 自动加载主包：

```text
packages/filamentboot
```

访问地址：`http://filamentboot.local`（需配置本地 hosts/Nginx）

---

## 2. 主包消费者安装（DOC-03 权威源）

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

---

### Quick Start（快速开始）

**第一步：安装主包**

```bash
composer require filamentboot/filamentboot
```

**第二步：发布资源文件**

```bash
php artisan vendor:publish --tag=filamentboot-config
php artisan vendor:publish --tag=filamentboot-migrations
php artisan vendor:publish --tag=filamentboot-views
php artisan vendor:publish --tag=filamentboot-lang
php artisan vendor:publish --tag=filamentboot-stubs
```

> 说明：
> - `filamentboot-config` — 发布配置文件 `config/filamentboot.php`
> - `filamentboot-migrations` — 发布数据库迁移文件
> - `filamentboot-views` — 发布视图文件到 `resources/views/vendor/filamentboot/`
> - `filamentboot-lang` — 发布语言文件（en / zh_CN）到 `lang/vendor/filamentboot/`
> - `filamentboot-stubs` — 发布 Stub 模板到 `stubs/vendor/filamentboot/`

**第三步：执行数据库迁移**

```bash
php artisan migrate
```

**第四步：创建超级管理员**

```bash
php artisan db:seed --class="Filamentboot\\Database\\Seeders\\SuperAdminSeeder"
```

**第五步：注册 Panel Provider**

参见下方「AdminPanelProvider 配置示例」。

**第六步：访问后台**

```text
http://你的域名/admin
```

使用默认账号登录（首次登录后请立即修改密码）。

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

### AdminPanelProvider 配置示例

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

### 升级指南

从 v0.4.x 升级到 v0.5.x，请参阅 [UPGRADING.md](../UPGRADING.md)。

v0.5 新增了 5 个 `vendor:publish` tag，升级后需**手动执行**上述第二步的 5 条发布命令以获取新版配置与模板。

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

详细命令选项请参阅 [packages/filamentboot/README.md](../packages/filamentboot/README.md)。
