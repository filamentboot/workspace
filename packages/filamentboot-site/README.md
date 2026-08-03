# filamentboot-site — 前台官网管理插件

面向中小企业的前台官网管理插件，提供案例/方案/产品/资讯/页面/询盘六类内容管理，中文单语言，支持主题切换。

## 简介

本包为 Filamentboot 后台增加完整的企业官网内容管理能力。注册 `SitePlugin` 后，后台将出现「官网管理」分组，包含装修案例（`SiteCaseResource`）、智能方案（`SiteSolutionResource`）、智能产品（`SiteProductResource`）、资讯文章（`NewsArticleResource`）、资讯分类（`NewsCategoryResource`）、静态页面（`SitePageResource`）、询盘管理（`ContactMessageResource`）七个 Resource，以及询盘与站点健康统计小部件（`UnreadContactMessagesWidget`）。前台路由、Livewire 组件（`CaseFilter`、`ContactForm`）和视图由 `SiteServiceProvider` 自动注册。官网设置（公司信息、联系方式、SEO 默认值、主题等）通过后台「网站设置」页（`SiteSettingsPage`）管理。

> **语言范围**：当前版本只维护中文内容流。数据库中的 `*_en` 列为早期双语实现的遗留字段，后台表单与前台渲染均已移除英文入口。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot`（`*`，跟随主包版本）
- `livewire/livewire ^4.3`（前台交互组件）
- `danharrin/livewire-rate-limiting ^2.2`（询盘提交频率限制）
- `spatie/laravel-settings ^3.9`（官网设置持久化）
- `filament/spatie-laravel-settings-plugin ^5.6`（设置页表单集成）
- `filament/spatie-laravel-media-library-plugin ^5.6`（媒体文件上传）
- `mews/purifier ^3.4`（HTML XSS 过滤）

### 富文本过滤

前台正文一律经 `Support\RichText::purify()` 输出，白名单写在包内、与后台 RichEditor
的默认工具栏对齐（标题、引用、代码块、表格、上下标、删除线都放行，脚本与事件属性剥离）。
不读宿主 `config/purifier.php` 的 `default` 画像——那份白名单只有十来个标签，
拿它过滤编辑器产出的正文会把版式静默吃掉。

想自己定过滤策略：在 `config/purifier.php` 加一段画像，再把画像名填到
`filamentboot-site.purifier_profile`（或 `.env` 的 `SITE_PURIFIER_PROFILE`），包内白名单即让位。

正文样式由各主题的 `.prose` 提供（`resources/css/themes/{theme}.css`）。
项目未装 `@tailwindcss/typography`，两套主题各写各的一份——自定义主题时别漏，
漏了正文会退回 Tailwind preflight 后的裸样式：标题与正文等大、列表没符号、段落无间距。

## 安装

```bash
composer require filamentboot/filamentboot-site
```

发布配置文件与静态资源：

```bash
php artisan vendor:publish --tag=filamentboot-site-config
php artisan vendor:publish --tag=filamentboot-site-assets
```

执行数据库迁移（14 张内容表）：

```bash
php artisan migrate
```

运行初始化种子数据（可选）：

```bash
# 案例 / 方案 / 产品 / 静态页面 / 示例询盘
php artisan db:seed --class="Filamentboot\FilamentbootSite\Database\Seeders\SiteDemoSeeder"

