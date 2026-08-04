# filamentboot-site — 前台官网管理插件

面向中小企业的前台官网管理插件，提供案例/方案/产品/资讯/页面/询盘六类内容管理，中文单语言，支持主题切换。

## 简介

本包为 Filamentboot 后台增加完整的企业官网内容管理能力。注册 `SitePlugin` 后，后台将出现「官网管理」分组，包含 10 个 Resource——装修案例（`SiteCaseResource`）、智能方案（`SiteSolutionResource`）、智能产品（`SiteProductResource`）、资讯文章（`NewsArticleResource`）、资讯分类（`NewsCategoryResource`）、静态页面（`SitePageResource`）、导航菜单（`SiteMenuResource`）、菜单项（`SiteMenuItemResource`）、301 重定向（`SiteRedirectResource`）、询盘管理（`ContactMessageResource`）——以及未读询盘小部件（`UnreadContactMessagesWidget`）。前台路由与视图由 `SiteServiceProvider` 自动注册。官网设置（公司信息、联系方式、SEO 默认值、主题、统计代码、在线客服等）通过后台「网站设置」页（`SiteSettingsPage`）管理。

> **公开页零 Livewire、零 session。** 前台内容页不挂 `web` 中间件组、不起会话、不含 Livewire 组件，
> 因此可以被 CDN 整页缓存（见下文「公开页缓存」）。前台交互一律用 Alpine + 无状态端点。

> **语言范围**：当前版本只维护中文内容流。数据库中的 `*_en` 列为早期双语实现的遗留字段，后台表单与前台渲染均已移除英文入口。

## 要求

- PHP `^8.3`、Laravel `^13`、Filament `^5`
- 依赖主包 `filamentboot/filamentboot`（`*`，跟随主包版本）
- `livewire/livewire ^4.3`（后台 Filament 页面依赖；前台不用）
- ⚠️ `danharrin/livewire-rate-limiting ^2.2` 仍在 `composer.json` 里，但**包内已无任何引用**——
  询盘限流改成了框架自带的 `RateLimiter`（前台去 Livewire 化的连带结果）。属可清理项，未动
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

执行数据库迁移（16 张内容表）：

```bash
php artisan migrate
```

写入权限点与三层角色（**必需**，否则除超管外无人能进官网管理）：

