# 官网 CMS 已完成 tasks

> 文档定位：**只记已交付的**，逐项写清落点文件、与原计划的差异、开工后才确定的细节，供回查与排查用。
>
> 还没做的见 [未完成 tasks](未完成tasks.md)。
>
> 更新时间：2026-08-04（第四轮 B/C 组交付后）
>
> 上游规划：[基于装修网站官网优化 CMS](基于装修网站官网优化cms.md)

---

## 交付轮次总览

| 轮次 | 内容 | 提交 |
|------|------|------|
| 第 1 轮 | 阶段 1 线上缺陷收口 | `6d511dc`（77 个文件） |
| 第 2 轮 | B2 分页 canonical、A 组线索链路、#11 页面数据模型、#12 区块契约 | `1e7aca4` `8278942` `3b0ebc8` `bf4ca87` `88c8be0` |
| 第 3 轮 | 内容承载扩容 + 资讯模块 + 富文本渲染修复 + CC0 封面图 | `f6a49bd`…`a42c959`（10 个提交） |
| 第 4 轮 | B 组 SEO 基建、C 组转化与防刷 | `73f927b` `ee3fbfb` |

当前累计验证状态：

```
composer test        561 通过 / 2026 断言
composer pint:test   通过
composer phpstan     0 告警（根项目，扫 app + database）
主包 composer test    83 通过 / 250 断言
站点包元数据测试      9 通过
站点包 level 6       10 个存量告警（见文末「已知存量问题」）
```

---

## 第 1 轮 · 阶段 1 线上缺陷收口

提交 `6d511dc`，77 个文件。

修掉四个**根因级**缺陷：

1. 站点设置断链——控制器传 `$settings`、视图读 `$siteSettings`，变量名错位导致设置从未真正注入前台
2. 封面图读了一个不存在的属性
3. CTA 与询盘面板不在同一 Alpine 作用域，点了没反应
4. 缺 `registerMediaConversions()`，媒体转换全部没生成

同时补齐：路由挂载模式（prefix / root / domain）、主题视图解析优先级、tech-product 全部页面、sitemap/robots、发布前健康检查、WCAG AA 对比度。

验证：`php artisan test` 378 通过；包测试 9 通过；Playwright 21 通过；Pint 通过。

---

## 第 2 轮 · 线索链路 + CMS 数据底座

范围：B2、A 组全部（A1–A4）、#11、#12。验证：468 通过（本轮前基线 378）；主包 83；站点包元数据 9；Playwright 21 通过 3 跳过；Pint 通过；根 PHPStan 0。

### ✅ #11 · 页面数据模型演进与新表迁移

落点：`database/migrations/2026_08_03_100004`–`100008`、`src/Enums/PageStatus.php`、`src/Models/{SitePage,SitePageRevision,SiteMenu,SiteMenuItem,SiteRedirect}.php`、`database/factories/SitePageFactory.php`。

`site_pages` 加 `template`、`blocks`(json)、`status`(index)、`published_at`(index)、`seo_og_image`；新建 `site_page_revisions` / `site_menus` / `site_menu_items` / `site_redirects` 四张表；定义 `PageStatus` 枚举（`draft → review → scheduled → published → archived`）。

`scopePublished()` 改为 `status = published AND (published_at IS NULL OR published_at <= now())`——**用查询过滤实现定时发布，不引入队列或定时任务**：少一个必须常驻运行的组件，就少一处「忘了起 worker 导致内容不上线」的故障点。

与原计划的差异，以及开工后才确定的细节：

- **`is_published` 旧列保留**（已确认的决策），由 `SitePage` 的 `saving` 钩子镜像为 `status === published`，保留期内不会停在过期值上。**删除时机已定在阶段 3 目录重构**（原文写「随包重命名一起做」，但改名已取消，那个锚点不存在了）。
- **`site_menu_items.parent_id` 直接建成 `unsignedBigInteger default 0` 且不加外键**。主包 `menus` 表当初建成 nullable 外键，为适配 filament-tree 又追加了一个迁移去外键、刷 NULL 为 0、改列——这笔学费没有再交第二次。
- **`SiteMenuItem` 需覆盖三处 ModelTree 约定**：`determineOrderColumnName() => 'sort'`、`determineTitleColumnName() => 'label'`、`defaultParentKey() => 0`。
- **顺带做了 #14 的最小外溢**：`scopePublished()` 改读 `status` 后，`SitePageResource` 的 `Toggle::make('is_published')` 必须同时换成 `Select::make('status')` + `DateTimePicker::make('published_at')`，否则「后台点了发布、前台看不到」。完整状态流转 Action 与按状态分 Tab 仍属 #14。
- **`SiteDemoSeeder` 的页面种子同步改用 `status` + `published_at`**：继续写 `is_published` 会被 saving 钩子覆盖成 false，新装机的三个静态页会全部不可见。

