# filamentboot-site — 前台官网管理插件

面向中小企业的前台官网管理插件，提供案例/方案/产品/资讯/页面/询盘六类内容管理，中文单语言，支持主题切换。

> 🏠 **官网**：https://www.xitongapp.com/ —— 本包驱动的官方产品站，`software` 主题的实例

## 简介

本包为 Filamentboot 后台增加完整的企业官网内容管理能力。注册 `SitePlugin` 后，后台将出现「官网管理」分组，包含 14 个 Resource——幻灯片（`SiteBannerResource`）、案例（`SiteCaseResource`）、方案（`SiteSolutionResource`）、套餐（`SitePackageResource`）、产品（`SiteProductResource`）、资讯文章（`NewsArticleResource`）、资讯分类（`NewsCategoryResource`）、城市页（`SiteCityPageResource`）、静态页面（`SitePageResource`）、导航菜单（`SiteMenuResource`）、菜单项（`SiteMenuItemResource`）、301 重定向（`SiteRedirectResource`）、询盘管理（`ContactMessageResource`）、站内搜索词（`SiteSearchTermResource`）——以及未读询盘小部件（`UnreadContactMessagesWidget`）。前台路由与视图由 `SiteServiceProvider` 自动注册。官网设置（公司信息、联系方式、SEO 默认值、主题、统计代码、在线客服等）通过后台「网站设置」页（`SiteSettingsPage`）管理。
>
> 部分 Resource 括号前的中文名会按 `active_theme` 变化（如「案例」在 `decoration` 主题下显示为「装修案例」），上面统一用 `software` 主题的通用叫法——两套官方模板都在维护，文档不再只照一套主题的说法写。

> **公开页零 Livewire、零 session。** 前台内容页不挂 `web` 中间件组、不起会话、不含 Livewire 组件，
> 因此可以被 CDN 整页缓存（见下文「公开页缓存」）。前台交互一律用 Alpine + 无状态端点。

> **语言范围**：当前版本只维护中文内容流。早期双语实现遗留的 `*_en` 列与站点设置项已全部删除（迁移 `2026_08_08_130000_drop_legacy_english_and_gallery_columns` 与 `2026_08_08_100001_drop_site_legacy_english_settings`），字段一律只有 `_zh` 一套。

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

执行数据库迁移（25 张内容表）：

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
| 幻灯片 | `SiteBannerResource` | 按投放位置管理首页与栏目页 Banner |
| 装修案例 | `SiteCaseResource` | 含分类、标签、封面图、图集 |
| 智能方案 | `SiteSolutionResource` | 解决方案内容管理 |
| 全屋套餐 | `SitePackageResource` | 按户型 × 档位组织，含包含清单与参考价 |
| 智能产品 | `SiteProductResource` | 含分类、品牌、价格、图集、富文本详情 |
| 资讯文章 | `NewsArticleResource` | 含分类、标签、封面图，`published_at` 控制发布与定时 |
| 资讯分类 | `NewsCategoryResource` | 扁平分类，前台列表页筛选用 |
| 城市页 | `SiteCityPageResource` | 按行政区划批量建落地页，概况字段表由 config 声明 |
| 静态页面 | `SitePageResource` | 关于我们、联系我们、常见问题等 |
| 询盘管理 | `ContactMessageResource` | 只读 + 状态流转 |
| 站内搜索词 | `SiteSearchTermResource` | 只读报表，零结果的词即内容缺口 |
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
    'vendor/filamentboot/filamentboot-site/resources/css/themes/software.css',
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

内置两套主题：`decoration`（科技装修 · 浅色）与 `software`（软件产品 · 浅色），在「网站设置 → 外观」中切换，保存后自动清除视图缓存。

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
    'label'     => '科技装修（浅色）',
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

