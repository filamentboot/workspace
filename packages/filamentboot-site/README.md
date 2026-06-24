# filamentboot-site — 前台官网管理插件

面向中小企业的前台官网管理插件，提供案例/方案/产品/页面/询盘五类内容管理，支持中英双语与主题切换。

## 简介

本包为 Filamentboot 后台增加完整的企业官网内容管理能力。注册 `SitePlugin` 后，后台将出现「官网管理」分组，包含装修案例（`SiteCaseResource`）、智能方案（`SiteSolutionResource`）、智能产品（`SiteProductResource`）、静态页面（`SitePageResource`）、询盘管理（`ContactMessageResource`）五个 Resource，以及未读询盘统计小部件（`UnreadContactMessagesWidget`）。前台路由、Livewire 组件（`CaseFilter`、`ContactForm`）和视图由 `SiteServiceProvider` 自动注册。官网设置（站点名称、联系方式、主题、双语切换等）通过后台「官网设置」页（`SiteSettingsPage`）管理。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot`（`*`，跟随主包版本）
- `livewire/livewire ^4.3`（前台交互组件）
- `danharrin/livewire-rate-limiting ^2.2`（询盘提交频率限制）
- `spatie/laravel-settings ^3.9`（官网设置持久化）
- `filament/spatie-laravel-settings-plugin ^5.6`（设置页表单集成）
- `filament/spatie-laravel-media-library-plugin ^5.6`（媒体文件上传）
- `mews/purifier ^3.4`（HTML XSS 过滤）

## 安装

```bash
composer require filamentboot/filamentboot-site
```

发布配置文件与静态资源：

```bash
php artisan vendor:publish --tag=filamentboot-site-config
php artisan vendor:publish --tag=filamentboot-site-assets
```

执行数据库迁移（8 张内容表）：

```bash
php artisan migrate
```

运行初始化种子数据（可选）：

```bash
php artisan db:seed --class="Filamentboot\FilamentbootSite\Database\Seeders\SiteSeeder"
```

## 使用

### 1. 注册插件

在 `app/Providers/Filament/AdminPanelProvider.php` 中注册：

```php
use Filamentboot\FilamentbootSite\SitePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            SitePlugin::make(),
        ]);
}
```

注册后，Filament 面板自动挂载以下内容：

| 组件 | 类 | 说明 |
|------|----|------|
| 官网设置页 | `SiteSettingsPage` | 站点基础信息、双语、主题 |
| 装修案例 | `SiteCaseResource` | 含分类、标签、封面图 |
| 智能方案 | `SiteSolutionResource` | 解决方案内容管理 |
| 智能产品 | `SiteProductResource` | 含分类、规格 |
| 静态页面 | `SitePageResource` | 关于我们、联系我们等 |
| 询盘管理 | `ContactMessageResource` | 只读 + 状态流转 |
| 未读询盘统计 | `UnreadContactMessagesWidget` | 仪表盘小部件 |

### 2. 前台路由

`SiteServiceProvider` 自动注册前台路由，`SetLocaleMiddleware` 处理中英文切换，`ContactForm` Livewire 组件带频率限制保护。

## 许可

MIT License，详见 [LICENSE](LICENSE)。