> 命名提示：`database/seeders/SiteMenuSeeder.php` 管的是**后台侧边栏**菜单（写主包 `menus` 表），与前台 `SiteMenu` 模型无关。改名会牵动 `composer.json` 的 `post_install.seeders`。

测试：`tests/Feature/SitePageStatusTest.php`——draft / review / scheduled（未到期）/ archived 四态公开 URL 均 404 且均不进 sitemap；定时发布到点后自动可见。

### ✅ #12 · 区块契约与注册表

落点：`src/Cms/Blocks/{BlockContract,AbstractBlock,BlockRegistry}.php` + 七个区块类（`HeroBlock`、`RichContentBlock`、`MediaTextBlock`、`FeatureGridBlock`、`CtaBlock`、`FaqBlock`、`ContactFormBlock`），在 `SiteServiceProvider::register()` 中注册为容器单例。测试：`tests/Unit/Cms/BlockRegistryTest.php`（31 例）。

- 注册表**同时充当安全白名单**：`register()` 校验 key 只允许 `^[a-z0-9]+(-[a-z0-9]+)*$` 且拒绝重复注册；`get()` 对未知 key 返回 null 而不抛异常——渲染层遇到历史遗留的失效区块应跳过并记日志，不能把整页打成 500。
- `AbstractBlock` 提供 `validate()`（按 `rules()` 校验 payload）与 `withDefaults()`（补齐历史 payload 缺失字段）。
- 含媒体的区块强制 alt：`hero` 用 `required_with:image`，`media-text` 图与 alt 均必填。
- `ContactFormBlock.source` 的字符集规则与 `ContactForm::normalizedSource()` 保持一致，否则后台填的来源入库时会被静默剥掉而对不上账。
- **区块 Blade 视图属于 #13，本轮未建**。`view()` 只返回视图名字符串，单测据此只断言命名规范，不断言视图存在。宿主可用 `app(BlockRegistry::class)->register(new MyBlock)` 追加自定义区块。

### ✅ A 组 · 线索链路打通

| 任务 | 落点 | 关键实现 |
|------|------|----------|
| A1 来源与归因 | `2026_08_03_100001` 迁移、`src/Http/Middleware/CaptureVisitorAttribution.php`、`ContactForm`、`contact-form.blade.php`、`ContactMessageResource` | 首触归因写 session（Livewire 提交打的是 `/livewire/update`，拿不到落地页 URL 与原始 Referer，session 是唯一跨得过这两次请求的载体）；中间件只挂内容页路由组，sitemap/robots 不为爬虫开 session；source 由视图根元素 `x-effect="$wire.set('source', ..., false)"` 从 Alpine store 同步，第三参数 false 表示不发额外网络请求 |
| A2 邮件通知 | `src/Services/ContactMessageNotifier.php`、`src/Mail/NewContactMessageMail.php`、`resources/views/mail/` | 只做邮件（已确认，不接 webhook）；`Mail::to()->queue()` 走队列，**整段包 try/catch 并 report()**——`queue()` 在队列后端不可用时会抛，访客侧成功提示不能依赖通知结果；非法收件人地址逐个过滤，一个填错不影响其余 |
| A3 统计与转化事件 | `resources/views/shared/components/analytics.blade.php`、两套主题 `base.blade.php`、`SiteSettingsPage` | 结构化 ID 优先且**格式校验后才输出**（ID 会拼进 script src）；自定义代码块用 `manage_site_settings` 权限 `disabled()` 门住 + `afterSave()` 比对前后值记操作日志；转化事件由 `ContactForm` 派发 `site-contact-submitted`，analytics 组件监听后 push 到 `_hmt` / `gtag` |
| A4 导出与跟进 | `src/Filament/Exporters/ContactMessageExporter.php`、`ListContactMessages`、`ViewContactMessage`、`2026_08_03_100002`/`100003` 迁移、`ContactMessageNote` | 导出照主包 `ListLoginLogs` 的 `ExportAction → authorize('export_contact_message') → after() 记 activity` 三段式；跟进人与备注的外键都是 `nullOnDelete`——线索与跟进记录是业务资产，不能因人员离职而消失 |