- **站长平台验证**：填百度 / Google / Bing / 搜狗下发的验证串（只填串本身，不要粘整段 `<meta>`），自动输出到全站 `<head>`。字符集非法时不输出，避免把半截标签打进文档。搜狗那一项值得单独一提——它同时是**腾讯元宝的检索源**，做 AI 引用绕不开。
- **百度主动推送**：填 token 与站长平台登记的站点域名后，内容**首次进入已发布态**时自动把 URL 推给百度，比等抓取快一个数量级。只在发布状态变化时推，改正文不会烧配额（普通站 3000 条/天）。

存量内容回推：

```bash
php artisan filamentboot-site:push-baidu          # 试运行，只报条数
php artisan filamentboot-site:push-baidu --all    # 真正推送
```

未填 token 即视为关闭，不发请求也不占队列。

#### robots.txt 与 AI 抓取器

`config('filamentboot-site.robots')` 控制两段：通用段的 `Disallow` 路径，以及 AI 抓取器策略
（`allow` / `disallow` / `omit`，默认 `allow`，可用 `SITE_ROBOTS_AI_POLICY` 覆盖）。

> ⚠️ **`allow` 与「不写 AI 段」的实际效果完全一致**——通配符本来就覆盖那些 UA，差别只在把立场写明。
> 真要拒绝就改成 `disallow`。另外 robots.txt 的分组匹配是「只有最具体的那一组生效」，所以
> `allow` 策略下会把通用段的 `Disallow` 在 AI 组里原样重复一遍；只写一行 `Allow: /` 等于把后台放出去。

#### `/llms.txt`