# 资讯分类与文章
php artisan db:seed --class="Filamentboot\FilamentbootSite\Database\Seeders\SiteNewsSeeder"
```

两个种子可反复执行，按 slug 增量补种：已有记录一概不动（后台改过的文案不会被覆盖），
缺的补上，升级后新增的演示内容直接跑一遍就能拿到。软删除过的记录不复活。

封面图每次都会重试挂载，所以图片是后补的也没关系——放进
`storage/app/public/site/{cases,solutions,news,products}/{slug}.jpg` 再跑一遍即可。

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
| 网站设置页 | `SiteSettingsPage` | 公司信息、联系方式、SEO 默认值、主题 |
| 装修案例 | `SiteCaseResource` | 含分类、标签、封面图、图集 |
| 智能方案 | `SiteSolutionResource` | 解决方案内容管理 |
| 智能产品 | `SiteProductResource` | 含分类、品牌、价格、图集、富文本详情 |
| 资讯文章 | `NewsArticleResource` | 含分类、标签、封面图，`published_at` 控制发布与定时 |
| 资讯分类 | `NewsCategoryResource` | 扁平分类，前台列表页筛选用 |
| 静态页面 | `SitePageResource` | 关于我们、联系我们、常见问题等 |
| 询盘管理 | `ContactMessageResource` | 只读 + 状态流转 |
| 官网概览 | `UnreadContactMessagesWidget` | 未读询盘数 + 发布前健康检查 |

### 2. 前台路由挂载模式

前台路由由 `config/filamentboot-site.php` 的 `route.mode` 决定，**默认 `prefix`，不抢占宿主根路由**：

| 模式 | 用途 | 示例 URL | 环境变量 |
|------|------|----------|----------|
| `prefix`（默认） | 宿主已有前台业务 | `/site/about` | `SITE_ROUTE_MODE=prefix`、`SITE_ROUTE_PREFIX=site` |
| `root` | 项目本身就是官网 | `/about` | `SITE_ROUTE_MODE=root` |
| `domain` | 官网使用独立域名 | `www.example.com/about` | `SITE_ROUTE_MODE=domain`、`SITE_ROUTE_DOMAIN=www.example.com` |

固定系统路径（`sitemap.xml`、`robots.txt` 等）先于动态 `/{slug}` 注册，并通过 `route.reserved_slugs` 排除，不会被页面路由吞掉。

> 使用动态 `robots.txt` 时，需删除宿主的静态 `public/robots.txt`——Web 服务器会优先返回静态文件。

### 3. 前端资源构建

在宿主 `vite.config.js` 的 `input` 中加入主题 CSS，然后执行 `npm run build`：

```js
input: [
    // ...
    'vendor/filamentboot/filamentboot-site/resources/css/themes/decoration.css',
    'vendor/filamentboot/filamentboot-site/resources/css/themes/tech-product.css',
],
```

### 4. 主题定制

内置两套主题：`decoration`（科技装修 · 深色）与 `tech-product`（科技产品 · 浅色），在「网站设置 → 外观」中切换，保存后自动清除视图缓存。

发布主题视图后即可覆盖包内模板：

```bash
php artisan vendor:publish --tag=filamentboot-site-views
```

视图解析优先级（先命中者生效）：

```text
resources/views/vendor/filamentboot-site/themes/{theme}/   ← 宿主发布覆盖
包内 resources/views/themes/{theme}/                        ← 主题模板
包内 resources/views/shared/                                ← 跨主题共享组件
包内 resources/views/                                       ← Livewire 视图兜底
```

### 5. 发布前检查

「网站设置」页与仪表盘会提示尚未配置的发布前必填项：联系电话、公司地址、ICP 备案号、隐私政策链接、默认 SEO 标题与描述、默认 Open Graph 图、公司 LOGO。未配置的项不会在前台渲染空栏目，但会在后台持续告警。

## 发布 Tag 一览

| Tag | 内容 | 目标路径 |
|-----|------|----------|
| `filamentboot-site-config` | 配置文件 | `config/filamentboot-site.php` |
| `filamentboot-site-migrations` | 内容与设置迁移 | `database/migrations/` |
| `filamentboot-site-views` | 主题视图 | `resources/views/vendor/filamentboot-site/themes/` |
| `filamentboot-site-assets` | 主题 CSS | `resources/css/vendor/filamentboot-site/` |

## 许可

MIT License，详见 [LICENSE](LICENSE)。