> A3 自定义代码块的「无权限保存不会抹空已有脚本」依赖两层机制叠加：Filament 不 dehydrate `disabled()` 字段，而 Spatie 的 `Settings::fill()` 只覆盖传入的键。这是推断出来的行为，已用 `tests/Feature/SiteAnalyticsInjectionTest.php` 锁住——任一侧实现变化都会立刻失败，而不是静默清空线上脚本。

### ✅ B2 · 分页 canonical 修正

`seo-meta.blade.php` 改为保留 `page` 等区分内容的参数、只剥追踪参数，参数按键 `ksort` 后拼回，保证同一组参数不同顺序产生同一个 canonical。

此前 canonical 取 `url()->current()`（不含查询串），`/solutions?page=2` 的 canonical 指向 `/solutions`，等于告诉搜索引擎列表页第 2 页往后全是第 1 页的副本，深层内容不会被索引。

待剥参数清单放在 `config('filamentboot-site.seo.canonical_ignored_params')`（默认含 utm 五项、gclid、fbclid、msclkid、yclid、bd_vid、_bd_vid、spm），宿主接新渠道时追加即可。测试见 `tests/Feature/SiteSeoMetaTest.php`。

### 本轮顺带修掉的两个存量缺陷

1. **设置页任一 `string` 字段留空保存即 500**。Filament 把空文本框归一为 null，而 `SiteSettings` 的 `phone`/`address_zh`/`icp_number`/`privacy_url`/`seo_*` 都声明为非空 `string`，Spatie 的 `Settings::fill()` 直接抛 `Cannot assign null to property`。也就是说「生产收尾」清单里那些待填字段，只要有一个留空就存不下来。已在 `SiteSettingsPage::mutateFormDataBeforeSave()` 用反射按声明类型归一，新增字段自动受同一规则保护。测试：`tests/Feature/Settings/SiteSettingsPageTest.php`。
2. **官网权限点从未被任何 seeder 创建**。`view_any_site_case` 等只在 `SiteMenuSeeder` 里被引用，除超管（主包 `Gate::before`）之外没有角色能被授予官网管理权限。已补 `SitePermissionSeeder`（含 #19 需要的 `manage_site_settings` 与 A4 的 `export_contact_message`），并注册进 `composer.json` 的 `post_install.seeders`。

### 顺带解掉的测试基础设施限制

官网插件的 Filament 资源路由在应用 boot 时注册，而插件启用状态来自 `plugins` 表，测试库那时还没有数据，因此后台资源页一直无法在测试里渲染。

`tests/Feature/SiteContactResourcePageTest.php` 的做法是**手工把插件注册进面板、重跑 `vendor/filament/filament/routes/web.php` 再刷新路由名查找表**——后续 #14/#17/#18 的后台页面都可以照此写测试，不必留给手工点击。

---

## 第 3 轮 · 内容承载扩容 + 资讯模块

10 个提交（`f6a49bd`…`a42c959`）。起因：CMS 骨架完整但**内容是空壳**——封面图全是 SVG 占位、文案是 seeder 拼的模板句，而且**现有页面装不下素材**（产品详情页仅 74 行、无图集无正文；案例无「客户本人说了什么」；资讯模块完全不存在）。

### 数据层（`a93647e`）

- `site_products` 加 `content_zh` / `content_en`，模型注册 `gallery` 媒体集合（`HasCoverImage` 已内建 `galleryUrls()`，转换也已 `performOnCollections('cover','gallery')`，只差注册集合）
- `site_cases` 加 `customer_name` / `customer_quote` / `customer_meta` + `avatar` 单文件集合
- 新建 `site_news_categories` / `site_news_articles` 两张表