```bash
php artisan db:seed --class="Filamentboot\FilamentbootSite\Database\Seeders\SitePermissionSeeder"
php artisan db:seed --class="Filamentboot\FilamentbootSite\Database\Seeders\SiteRoleSeeder"
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

主题 CSS 与前台脚本都通过宿主的 Vite 构建。**两件事都要做，缺一样前台就不完整**：

```bash
npm install -D alpinejs
```

`vite.config.js` 的 `input` 里加上这两类入口（路径按你的安装形态选，见下）：

```js
input: [
    // …你自己的入口
    'vendor/filamentboot/filamentboot-site/resources/css/themes/decoration.css',
    'vendor/filamentboot/filamentboot-site/resources/css/themes/tech-product.css',
    'vendor/filamentboot/filamentboot-site/resources/js/site.js',
],
```

然后 `npm run build`。

**为什么需要 `alpinejs`**：前台的交互（导航抽屉、询盘面板、二级导航下拉、产品图集轮播）全靠 Alpine。
本包的公开页**刻意不使用任何 Livewire 组件**——Livewire 注入的脚本带 `data-csrf`，渲染时会调
`csrf_token()` 起 session，公开页就必然带 `Set-Cookie`，整页缓存无从谈起。所以 Alpine 由
`resources/js/site.js` 独立交付。**漏了这一步，前台所有 `x-data` 都不工作。**

入口路径有三种形态，包内会按 Vite manifest 实际命中的那个自动选（配置项
`assets.vite_entries` 与 `assets.script_entries`）：

| 形态 | 路径 |
|------|------|
| 真实 Composer 安装 | `vendor/filamentboot/filamentboot-site/resources/{css,js}/…` |
| `vendor:publish --tag=filamentboot-site-assets` 之后 | `resources/css/vendor/filamentboot-site/…`、`resources/js/vendor/filamentboot-site/site.js` |
| monorepo path 仓库（vendor 是符号链接） | `packages/filamentboot-site/resources/{css,js}/…` |

### 公开页缓存

内容页响应头是 `Cache-Control: public, max-age=600`（`config('filamentboot-site.cache.public_max_age')`，
设 0 关闭），**不发 `Set-Cookie`**，可直接上 CDN 整页缓存。

- 询盘提交打的是 `POST /contact-submissions`，一条不挂 `web` 中间件组的无状态路由；
  防刷是蜜罐 + 客户端上报耗时 + 每 IP 3 次 / 5 分钟限流 + 路由级 `throttle`。
- 首触渠道归因在客户端 localStorage 里，提交时随请求体发出。
- ⚠️ 若你在内容路由上加了会起 session 的中间件，`SiteCacheHeaders` 会**自动退回不缓存**
  （带会话 Cookie 的响应绝不能标成公共可缓存），但缓存收益也就没了。

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

#### 主题清单 `theme.php`

每个主题目录下有一份 `theme.php`，声明这套主题支持什么：

```php
return [
    'label'     => '科技装修（深色）',
    'templates' => ['default', 'landing'],   // pages/templates/{key}.blade.php
    'blocks'    => ['hero', 'cta', 'faq'],   // blocks/{key}.blade.php
    'features'  => ['nested_menu' => true],  // 桌面导航有下拉版式
];
```

后台切换主题**之前**会按目标主题的清单算一遍：已发布页面用到的版式或区块若不在清单里，
就把受影响的页面逐条列出来，并要求勾选确认才放行。所以自定义主题时：

- **加了 `blocks/*.blade.php` 或 `pages/templates/*.blade.php` 就同步改清单**，否则已支持的东西会被报成不支持。
- **`features.nested_menu`** 决定 `MenuResolver` 返回层级还是摊平结构。未声明即按不支持处理，后台配出来的二级菜单项会被摊平到一层显示（不会丢）。
- 清单缺失时按目录里实际存在的视图文件推断，但 `features` 一律按不支持——文件系统看不出一个导航栏有没有下拉版式。

### 5. 发布前检查

「网站设置」页与仪表盘会提示尚未配置的发布前必填项：联系电话、公司地址、ICP 备案号、隐私政策链接、默认 SEO 标题与描述、默认 Open Graph 图、公司 LOGO。未配置的项不会在前台渲染空栏目，但会在后台持续告警。

### 6. 三层角色

`SiteRoleSeeder` 建好三个开箱可用的角色，在「角色管理」里直接分配给管理员即可：

| 角色 | 五类内容读写 | 发布 / 定时发布 | 删除与版本回滚 | 站点设置 / 导航 / 重定向 | 询盘查看与导出 |
|------|------|------|------|------|------|
| **内容编辑** | ✅ | ❌ 只能提交审核 | ❌ | ❌ | ❌ |
| **内容发布** | ✅ | ✅ | ✅ | ❌ | ❌ |
| **站点管理** | ✅ | ✅ | ✅ | ✅ | ✅ |

分层的实际意义在「内容编辑不能发布」这一条：写文案的人和对外发声负责的人往往不是同一个，`publish_site_page` 就是那道闸门。内容编辑在编辑页只看到「提交审核」，看不到「发布」与「定时发布」。

角色定义以代码为准：`SiteRoleSeeder` 用 `syncPermissions`，重跑会把手工加到这三个角色上的权限刷掉。要给某人额外权限，新建角色或直接授予用户，不要改这三个。

超级管理员沿用主包的 `Gate::before()` 放行，不需要（也不应该）授予上述任何权限点。

### 7. 搜索引擎接入（可选）

「网站设置 → SEO 默认值」下有两组选填项：

- **站长平台验证**：填百度 / Google / Bing 下发的验证串（只填串本身，不要粘整段 `<meta>`），自动输出到全站 `<head>`。字符集非法时不输出，避免把半截标签打进文档。
- **百度主动推送**：填 token 与站长平台登记的站点域名后，内容**首次进入已发布态**时自动把 URL 推给百度，比等抓取快一个数量级。只在发布状态变化时推，改正文不会烧配额（普通站 3000 条/天）。

存量内容回推：

```bash
php artisan filamentboot-site:push-baidu          # 试运行，只报条数
php artisan filamentboot-site:push-baidu --all    # 真正推送
```

未填 token 即视为关闭，不发请求也不占队列。

### 8. 站内搜索

前台 `/search?q=关键词`，两套主题各有一份结果页，导航栏与移动端抽屉都有入口。跨五类内容检索（页面、案例、方案、产品、资讯），每类最多 5 条。

搜索页固定输出 `X-Robots-Tag: noindex, follow` 且不出 canonical：关键词组合是无限的 URL 空间，被收录会产出成千上万低价值页面稀释整站权重。表单是 `method="get"`，所以每个关键词各自是一个可缓存、可分享的地址。

**两条已知边界**（不是缺陷，是实现方式的代价）：

- 匹配用 `LIKE '%词%'`，**没有相关度排序**，排序按各类型自己的自然顺序。内容量上万要换 MySQL FULLTEXT（中文还需 `WITH PARSER ngram`）。
- **区块正文搜不到。** `site_pages.blocks` 是 JSON 列，中文在里面是 Unicode 转义序列，`LIKE` 匹配不到。纯用区块搭的页面只能靠标题被搜到——正文写在富文本里的页面不受影响。

### 9. 资料索取（手册换联系方式）

页面里放一个「资料索取」区块，上传 PDF / Word / Excel / PPT / ZIP，访客提交联系方式后拿到一条**限时签名下载链接**。

```php
// config/filamentboot-site.php
'gated' => [
    'disk'     => env('SITE_GATED_DISK', 'local'),   // ⚠️ 必须是非公开磁盘
    'link_ttl' => env('SITE_GATED_LINK_TTL', 30),    // 链接有效分钟数
],
```

> ⚠️ **`disk` 指到 `public` 这道门就形同虚设**，而且不会有任何报错：文件会多出一个人人可猜的 `/storage/...` 地址，表现只是「留资率莫名很低」。默认 `local` 指向 `storage/app`，在 Web 根之外。

门由四条共同关住：前台 HTML 里只有不透明 key（没有文件路径）；下载必须带有效且未过期的签名；key 必须由某个**已发布**页面声明（草稿页的资料下不到）；判为机器人时对外回成功但不放资料。

「索取了哪份资料」会记进询盘的额外问题里，后台详情、CSV 导出与通知邮件三处都能看到。

### 10. 询盘表单加问不同的问题

「询盘表单」与「资料索取」区块都能配额外问题（最多 6 个，单行文本 / 多行文本 / 下拉），答案存进 `site_contact_messages.extra`，后台、导出、通知邮件同步可见。

> ⚠️ **必填只在浏览器里生效。** 提交端点是无状态的（公开页零 session 的连带结果），它收到的只是一份键值对，无从核对是哪份区块配置渲染出来的表单。服务端只做边界约束（条数、键长、值长）。绕过必填的代价是收到一条答得不全的线索，不是数据被污染。

### 11. 在线客服与地图

- **在线客服**：「网站设置 → 统计与代码 → 在线客服」，开关与代码分成两个字段，换供应商或临时无人值守时直接关掉即可。代码原样输出在 `</body>` 前、不过滤，仅 `manage_site_settings` 权限可改，变更写操作日志。移动端底部已有操作条，多数客服气泡会与它重叠，接入后请在真机上确认。
- **地图**：用「地图」区块。填的是地图服务商生成的**嵌入地址**（只填 `src`，不要整段 iframe HTML），只放行 https 且域名须精确命中 `config` 的 `map.allowed_hosts`（默认含百度 / 高德 / 腾讯 / Google）。文字地址建议一并填上——拦截插件、企业网络与爬虫都会丢掉 iframe，那时它是唯一还看得到的信息。

## 发布 Tag 一览

| Tag | 内容 | 目标路径 |
|-----|------|----------|
| `filamentboot-site-config` | 配置文件 | `config/filamentboot-site.php` |
| `filamentboot-site-migrations` | 内容与设置迁移 | `database/migrations/` |
| `filamentboot-site-views` | 主题视图 | `resources/views/vendor/filamentboot-site/themes/` |
| `filamentboot-site-assets` | 主题 CSS | `resources/css/vendor/filamentboot-site/` |

## 许可

MIT License，详见 [LICENSE](LICENSE)。
