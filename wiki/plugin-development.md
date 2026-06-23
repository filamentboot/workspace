# 插件开发指南（plugin-development.md）

> 面向插件作者。本文说明如何开发一个与 `filamentboot/filamentboot` 生态兼容的 Filament 插件，
> 从零创建最小合规骨架，到声明 `post_install` 安装钩子、提交到官方市场，全流程覆盖。

---

## 目录

1. [什么是 filamentboot 兼容插件](#1-什么是-filamentboot-兼容插件)
2. [必须实现的接口](#2-必须实现的接口)
3. [composer.json 规范字段](#3-composerjson-规范字段)
4. [filamentboot 扩展约定（可选）](#4-filamentboot-扩展约定可选)
5. [完整最小示例](#5-完整最小示例)
6. [提交到 filamentboot 官方市场](#6-提交到-filamentboot-官方市场)
7. [可选：同步上架 filamentphp.com](#7-可选同步上架-filamentphpcom)

---

## 1. 什么是 filamentboot 兼容插件

`filamentboot/filamentboot` 是基于 Filament 5 构建的后台基础平台。
它的插件生态分两层：

| 层次 | 描述 |
|------|------|
| **标准 Filament 插件** | 实现 `Filament\Contracts\Plugin` 接口，可在任意 Filament 5 Panel 使用 |
| **filamentboot 兼容插件** | 在标准 Filament 插件基础上，**可选**声明 `extra.filamentboot` 扩展块，以获得后台市场一键安装、设置页直跳、`post_install` 自动钩子等增强能力 |

**关键原则：**

- 只要实现了 `Filament\Contracts\Plugin` 接口，就是一个合法的 filamentboot 兼容插件。
  后台插件市场通过扫描 `Filament\Contracts\Plugin` 接口实现类自动发现已安装插件。
- `extra.filamentboot` 块是**可选的富扩展**，不声明不影响基本使用；声明后可获得更丰富的市场展示和自动化安装体验。

---

## 2. 必须实现的接口

所有 filamentboot 兼容插件必须实现 `Filament\Contracts\Plugin` 接口：

```php
<?php

namespace YourVendor\YourPlugin;

use Filament\Contracts\Plugin;
use Filament\Panel;

class YourPlugin implements Plugin
{
    /**
     * 返回插件的唯一标识符，建议与 Composer 包名后半段一致。
     */
    public function getId(): string
    {
        return 'your-vendor-your-plugin';
    }

    /**
     * 在 Panel 注册阶段执行，用于注册 Resources、Pages、Widgets 等。
     */
    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                // YourResource::class,
            ])
            ->pages([
                // YourPage::class,
            ]);
    }

    /**
     * 在 Panel 启动阶段执行，用于注册事件监听器、中间件等。
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * 静态工厂方法，Filament 约定写法。
     */
    public static function make(): static
    {
        return app(static::class);
    }
}
```

**接口方法说明：**

| 方法 | 必须 | 说明 |
|------|------|------|
| `getId(): string` | 必须 | 全局唯一插件 ID，格式建议 `vendor-plugin-name` |
| `register(Panel $panel): void` | 必须 | 注册 Resources / Pages / Widgets / Livewire 组件 |
| `boot(Panel $panel): void` | 必须 | 启动钩子，可为空方法体 |
| `make(): static` | 约定 | 静态工厂，由 `AdminPanelProvider` 调用（`->plugin(YourPlugin::make())`） |

---

## 3. composer.json 规范字段

### 必须字段

```json
{
    "name": "your-vendor/your-plugin",
    "description": "插件功能的中文简短描述（≤120 字符）",
    "type": "library",
    "license": "MIT",
    "keywords": [
        "filament",
        "laravel",
        "filament-plugin",
        "your-feature-keyword"
    ],
    "extra": {
        "laravel": {
            "providers": [
                "YourVendor\\YourPlugin\\YourPluginServiceProvider"
            ]
        }
    }
}
```

**关键规范：**

| 字段 | 要求 | 说明 |
|------|------|------|
| `type` | 必须为 `"library"` | 标识这是一个可复用库，不是应用项目 |
| `keywords` | 必须含 `"filament"` 和 `"filament-plugin"` | 用于 Packagist 搜索过滤 |
| `extra.laravel.providers` | 必须声明 ServiceProvider | 支持 Laravel 包自动发现 |

### 版本约束建议

```json
{
    "require": {
        "php": "^8.3",
        "filament/filament": "^5.0",
        "filamentboot/filamentboot": "^0.5"
    }
}
```

声明 `require.filament/filament` 约束是最佳实践——后台插件市场的兼容性检测
（`compatible` / `incompatible` / `unknown` 三态标签）依赖此字段。
未声明时，市场显示"兼容性未知"黄标，允许安装但会提示用户注意。

---

## 4. filamentboot 扩展约定（可选）

在 `extra.filamentboot` 块中声明 filamentboot 专属信息，可获得：

- 后台市场更丰富的展示信息（名称、描述、类型、信任来源）
- 设置页直跳链接
- `post_install` 自动安装钩子（发布资源、迁移、种子）

### 4.1 extra.filamentboot 完整字段

```json
{
    "extra": {
        "filamentboot": {
            "slug": "your-vendor-your-plugin",
            "name": "插件显示名称",
            "type": "package",
            "source": "community",
            "plugin_class": "YourVendor\\YourPlugin\\YourPlugin",
            "settings_page_slug": "settings/your-plugin",
            "service_provider": "YourVendor\\YourPlugin\\YourPluginServiceProvider",
            "description": "插件功能详细描述",
            "post_install": {
                "publish_tags": ["your-plugin-config"],
                "run_migrations": false,
                "seeders": []
            }
        }
    }
}
```

### 4.2 字段说明

| 字段 | 类型 | 必须 | 说明 |
|------|------|------|------|
| `slug` | `string` | 建议 | 全局唯一 slug，用于 DB 标识；建议与 `getId()` 一致 |
| `name` | `string` | 建议 | 后台市场展示名称（中文） |
| `type` | `string` | 可选 | `"package"`（功能型）或 `"solution_plugin"`（含迁移/种子的解决方案型） |
| `source` | `string` | 可选 | `"official_trusted"` / `"official_listed"` / `"community"`，官方插件由平台方设置 |
| `plugin_class` | `string` | 建议 | Plugin 实现类的完整 FQCN，用于快速路径发现（无需 classmap grep） |
| `settings_page_slug` | `string` | 可选 | 插件配置页 slug，用于后台"设置"按钮直跳（如 `"settings/oss"`） |
| `service_provider` | `string` | 建议 | ServiceProvider 完整 FQCN，用于 `vendor:publish` 兜底（无 `post_install.publish_tags` 时） |
| `description` | `string` | 可选 | 市场详情页长描述 |

> 注：旧版本使用 `extra.filament-admin` 键，v0.5 起统一改为 `extra.filamentboot`。

### 4.3 post_install 块详解

`post_install` 声明安装完成后自动执行的步骤，数据驱动，无需编写代码：

```json
"post_install": {
    "publish_tags": ["your-plugin-config", "your-plugin-assets"],
    "run_migrations": true,
    "seeders": ["YourVendor\\YourPlugin\\Database\\Seeders\\YourSeeder"]
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `publish_tags` | `string[]` | `vendor:publish --tag=TAG --force` 依次执行的 tag 列表 |
| `run_migrations` | `bool` | `true` 时自动执行 `php artisan migrate --force` |
| `seeders` | `string[]` | 依次执行 `php artisan db:seed --class=X` 的种子类（完整 FQCN） |

**未声明 `post_install` 时的通用兜底行为：**

1. 执行 `php artisan migrate --force`（通过 `loadMigrationsFrom` 自动发现迁移文件）
2. 执行 `composer dump-autoload`
3. 跳过 `vendor:publish`（无 tags 声明时跳过，记录日志）

---

## 5. 完整最小示例

本节以官方 [filamentphp/plugin-skeleton](https://github.com/filamentphp/plugin-skeleton)（5.x 分支）
为基底，展示叠加 `extra.filamentboot` 约定后的完整最小可用插件。

### 5.1 从 plugin-skeleton 创建项目

```bash
composer create-project filamentphp/plugin-skeleton your-vendor-your-plugin --stability=dev
cd your-vendor-your-plugin
php configure.php
```

`configure.php` 交互式脚本会询问包名、命名空间、作者等信息，自动生成骨架代码。

完成后目录结构：

```
your-vendor-your-plugin/
├── src/
│   ├── YourPlugin.php              ← Plugin 实现类
│   └── YourPluginServiceProvider.php
├── composer.json
├── README.md
└── CHANGELOG.md
```

### 5.2 叠加 extra.filamentboot 约定

以一方插件 `filamentboot/filamentboot-oss`（阿里云 OSS 存储）为真实参考，
其 `composer.json` 中的 `extra.filamentboot` 块如下（已通过 `plugin:scan` 合规验证）：

```json
{
    "name": "filamentboot/filamentboot-oss",
    "description": "阿里云 OSS 存储插件，为 filamentboot/filamentboot 提供 Flysystem OSS 磁盘驱动与后台凭证配置页。",
    "type": "library",
    "license": "MIT",
    "keywords": ["filament", "laravel", "oss", "aliyun", "storage", "filament-plugin", "cloud-storage"],
    "extra": {
        "laravel": {
            "providers": [
                "Filamentboot\\FilamentbootOss\\OssServiceProvider"
            ]
        },
        "filamentboot": {
            "slug": "filamentboot-oss",
            "name": "阿里云 OSS 存储",
            "type": "package",
            "source": "official_listed",
            "plugin_class": "Filamentboot\\FilamentbootOss\\OssPlugin",
            "settings_page_slug": "settings/oss",
            "service_provider": "Filamentboot\\FilamentbootOss\\OssServiceProvider",
            "description": "阿里云 OSS 对象存储驱动，凭证由超管在后台配置页加密存储，无需修改 .env 文件。",
            "post_install": {
                "publish_tags": ["filamentboot-oss-config"],
                "run_migrations": false,
                "seeders": []
            }
        }
    }
}
```

**关键点解读：**

- `plugin_class` 声明后，插件市场发现时跳过 classmap grep（快速路径）。
- `settings_page_slug: "settings/oss"` 让后台"设置"按钮可直接跳转插件配置页。
- `post_install.publish_tags: ["filamentboot-oss-config"]` 安装完成后自动执行
  `php artisan vendor:publish --tag=filamentboot-oss-config --force`。
- `run_migrations: false` 表示此插件无数据库迁移，跳过 migrate 步骤。

### 5.3 Plugin 类最小实现

```php
<?php

namespace Filamentboot\FilamentbootOss;

use Filament\Contracts\Plugin;
use Filament\Panel;

class OssPlugin implements Plugin
{
    /**
     * 返回插件唯一 ID，与 extra.filamentboot.slug 保持一致。
     */
    public function getId(): string
    {
        return 'filamentboot-oss';
    }

    /**
     * 注册插件到 Panel（Filament Resource / Page / Widget 等）。
     */
    public function register(Panel $panel): void
    {
        $panel->pages([
            \Filamentboot\FilamentbootOss\Filament\Pages\OssSettings::class,
        ]);
    }

    /**
     * 启动阶段钩子（可为空）。
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * 静态工厂，供 AdminPanelProvider 调用。
     */
    public static function make(): static
    {
        return app(static::class);
    }
}
```

### 5.4 ServiceProvider 最小实现

```php
<?php

namespace Filamentboot\FilamentbootOss;

use Illuminate\Support\ServiceProvider;

class OssServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filamentboot-oss.php',
            'filamentboot-oss'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/filamentboot-oss.php' => config_path('filamentboot-oss.php'),
        ], 'filamentboot-oss-config');
    }
}
```

**注意：** `publishes()` 中的 tag（`'filamentboot-oss-config'`）必须与
`extra.filamentboot.post_install.publish_tags` 中声明的 tag 保持一致。

### 5.5 含迁移与种子的 solution_plugin 示例

对于需要创建数据库表的解决方案型插件（`type: solution_plugin`），参考
`filamentboot/filamentboot-site`（官网插件）的 `post_install` 声明：

```json
"post_install": {
    "publish_tags": ["filamentboot-site-config", "filamentboot-site-assets"],
    "run_migrations": true,
    "seeders": ["Filamentboot\\FilamentbootSite\\Database\\Seeders\\SiteSeeder"]
}
```

安装后自动执行顺序：

1. `php artisan vendor:publish --tag=filamentboot-site-config --force`
2. `php artisan vendor:publish --tag=filamentboot-site-assets --force`
3. `php artisan migrate --force`
4. `php artisan db:seed --class="Filamentboot\\FilamentbootSite\\Database\\Seeders\\SiteSeeder"`
5. `composer dump-autoload`

### 5.6 合规自检

开发完成后，使用平台内置命令验证合规状态：

```bash
# 在 filamentboot/filamentboot 项目根目录执行
php artisan plugin:scan
```

输出示例（所有项为 `[PASS]` 即合规）：

```
## filamentboot/filamentboot-oss
- [PASS] implements Filament\Contracts\Plugin
- [PASS] composer.json type: library
- [PASS] keywords contains 'filament'
- [PASS] extra.laravel.providers 已声明
- [PASS] post_install 块已声明
```

---

## 6. 提交到 filamentboot 官方市场

官方市场内置精选列表（`config/official-market.php`）由 filamentboot/filamentboot
维护团队审核管理。提交流程：

1. **确保合规：** 运行 `php artisan plugin:scan`，确认所有检查项 `[PASS]`。
2. **发布到 Packagist：** 将插件发布至 [packagist.org](https://packagist.org)，确保包名格式
   为 `vendor/filamentboot-xxx` 或含 `filament` 关键词。
3. **提交 PR：** 向 [filamentboot 仓库](https://github.com/filamentboot/filamentboot) 提交 PR，
   在 `config/official-market.php` 添加插件条目，包含以下信息：
   - 包名（Packagist 格式）
   - 展示名称与描述
   - `source: "official_listed"`（精选列表级别）
4. **审核标准：** 代码开源、文档完善、有 CI、README 含安装步骤、无已知安全漏洞。

社区开发者提交的插件默认标记为 `source: "community"`，用户安装时会看到"来自社区，风险自负"的确认提示。

---

## 7. 可选：同步上架 filamentphp.com

官方 Filament 插件目录（[filamentphp.com/plugins](https://filamentphp.com/plugins)）接受社区插件提交：

1. 确保插件已在 Packagist 发布，且 `composer.json` 的 `keywords` 含 `filament`、`filament-plugin`。
2. 按 [filamentphp.com 插件提交指南](https://filamentphp.com/docs/4.x/plugins/getting-started#publishing-your-plugin)
   在仓库补充所需元数据（徽章、截图等）。
3. 向 [filamentphp/filamentphp.com](https://github.com/filamentphp/filamentphp.com) 仓库提交 PR。

上架 filamentphp.com 后，该插件可被 filamentboot 后台"社区市场" Tab 通过 Packagist
搜索（`tags=filament`）发现，不需要额外配置。

---

## 相关文档

- [插件使用指南](plugin-usage.md) — 后台一键安装、手动安装、启用禁用、卸载全流程
- [安装指南](installation.md) — filamentboot 主包安装
- [Filament 5 官方插件文档](https://filamentphp.com/docs/4.x/plugins/getting-started)
- [filamentphp/plugin-skeleton](https://github.com/filamentphp/plugin-skeleton) — 官方插件脚手架