**资讯模块直接建在阶段 3 的目标路径 `src/Modules/News/`**，规避「阶段 3 重构时搬两遍」。命名空间 `Filamentboot\FilamentbootSite\Modules\News\`。

> ⚠️ `BasePolicy` 从**短类名**推导权限点名，`NewsArticle` → `news_article`，与命名空间无关。

### 后台（`55baf98`）

`SiteProductResource` 加图集上传与富文本；`SiteCaseResource` 加「业主见证」Section；新建 `NewsArticleResource` + `NewsCategoryResource`（含 Pages 三件套）并注册进 `SitePlugin`。

### 前台双主题（`0a221f9`）

产品图集轮播（Alpine，不引新 JS 依赖）、案例页「业主说」卡片、首页见证轮播、资讯列表/详情/归档三页、`news-card` 组件、导航加资讯入口。**每个视图两套主题各一份完整副本。**

> 见证不取 featured 案例：置顶与否是编辑对案例本身的排期判断，跟这条案例有没有配业主原话是两回事，用 featured 过滤会让大量见证白填。

### 路由与 SEO（`0efe881`）

`routes/site.php` 在 `/{slug}` 兜底**之前**加 `/news`、`/news/{slug}`、`/news/archive/{year}/{month}`；`SitemapController` 加 `NewsArticle::published()` 循环；`seo-meta.blade.php` 首次输出 JSON-LD（文章与案例 `Article`、产品 `Product`）。

> 归档月份在 PHP 里分组而非交给数据库的日期函数：MySQL 的 `DATE_FORMAT` 与 SQLite 的 `strftime` 语法不同，宿主换驱动就会炸。

### 富文本渲染修复（`f9ebb76`）

**这是本轮发现的真实前台缺陷**，两个独立问题叠在一起：

1. **详情页正文里的排版被静默吃掉**。各详情页直接调 `app('purifier')->clean($content)`，退回 mews/purifier 的 default 画像——那份白名单只有十来个标签，h2/h3 被合并进一个 `<p>`、`x<sup>2</sup>` 变成 `x2`、表格整个塌掉。
   → 新建 `src/Support/RichText.php`，白名单**写在包内**、与后台 RichEditor 的默认工具栏对齐，不依赖宿主的 `config/purifier.php`。宿主想自定义就在 `filamentboot-site.purifier_profile`（或 `SITE_PURIFIER_PROFILE`）填画像名，包内白名单让位。
   → 用数组画像而非注册 config 画像：下游装了包不改配置也能拿到确定的过滤行为。
2. **`.prose` 类根本没有定义**。项目未装 `@tailwindcss/typography`，而 Tailwind v4 preflight 会把 `h1–h6` 重置为与正文等大、`ol/ul` 去掉符号、所有元素清零 margin。也就是说过滤放行了标签，样式层又把它们打回裸态。
   → 两套主题的 CSS 各写一份 `@layer components { .prose … }`（约 140 行），刻意做出差异：decoration 的 `h2` 用主色左边框，tech-product 用底部细线。表格 `display:block; overflow-x:auto` 让宽表在自己的容器里滚动。

测试：`tests/Feature/SiteRichTextTest.php`（5 例，含「两套主题 CSS 都定义了 `.prose` 各元素」的断言）+ `SiteContentRenderTest` 的路由级验证。

### 素材灌入与种子改造（`e1b1b0e`）

演示内容扩到 18 个产品 + 11 篇资讯。

**种子从「整体跳过」改为「按 slug 增量补种」**：原先 `if (SiteCase::query()->exists()) return;` 导致升级后新增的演示内容永远补不上。改造要点：

- 新建 `Concerns/SeedsBySlug` trait。slug 列上是**普通 unique 索引，不带 `deleted_at`**，所以对软删除过的 slug 直接 `firstOrCreate` 会 INSERT 然后 500 —— trait 里用 `withTrashed()` 查。
- 改增量后暴露一个被旧守卫掩盖的 bug：**随机标签会每次重跑累积**。用 `wasRecentlyCreated` 守住。
- 封面图挂载**故意保持无条件执行**：图片后补的也能被下一次重跑捡起来。

### CC0 封面图（`91ec2da` `61eba4a` `a42c959`）

案例 / 方案 / 资讯 20 张封面已上站，全部 CC0 或公有领域（**只收无署名义务的许可**，CC-BY 要求可见署名，那是产品层面的改动）。图片本身不入库，入库的是能重新生成它的 `scripts/` + `queries.json` + `selection.json`。详见 [cc0-assets](cc0-assets/README.md)。

三条值得记住的结论：

1. **搜 `<题材> Unsplash` 而不是按题材直搜**。Commons 上的公有领域素材绝大多数是 1930 年代档案扫描件，年代不搭；而 2017 年前的 Unsplash 是 CC0 且被批量导入了 Commons，文件名带 `(Unsplash)` 后缀。这一招把可用数从 8 拉到 20。
2. **Openverse API 被 Cloudflare 人机验证拦截**，`source.unsplash.com` 已关停，Unsplash/Pexels 官方 API 要 Key——所以选 Commons。
3. **必须人眼过一遍**：许可与尺寸筛不掉可识别人脸、第三方品牌字样、年代感这三类，本轮据此换掉过三张。

### 后台导航修复（`f6a49bd`）

后台导航统一返回 `NavigationGroup`，修正折叠态图标与子页高亮。

---

## 第 4 轮 · B 组 SEO 基建 + C 组转化与防刷

两个提交 `73f927b` `ee3fbfb`。验证：561 通过 / 2026 断言（本轮前 550）；Pint 通过；根 PHPStan 0；站点包 level 6 未新增告警。

### ✅ B3 · 面包屑导航

数据由 `SiteFrontController::breadcrumbs()` **统一构建一次**，两处消费：两套主题各一份 `breadcrumb.blade.php`，以及 B1 的 `BreadcrumbList` 结构化数据。这样杜绝了「页面上显示三级、结构化数据里只有两级」。

覆盖案例 / 方案 / 产品 / 资讯 / 归档 / 静态页六类页面。未归类的资讯跳过分类层，不留一个指向 `/news?category=` 的空链接。

- **组件不带宽度容器与左右内边距**：各调用页正文区宽度不一（`max-w-3xl` / `5xl` / `screen-xl`），组件自带容器会让面包屑与正文左边缘对不齐。
- 列表页与首页不出面包屑：只有两级，没有信息量。
- 当前页用 `aria-current="page"` 而非 `<a>`。

### ✅ B1 · JSON-LD（Organization + BreadcrumbList）

- **`seo-meta.blade.php` 的 `$seoData['jsonLd']` 改为可接节点列表**。此前只吃单个关联数组，一个页面同时要 `Article` 与 `BreadcrumbList` 就装不下。用 `array_is_list()` 判别，单节点写法向后兼容。
- **Organization 只在首页输出**完整节点（含 `url` / `telephone` / `address`）：品牌词知识面板锚在首页，详情页里它已作为 `publisher` 嵌在 `Article` 与 `Product` 内部。站点设置未填的字段一律不输出——结构化数据里出现空字符串比缺字段更糟。
- **FAQPage 未做**，数据源是页面里的 `faq` 区块，已移交 #13 一起落地。

### ✅ B4 · 站长验证位与百度主动推送

**验证 meta**：三个 `*_verify_code` 设置项（百度 / Google / Bing），输出前正则校验字符集。值会进 `<meta content>`，最常见的填写错误是把整段标签粘进来——此时宁可不输出，也不要把半截标签打进 head。纪律与 A3 的统计 ID 一致。

**主动推送**（`src/Services/BaiduPushService.php` + `src/Jobs/PushUrlsToBaidu.php` + `src/Observers/SearchPushObserver.php`）：

- 只在**发布状态列变化**「且」回查各模型自己的 `published()` 作用域确认可见时才派发。改错别字不烧配额（百度普通站 3000 条/天）。
- 可见性判断**逐个 case 写死具体类**而非 `$model->newQuery()->published()`：后者经 `Builder<Model>` 调用时静态分析看不见作用域。写成具体类既保住类型，又仍然复用各模型自己的发布判据——四类内容判据本就不同（`SitePage` 看 `status`、产品看 `is_published`、其余看 `published_at`）。
- Service 整段吞异常并 `report()`，与 A2 同一条纪律：推送失败是运维问题，不能把后台保存打成 500。
- **未配置 token 即视为关闭**：不发请求、不排队、不写日志。大多数下游装了包不会用百度推送，不该让他们的日志被刷屏。
- `php artisan filamentboot-site:push-baidu [--all]` 做存量回推，默认试运行只报条数。

测试：`tests/Feature/SiteSearchPushTest.php`（16 例）。

### ✅ C1 · 移动端底部三段式操作条

一键拨号 / 微信咨询 / 在线留言，两套主题各一份完整实现，只在 `sm` 以下出现。

- **同时给悬浮气泡加 `hidden sm:inline-flex`**：两个入口在移动端同屏是重复噪音，气泡还会压在操作条上。滑入面板不受影响，操作条的「在线留言」调的就是同一个 `$store.contactPanel.show()`。
- `tel:` 里的号码去掉空格与横杠——带分隔符在部分安卓拨号盘上解析失败。
- 缺数据的段落整段不渲染，三段自动收敛为两段甚至一段，不留死按钮。
- `main` 的避让从 `pb-24` 改为 `pb-32 sm:pb-24`：操作条 56px 条高加安全区已经逼近原来的 96px。
- 新增来源标识 `mobile-bar` 并登记进 `config` 的 `contact.sources`。

### ✅ C2 · 表单蜜罐与提交耗时校验

- 蜜罐用**屏外定位而非 `display:none`**——后者是已知特征，成熟脚本会跳过。
- `mount()` 记渲染时刻，提交间隔不足 3 秒判为机器。Livewire 对公开属性做 checksum 校验，客户端改不动这个值，不需要额外签名。
- **命中任一条件时「静默成功」**：不入库、不通知、不派发转化事件，但界面照常显示成功。回一个错误等于在教脚本怎么绕过；转化事件若也派发，投放后台的转化数会被灌水，比漏报更难排查。
- 判断放在 `rateLimit()` **之前**：否则同一出口 IP 下的脚本三下就能把整个办公室或小区的真实访客一起锁在门外。

> `ContactFormTest` 的 9 个既有提交用例加了 `humanPace()`（`travel(4)->seconds()`）：`Livewire::test()` 里 mount 与 call 只隔几微秒，不推进时钟就会被新规则挡下——这不是绕过测试，正是该规则应有的行为。

### 本轮顺带修掉的存量缺陷

**`SitePackageMetadataTest` 本来就是红的**：第 3 轮资讯模块加了两张表，README 还写「14 张内容表」，实际 16 张。该测试不在 `composer test` 里（站点包没有独立 `vendor/`，要用根项目 PHPUnit 单独指文件跑），所以一直没暴露。

---

## 已知存量问题

### 站点包 PHPStan level 6 的 10 个告警

**不是老文档写的 6 个**——多出的 4 个来自第 3 轮的资讯模块。根 `phpstan.neon` 只扫 `app` 与 `database`，所以 `composer phpstan` 一直是绿的。

| 文件 | 告警 |
|------|------|
| `SiteCaseResource` / `SitePageResource` / `SiteProductResource` / `SiteSolutionResource` / `NewsArticleResource` | `getEloquentQuery()` 返回类型 ×5 |
| `ContactMessage` / `SiteTag` | `HasFactory` 泛型未声明 ×2 |
| `SiteFrontController` | `newsIndex` 的 `published()`、`newsArchiveMonths()` 返回类型、`?->name_zh` 多余的 nullsafe ×3 |

按仓库约定「无关问题提及但不处理」，未修。**新增代码不应让这个数字变大。**

### 素材空缺

- **18 个产品封面全部空缺**。产品图要与型号对得上，CC0 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。等品牌方渠道商素材包。
- **`news/is-voice-control-useful` 空缺**。CC0 池里「智能音箱」清一色是 Amazon Echo Dot 的棚拍图，放到自有品牌站上等于替竞品打广告。留空反而给前台占位降级留了个活样本。

### 已取消的事项

**包重命名 `filamentboot-site` → `filamentboot-cms` 已于 2026-08-03 取消**：收益小、风险实在。`PluginManager::syncFromInstalled()` 按 slug upsert，改名后会新建行、留下孤儿旧行、`is_enabled` 不继承（阶段 1 期间已在本地 DB 实测确认）。连带取消 `plugins` 表 slug 数据迁移。

沿用 `filamentboot-site` 包名与 `Filamentboot\FilamentbootSite\` 命名空间，新增代码不做任何改名预留。

> 副作用：`is_published` 旧列原定「随包重命名一起删」，锚点没了，已改挂到阶段 3 目录重构的破坏性变更批次。