站根输出一份 Markdown 索引（[llmstxt.org](https://llmstxt.org) 约定），列站点栏目与各类内容的
带说明目录，每类取前 `seo.llms_limit` 条（默认 50）。它不替代 `sitemap.xml`——后者是给爬虫的
全量机器清单，前者是给模型看的简明目录。**这是新兴约定，目前没有哪家明确声明会读它**，
做它的理由是成本极低而位置唯一，先占住不亏。

#### 爬虫抓取统计

站长平台的抓取数据滞后好几天，访问日志不滞后。AI 抓取器更是只能看日志——那几家都没有站长平台。

```bash
php artisan filamentboot-site:crawler-stats --log=/var/log/nginx/access.log
php artisan filamentboot-site:crawler-stats --since=2026-08-01 --paths
```

按 UA 归类输出抓取次数与状态码分布（**2xx 占比偏低比抓取次数少更值得先查**——那说明爬虫在撞死链）。
需要 nginx combined 格式；默认路径取 `config('filamentboot-site.crawler_stats.access_log')`。
只读不落库：日志本身就是数据源，再同步一份到数据库等于维护两套真相。

### 8. 站内搜索

前台 `/search?q=关键词`，两套主题各有一份结果页，导航栏与移动端抽屉都有入口。跨五类内容检索（页面、案例、方案、产品、资讯），每类最多 5 条。

搜索页固定输出 `X-Robots-Tag: noindex, follow` 且不出 canonical：关键词组合是无限的 URL 空间，被收录会产出成千上万低价值页面稀释整站权重。表单是 `method="get"`，所以每个关键词各自是一个可缓存、可分享的地址。

**两条已知边界**（不是缺陷，是实现方式的代价）：

- 匹配用 `LIKE '%词%'`，**没有相关度排序**，排序按各类型自己的自然顺序。内容量上万要换 MySQL FULLTEXT（中文还需 `WITH PARSER ngram`）。
- **区块正文搜不到。** `site_pages.blocks` 是 JSON 列，中文在里面是 Unicode 转义序列，`LIKE` 匹配不到。纯用区块搭的页面只能靠标题被搜到——正文写在富文本里的页面不受影响。

#### 搜索词统计

每次搜索按「词 + 累计次数 + 最近一次结果条数」写进 `site_search_terms`，后台「官网管理 → 站内搜索词」查看。
**不记 IP、UA 或任何身份信息**，要回答的是「大家在搜什么」而不是「谁搜了什么」。用
`SITE_SEARCH_LOG_TERMS=false` 关闭。

这张表最该看的是**结果数为 0 的那一档**——访客明确表达了需求而站上答不上来，每一条都是内容缺口，
所以后台默认就把它们排在最前面。热词榜谁都会看，这一档才真正决定下一批写什么。

写入走一条 `on duplicate key update` 原子语句：搜索是并发的，读一次再写一次会丢计数。
记录失败一律吞掉，绝不能把搜索页打成 500。

### 9. 标签聚合页

前台 `/tags/{slug}`，两套主题各有一份视图。标签在案例 / 方案 / 套餐 / 产品 / 资讯五类详情页底部渲染成链接，点进去是该标签下的跨类型聚合。

**标签是站上唯一一条跨内容类型的通路。** 分类、户型、风格这些维度都只在自己那一类里有效，只有标签能把一个「节能环保」下的案例、方案、套餐、资讯串到一起。做这一页之前它们只是不可点的灰字，一条内链都不产生。

三条实现口径：

- **只出已发布内容**，草稿从这条路径泄露和从列表页泄露是一回事。
- **该标签下没有任何已发布内容时返回 404**，且不进站点地图。后台随手建个标签还没挂内容是常态，每建一个就多一个空页面被收录，那是在稀释整站权重。
- 每类最多 24 条，超出时给出「看全部」指向该类型的列表页——聚合页处在「详情 → 标签 → ?」这条路径的中间，不给出口就是把一个断头路换成另一个。

取数在 `Cms\Services\TagContent`，返回结构与 `SiteSearch::search()` 一致，宿主要换口径 bind 掉即可。

> ⚠️ 加内容类型时要同时改三处：该模型的 `tags()` 正向关系、`SiteTag` 上的反向关系、`TagContent` 的 `groups()` 与 `hasContent()`。资讯这一条长期只有前两处的第一处——`SiteTag` 上没有 `news()`，于是「从标签查内容」整整少一类，而资讯恰恰是打标签最多的。

### 10. 城市页

一套「按行政区划批量建落地页」的机制。前台三段层级，两套主题各三份视图：

```
/city                      总索引，按省分组平铺全部已发布城市
/city/{省}                 该省的城市列表
/city/{省}/{市}            城市页
```

⚠️ **`/city/{省}` 有两种产出。** 该省级区划自己挂了城市页就直接渲染城市页（直辖市走这条），否则渲染下辖城市列表。北京没有「下辖地级市」那一层，硬套三段会造出 `/city/beijing/beijing` 这种与省页内容完全一样的第二个地址。

#### 包放机制，数据归宿主

| | 在哪 |
|---|---|
| 区划表 `site_regions`、城市页表 `site_city_pages`、路由、视图、结构化数据 | 包 |
| 区划数据文件 | **宿主**，路径由 `--file` 给，包不随身携带 |
| 概况表有哪些字段 | **宿主 config**，包里默认空数组 |

`config('filamentboot-site.city_pages.profile_fields')` 声明 `site_city_pages.profile` 里能出现哪些字段（key / label / type / unit / help / options），**后台表单与前台模板都从它生成**。所以包本身不认识「气候类型」「供暖方式」这些词——那是装修行业的口径，换个行业装这个包，同一张表里该放的是别的东西。配错的条目由 `Modules\Corporate\Cities\CityProfile` 静默丢掉，宁可少一个字段也不在页面上渲染一个坏格子。

#### 导入区划

```bash
php artisan filamentboot-site:import-regions --file=database/data/regions.json [--dry-run]
```

输入是嵌套 JSON，**每个节点必须写出 `level`**：

```json
[{"code":"420000","level":1,"name":"湖北省","short_name":"湖北","slug":"hubei","children":[
  {"code":"420100","level":2,"name":"武汉市","short_name":"武汉","slug":"wuhan","children":[
    {"code":"420102","level":3,"name":"江岸区"}]},
  {"code":"429004","level":3,"name":"仙桃市"}]}]
```

⚠️ **层级不能用嵌套深度推。** 上面最后那条就是原因：仙桃是省直辖县级市，直接挂在湖北省下，深度 2 而层级 3；直辖市更彻底，北京的 16 个区全部直接挂在省级下。「谁的下级」和「第几级」在中国的行政区划里本来就不是一回事。

命令**幂等**（按 `code` upsert），且**不删任何东西**——文件里没有、库里有的记录只报出来，因为那些记录底下很可能挂着城市页。第 1、2 级必须有 `slug`（要进 URL），第 3 级不要求：它不建页，导进来只为渲染「下辖区县」。

**slug 必须随数据给，命令不做拼音转换。** 自动转一定错：`陕西` 与 `山西` 的拼音都是 `shanxi` 机器分不开；地名多音字（长治 / 朝阳 / 漯河 / 昌都 / 阿勒泰…）主流拼音库也读不对。slug 是 URL 的一段，改一次就是一条死链，属于要人工确认的数据。

slug 只在**同一个上级下**唯一，所以 `/city/jiangsu/taizhou`（泰州）与 `/city/zhejiang/taizhou`（台州）、`/city/jilin` 与 `/city/jilin/jilin` 都是合法且不冲突的。

#### 页面主体是渲染出来的，不是写出来的

`site_city_pages.content_zh` **正常应该是 NULL**。页面由模板从概况表、下辖区县、同省城市渲染——三百多个城市不该有三百多篇正文。填了它是**追加**在概况之后，留给「这个城市确实有值得单独说的东西」的个别情况。

模板里的共用文字**刻意压到最少**（只有一个 CTA，没有推荐位）：这一页会被复制三百多次，每多一句写死的话就在三百多个页面上多一段一模一样的正文。

#### 结构化数据

城市页是 `Service` + `areaServed`，**明确不用 `LocalBusiness`**——那个类型要求 `address`，而在这三百多个城市里通常没有实体经营场所，填进去就是编一个不存在的地址。`areaServed` 的 `@type` 按区划名判：以「市」结尾用 `City`，自治州 / 地区 / 盟用 `AdministrativeArea`。省页与总索引是 `CollectionPage`。

#### 站点地图分片

`/sitemap.xml` 变成**索引**，指向 `/sitemap-content.xml` 与 `/sitemap-city.xml`。分片的理由不是体积（离 50000 条上限差着两个数量级），是**站长平台按提交的分片分别统计收录率**——「城市页收录了多少」这个数字混在一份地图里根本读不出来。一条城市页都没发布时索引里不出现城市分片。

### 11. 资料索取（手册换联系方式）

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

### 12. 询盘表单加问不同的问题

「询盘表单」与「资料索取」区块都能配额外问题（最多 6 个，单行文本 / 多行文本 / 下拉），答案存进 `site_contact_messages.extra`，后台、导出、通知邮件同步可见。

> ⚠️ **必填只在浏览器里生效。** 提交端点是无状态的（公开页零 session 的连带结果），它收到的只是一份键值对，无从核对是哪份区块配置渲染出来的表单。服务端只做边界约束（条数、键长、值长）。绕过必填的代价是收到一条答得不全的线索，不是数据被污染。

### 13. 在线客服与地图

- **在线客服**：「网站设置 → 统计与代码 → 在线客服」，开关与代码分成两个字段，换供应商或临时无人值守时直接关掉即可。代码原样输出在 `</body>` 前、不过滤，仅 `manage_site_settings` 权限可改，变更写操作日志。移动端底部已有操作条，多数客服气泡会与它重叠，接入后请在真机上确认。
- **地图**：用「地图」区块。填的是地图服务商生成的**嵌入地址**（只填 `src`，不要整段 iframe HTML），只放行 https 且域名须精确命中 `config` 的 `map.allowed_hosts`（默认含百度 / 高德 / 腾讯 / Google）。文字地址建议一并填上——拦截插件、企业网络与爬虫都会丢掉 iframe，那时它是唯一还看得到的信息。

## 内容契约

下面这几条不是用法，是**包现在的行为规格**：测试照它们写，改包代码时不能绕过。定制主题、加内容类型、加区块之前先读一遍。

### 页面状态流转

```text
draft → review → scheduled → published → archived
```

- **`draft` / `review` / `scheduled` 一律不得出现在公开查询里。** 前台每一处取内容都走 `published()` scope，不是靠视图层过滤——漏一处就是草稿泄露。
- **`published_at` 到期之后才允许公开访问。** 定时发布不是"到点改状态"，是查询条件的一部分。
- **预览必须要后台会话或短期签名 URL。** `/preview/{page}` 是全站唯一读未发布内容的入口，靠 `auth('admin')` 判权，**绝不能被打上 `public` 标记**——公开页整页缓存是共享缓存，草稿一旦进去就是对所有人可见。
- 内容删除走软删除；`SeedsBySlug` 用 `withTrashed()` 查重，**不复活软删过的记录**——用户删它是有意的。

### 加一个区块要同时给齐五样

页面正文存成受控 JSON，后台用 Filament Builder 编辑。少给一样，症状都是运行时才炸：

1. **稳定的区块键**（`hero`、`rich-content`、`feature-grid`、`cta`、`faq` …）——键进了数据库就不能改，改了等于让已发布页面指向不存在的区块。
2. **后台表单 Schema**。
3. **前台 Blade 渲染器**，落在每一套主题的 `blocks/{key}.blade.php`。
4. **输入验证与默认值**——老页面的 payload 里不会有你新加的字段。
5. **媒体字段的必填与 alt 文本规则**。

然后**同步改每套主题的 `theme.php` 清单**（见上文「主题定制」）。清单只覆盖 `templates` / `blocks` / `features`，**component 表达不了**——新增跨主题组件时切换预检查抓不到"另一套主题缺这个"，只能靠自己两边都写。

### 六条不变量

改动涉及内容发布链路时，这六条要么已有测试守着、要么你得补上：

1. 内容编辑能建草稿但**不能直接发布**；发布者才能审核与发布。
2. 未发布、未来定时发布、已归档的内容**无法通过公开 URL 访问**。
3. 授权用户可预览草稿，**未授权用户不可**。
4. 每个已发布页面都有有效 canonical、SEO 回退与站点地图条目。
5. 案例 / 方案 / 产品 / 套餐 / 资讯 / 静态页面六类改 slug 后**自动建一条 301 重定向**（默认发生，通知里可撤销）。
6. 页面里只允许主题已声明的区块，**不支持任意 HTML、Blade 或 PHP 执行**。

### 安全边界

- 富文本统一过 HTML 白名单（见上文「富文本过滤」），**内容字段里不执行 Blade、PHP 或任意脚本**。
- 询盘保留频率限制、蜜罐、防刷与 PII 权限控制。提交端点无状态，服务端只做边界约束。
- 前台**只查已发布内容**，所有动态 slug 走参数绑定。
- 媒体上传统一用主包的上传配置；图片要有 alt 文本、合理尺寸与封面比例约束。
- 主题只能来自 Composer 已安装的包或宿主受控的发布目录，**不支持后台上传并执行任意主题代码**。

### 架构上刻意不做的

- **不做 Headless / 公开内容 API 撑前台。** 前台是 Laravel 路由 + Controller + Blade 服务端渲染，Vite 只负责样式与 `site.js`。
- **不做可执行 PHP / Blade 的自由页面编辑器**，也不做无限自由拖拽排版——区块必须由主题和插件显式声明。
- **不做运行时主题市场**（后台上传主题包并执行）。
- **不把电商、会员、支付、订单一次性内置进核心。** 内容域按模块隔离在 `src/Modules/` 下，核心留在 `src/Cms/`。

## 发布 Tag 一览

| Tag | 内容 | 目标路径 |
|-----|------|----------|
| `filamentboot-site-config` | 配置文件 | `config/filamentboot-site.php` |
| `filamentboot-site-migrations` | 内容与设置迁移 | `database/migrations/` |
| `filamentboot-site-views` | 主题视图 | `resources/views/vendor/filamentboot-site/themes/` |
| `filamentboot-site-assets` | 主题 CSS | `resources/css/vendor/filamentboot-site/` |

## 许可

MIT License，详见 [LICENSE](LICENSE)。
