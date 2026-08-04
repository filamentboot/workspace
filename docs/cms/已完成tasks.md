# 官网 CMS 已完成 tasks

> 文档定位：**只记已交付的**，逐项写清落点文件、与原计划的差异、开工后才确定的细节，供回查与排查用。
>
> 还没做的见 [未完成 tasks](未完成tasks.md)。
>
> 更新时间：2026-08-04（第 7 轮收口后；§二 那批「暂不排期的缺口」已全部交付）
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
| 第 5 轮 | 阶段 2 收口：#13–#21（区块出口、发布流转、版本回滚、草稿预览、导航、301、三层角色、SEO） | `02e87a0` |
| 第 6 轮 | 第五轮遗留 #22–#26 收口（含一个真 bug）+ 阶段 3 目录重构 #27 + 主题契约 #28 + 缓存边界 #29 | 未提交 |
| 第 7 轮 | 原「暂不排期的缺口」全清：相关内容推荐、客服脚本位与地图区块、站内搜索、可配置表单字段、资料索取 | 未提交 |

当前累计验证状态：

```
composer test        881 通过 / 3092 断言
composer pint:test   通过
composer phpstan     0 告警（根项目，扫 app + database）
主包 composer test    83 通过 / 250 断言
站点包元数据测试      9 通过
站点包 level 6       0 告警（第 6 轮清零；⚠️ 插件禁用时会多报 13 条，见文末）
Playwright uat-phase12  真机 8/8（第 6 轮首次真机跑通）
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

## 第 5 轮 · 阶段 2 收口（#13–#21）

提交 `02e87a0`（78 个文件，+6868 / -300），未 push。验证：717 通过 / 2585 断言（本轮前 561）；Pint 通过；根 PHPStan 0；站点包 level 6 仍是 10 个存量告警；主包 83；站点包元数据 9。

> ⚠️ **后台验证全部经 `Livewire::test()`，未经过真实面板**——开发库的 `plugins` 表缺
> `filamentboot-site` 行，官网资源路由从头到尾是 0 条（详见未完成 tasks 的 #22）。
> 本轮遗留 5 项，见本节末尾。

**本轮把 CMS 第一次做到可用**：#11 的 `blocks` 列与 #12 的区块契约此前**没有任何出口**——七个区块类的唯一调用方是 `SiteServiceProvider::registerBlockRegistry()`，既没有前台视图也没有后台表单。同样，版本快照表 / 前台菜单表 / 重定向表三张表建好后一直零读写。

### ✅ #13 · 区块前台渲染与安全过滤

- **`src/Cms/Rendering/BlockRenderer.php`**：`render()` 逐条处理 `[{type, data}]`，未注册 key 与缺视图两种情况都**跳过并 `Log::warning`**，不抛异常。`structuredData()` 扫 `faq` 区块产出 FAQPage 节点（从 B1 移交）。
- **`src/Cms/Rendering/BlockSanitizer.php`**：保存侧对 `rich-content.content` 跑 `RichText::purify()`。
- **14 个区块视图**：`themes/{decoration,tech-product}/blocks/{hero,rich-content,media-text,feature-grid,cta,faq,contact-form}.blade.php`，两套主题各一份完整副本。
- 接入 `SiteFrontController::page()`（抽出 `pageViewData()` 供 #16 预览共用）+ 两套 `pages/show.blade.php` 在正文**之后**输出区块。

与原计划的差异与开工后才定的细节：

- **`safeUrl()` 改成独立的 `src/Support/SafeUrl.php` 静态工具**（原计划写的是 BlockRenderer 上的 protected 方法）。#17 的 `url` 型菜单项、#18 的 `to_path` 都要用同一份白名单，protected 拿不到。放行清单只有 `/` `#` `http(s)` `tel:` `mailto:`，另外拦掉**协议相对 URL（`//evil.com`）**与**含控制字符的混淆**（`java\0script:`）——这两条是写测试时补的。
- **`BlockContract` 加了 `withDefaults()` 与 `disk()` 两个方法**。渲染器每条都要调 `withDefaults()`，只存在于 `AbstractBlock` 上会让静态分析看不见，且宿主自定义区块若不继承基类会在渲染时炸。`disk()` 是视图解析图片 URL 所必需（`image` 存的只是磁盘内相对路径）。
- **`BlockRenderer` 向视图传 `index`**，视图用它拼 `aria-labelledby` 的稳定 DOM id。原本用 `uniqid()`，同一份内容每次请求产出不同 HTML，断言无从下手，也让 #29 的整页缓存无法验证。
- **`feature-grid` 的图标名先过字符集校验再交给 `svg()`**：blade-icons 按名称拼文件路径，带 `..` 或 `/` 的名字能读到图标集目录外的任意 `.svg` 并原样输出到页面。外面再套 `rescue()`，打错的图标名不该让整页 500。
- **`ContactForm` 加了 `$tracksPanelSource`**。原本组件根元素无条件 `x-effect` 从 `$store.contactPanel.source` 同步 source，内联的 `contact-form` 区块会被它把后台配好的 source 覆盖成空串；访客点一下悬浮按钮更会把内联表单的来源改成 `floating`，落地页归因彻底失效。区块传 `source` 挂载参数时该同步关闭。
- `cta` 区块留空 `button_url` 时按钮改为打开询盘面板（来源 `page-cta`，已登记进 `config` 的 `contact.sources`）；但**填了却被 SafeUrl 拦下时什么都不渲染**，不降级成询盘面板——那会让作者以为链接生效了，点出来是另一个东西。
- `faq` 用原生 `<details>/<summary>` 而非 Alpine 手写折叠：键盘操作、读屏语义、Ctrl+F 命中折叠内容全部免费，且不依赖 JS（利好 #29）。
- `SitePage` 的 `@property array<string, mixed>|null $blocks` 改成 `array<int, mixed>|null`——Builder 存的是列表，原标注是错的。

### ✅ #14 · 页面编辑与发布流转

- `SitePageResource`：「内容」Tab 富文本之后加 `Builder::make('blocks')`（从 `BlockRegistry` 动态装配，宿主注册自定义区块后后台自动出现），加 `template` Select（读 config 新增的 `page_templates`）。
- **状态机写在 `PageStatus` 上**（`allowedTransitions()` / `canTransitionTo()`），脱离 Filament 可单测；`tests/Unit/Cms/PageStatusTest.php` 跑 5×5 全矩阵。
- `EditSitePage::getHeaderActions()`：`预览`（#16）、`提交审核`、`发布`、`定时发布`、`退回草稿`、`归档`。`visible()` 查 `canTransitionTo()`，发布类额外 `authorize('publish')`。
- `ListSitePages::getTabs()` 按 `PageStatus::cases()` 生成，各带计数 badge。
- 保存侧 `mutateFormDataBeforeCreate/Save` 跑 `BlockSanitizer`。

开工后才定的细节：

- **纠正了原计划的依赖倒挂**：`publish_site_page` 原定在 #19 建、而 #19 又阻塞于 #14。改为**权限点由第一个需要它的任务创建**。同时 `SitePagePolicy` 必须补 `publish()` 方法——BasePolicy 没有它，Gate 对任何非超管一律拒绝，发布按钮会永远点不动。
- **`Filament\Forms\Components\Builder` 必须 `as BuilderField` 导入**：`SitePageResource` 里已有 `Illuminate\Database\Eloquent\Builder`，同名会 fatal。
- **`publish` Action 会把未来的 `published_at` 拉回当下**。定时排期后又点立即发布时，`published_at` 若留在未来，`scopePublished()` 判为未到期，前台仍然看不到——正是「点了发布前台看不到」那类故障的来源。
- 状态流转走 `$record->update()` 而不是改属性再 save，这样 #15 的 Observer 能吃到。
- Action 的 `->action(fn (): void => ...)` 写法是 PHP fatal（箭头函数隐式 return，与 `: void` 冲突），必须用普通闭包。

### ✅ #15 · 版本快照与回滚

- **`src/Observers/SitePageObserver.php`**，在 `SiteServiceProvider::boot()` 注册（**不放在插件启用分支里**：版本历史与前台无关，插件禁用时后台仍在用）。
- `SitePageResource/RelationManagers/RevisionsRelationManager.php`：时间 / 操作人 / 变更字段摘要三列，`查看`（字段级新旧对比 Modal）+ `回滚`（`authorize('rollback')`）。
- config 新增 `revisions_keep`（默认 50），超出即裁剪。权限点 `rollback_site_page`。

与原计划的差异：

- **改用 `created` 写基线 + `updated` 按 `wasChanged()` 写增量，而不是 `saved` + `wasChanged()`**。Laravel 的 `performInsert()` 不调 `syncChanges()`，新建记录在 `saved` 里 `wasChanged()` 恒为 false——按原计划写，首版永远没有快照，回滚回不到最初那一版。
- `TRACKED` 与 `RESTORABLE` 两个常量分开：快照**记录** `status` / `published_at`（否则历史里看不出状态怎么变的），但回滚**不恢复**它们——回滚一篇已归档页的旧版本不该把它偷偷重新发布。`SitePageRevisionTest` 有一条结构断言固化这个区别，防日后误把 status 加进 RESTORABLE。
- payload 里 enum 存标量值、时间存字符串：payload 是 JSON，存实例会让两次读出来类型不一致。
- `blocks` 的对比只列区块 type 序列（`hero → faq → cta`），不做全文 diff——那是过度设计，而「加了哪几个区块、顺序变没变」才是回滚决策要的信息。
- 顺带改了 `SitePageStatusTest` 两条 #11 时期的用例：它们断言的是快照表的硬编码条数，观察器上线后自然多一条基线。改成断言关联包含目标记录 / 以删除前实际条数为基准。

### ✅ #16 · 草稿预览授权

- 路由 `/preview/{page}`（`->where('page', '[0-9]+')`），注册在 `/{slug}` 之前；`preview` 早已在 `reserved_slugs` 里。
- **双通道**：`URL::hasValidSignature()` **或** 已登录管理员且 `can('view', $page)`。只挂 `signed` 中间件会把已登录管理员挡在门外，所以签名在控制器里手工校验。
- 响应带 `X-Robots-Tag: noindex, nofollow`；`EditSitePage` 的 `预览` Action 用 15 分钟 `temporarySignedRoute` + 新标签打开。
- 不走 `scopePublished()`（这是它存在的理由），但保留软删除全局作用域——隐式绑定让已删页面直接 404。

开工后才定的细节：

- **`seo-meta.blade.php` 加了 `$seoData['canonical'] === false` 开关**，预览页同时跳过 `<link rel="canonical">` 与 `og:url`。两者都是对外声明「这页的正式地址」，与 noindex 矛盾，签名 URL 更不该被当作规范地址。
- 预览与正式渲染共用 `pageViewData()`：否则「预览看到的」和「发布后看到的」会不是一回事。
- 页面不存在返回 404 而不是 403（隐式绑定先于授权执行）——「链接错了」与「权限不够」是两件事，混成一个状态码编辑分不清。

### ✅ #17 · 菜单管理与前台接入

- `SiteMenuResource`（菜单本体，列表页主要动作是「管理菜单项」）+ `SiteMenuItemResource`（**不进导航**）+ `SiteMenuItemResource/Pages/SiteMenuItemTree`（filament-tree 树页）。
- `src/Cms/Services/MenuResolver.php`：`resolve(string $key): ?array`，四种 type 解析，`rememberForever` 缓存 + 模型事件失效。
- 四个 blade 接入：`themes/{decoration,tech-product}/components/{nav,footer}.blade.php`，**硬编码兜底数组原样留在各主题的 blade 里**（抽到 PHP 会把两个主题的导航结构焊死）。
- 权限点 `manage_site_menu`，config 新增 `menu.allowed_routes` 白名单。

与原计划的差异与开工后才定的细节：

- **树页做成「一个树页 + `?menu={key}` 查询串」，不是 `SiteMenuResource` 的 `/{record}/items` 记录页**。`TreePage` 继承的是 Filament 普通 Page，没有记录绑定；硬塞 `InteractsWithRecord` 之后 `getModel()` / `getFormSchema()` / 三个 `configure*Action()` 全得改指向另一个模型和另一份表单。查询串方案只需覆写库里现成的 `getTreeQuery()` 钩子。
- **`MenuResolver` 返回平铺列表，树页 `maxDepth = 1`**（原计划是嵌套两层）。两套主题的导航与页脚都没有二级下拉的版式，返回嵌套结构等于允许后台配出前台静默丢弃的层级。「后台配得出来的，前台一定显示得出来」这条比数据模型的表达力更重要；二级导航挪到 #28 主题契约，届时连版式一起放开。
- **表单里 `target` 拆成 `target_page` / `target_route` / `target_url` / `target_anchor` 四个字段**，存取时由 `SiteMenuItemResource::collapseTarget()` / `expandTarget()` 互转。同一个 schema 里四个控件都叫 `target` 时，状态绑定行为依赖 Filament 内部实现，升级即可能静默失效——那会表现为「填了链接存进去是空的」。
- **解析不出地址的菜单项整条不渲染**：页面被删 / 未发布（走 `published()`，草稿不泄露）、路由不在白名单、外链被 SafeUrl 拦下。渲染一个无处可去的链接比少一项更糟。
- 白名单是必需的而非可选：`route()` 对未知名称会抛异常，而导航在每个页面都渲染——一个填错的路由名会让全站白屏。
- `SiteMenuPolicy` / `SiteMenuItemPolicy` 共用 `manage_site_menu`，且**不继承 BasePolicy**：菜单只有「能不能管」一档，拆成五个权限点只会给角色配置添麻烦，还会造出「能建菜单但改不了菜单项」的死角。
- `SiteMenu` 改 `key` 时连旧键缓存一起清：不清会留一条永不过期的孤儿缓存，日后又建同名菜单会读到旧结构。

### ✅ #18 · 301 重定向

- **`src/Http/Middleware/SiteRedirectMiddleware.php`**，由 `SiteServiceProvider` 在插件启用时 `pushMiddleware()` 注册（全局中间件是唯一可行方案：旧 URL 已匹配不到任何路由，路由中间件跑不到；`Route::fallback()` 会顶掉宿主自己的 404 处理；接管 404 异常渲染要求下游手工改 `bootstrap/app.php`）。
- `SiteRedirectResource`（CRUD + `hits` 只读可排序列）+ `SiteRedirectPolicy`（`manage_site_redirect`）。
- `EditSitePage::afterSave()` 在 slug 变更时**自动建 301 + 通知里给撤销按钮**（原计划写的是弹确认框；自动创建默认永不丢旧 URL，少一次点击也少一次误关）。

开工后才定的细节：

- **`targetUrl()` 的判据必须是「有没有 scheme」而不是「有没有 `://`」**。这是写测试时抓到的真 bug：`javascript:alert(1)` 不含 `://`，按 `://` 判会被补成 `/javascript:alert(1)`，于是变成一个"站内路径"直接通过 SafeUrl——伪协议就这样绕了过去。
- `to_path` 被白名单拦下时**当作没配、请求继续走正常路由**：跳到一个不安全地址比 404 严重得多。
- 只处理 GET/HEAD：POST 跳转会丢请求体。
- `isSitePath()` 早退按三种挂载模式分别判断；root 模式额外排除 `reserved_slugs`——后台每个 Livewire 轮询都会打到 `/livewire/update`，让它们也过一次重定向查询纯属浪费。测试用「hits 是否递增」而不是状态码来验证早退（范围外的路径会被官网自己的 `/{slug}` 兜底路由接走并 404，那个 404 不能证明中间件放行了）。
- slug 连改两次时用 `updateOrCreate` 让第一条旧地址**直指最终地址**，不留 a→b、b→c 两跳；改回原 slug 时删掉反向链，避免新旧地址互指成死循环。

### ✅ #19 · 三层角色

- `SitePagePolicy` 补 `publish()` / `rollback()`（实际在 #14 / #15 就已补上——权限点与 Policy 方法都由第一个需要它的任务创建）。
- **`database/seeders/SiteRoleSeeder.php`**：内容编辑 / 内容发布 / 站点管理三档，注册进 `composer.json` 的 `post_install.seeders`，README 补了权限对照表与安装步骤。

开工后才定的细节：

- **用 `syncPermissions` 而非 `givePermissionTo`**：角色定义以代码为准，否则升级后各站权限各不相同没法支持。有一条测试固化这个语义（重跑会刷掉手工加的权限），防日后有人「顺手」改回去。
- **权限点缺失时过滤掉而不是报错**：下游可能只装了部分功能，给 `syncPermissions` 传不存在的权限名会抛 `PermissionDoesNotExist` 中断整个安装流程。
- **媒体没有独立权限点**：图片是通过各内容资源的 FileUpload 字段上传的，有 create/update 内容的权限就能传图。另立一个只会造出「能编辑但传不了图」这种没人想要的组合。原计划表格里的「+ 媒体」因此落空——不是漏做，是确认不需要。

### ✅ #20 · SEO 收口

- **修掉 `seo_og_image` 被完全忽略的缺陷**：`buildSeo()` 原先只在 `method_exists($record, 'ogImageUrl')` 时取封面，而 `SitePage` 不是 media-library 模型没有该方法，于是后台「SEO」Tab 里填的「社交分享图 URL」**从来没进过 `og:image`**。回退链改为 `seo_og_image` → 封面 → 全局默认。
- canonical 三项复核全部补了断言：归档页自指、`category` 参数保留（含一条「`category` 不在剥离清单里」的结构约束）、预览页不出 canonical。
- ⚠️ `seo_og_image` 列**只在 `site_pages` 上**（D-10-16 起页面无封面图，靠这一列承载分享图）；案例 / 方案 / 产品走 `HasCoverImage::ogImageUrl()`。写测试时误以为 `site_cases` 也有这列，撞了 `Unknown column`。

### ✅ #21 · 测试与验收

新增 8 个测试文件、扩了 3 个：

| 文件 | 覆盖 |
|------|------|
| `tests/Unit/Cms/SafeUrlTest.php` | scheme 白名单（放行 8 例 / 拦下 12 例 + 控制字符混淆） |
| `tests/Unit/Cms/BlockRendererTest.php` | 未知 key / 缺视图降级、脏 payload、FAQPage、保存侧净化、双主题 14 视图齐备 |
| `tests/Unit/Cms/PageStatusTest.php` | 状态机 5×5 全矩阵 + 无自环 + 无死角 |
| `tests/Feature/SitePageResourcePageTest.php` | Builder 存取、状态流转 Action 显隐与授权、Tab 计数、版本历史、slug 改名建 301 |
| `tests/Feature/SitePageRevisionTest.php` | 基线快照、增量、裁剪、回滚不改 status、回滚产生新版本 |
| `tests/Feature/SitePagePreviewTest.php` | 双通道授权、过期 / 篡改签名、noindex、无 canonical、四种未发布态可预览、软删除不可预览 |
| `tests/Feature/SiteMenuTest.php` | 四种 type 解析、不可用项不渲染、null 回退、缓存失效、双主题前台同步 |
| `tests/Feature/SiteMenuResourcePageTest.php` | 菜单列表、树页按菜单过滤、target 字段互转 |
| `tests/Feature/SiteRedirectTest.php` | 301/302、hits 递增、路径归一、三种挂载模式的早退、伪协议不跳 |
| `tests/Feature/SiteRoleSeederTest.php` | 三层权限递增、内容编辑不能发布、幂等、权限缺失不报错 |
| 扩 `SiteContentRenderTest.php` | 区块双主题渲染、区块富文本剥离脚本、未知区块优雅降级（各 `->with('themes')`） |
| 扩 `SiteSeoMetaTest.php` | `seo_og_image` 优先级、归档页自指 canonical、`category` 参数保留 |
| 扩 `SitePageStatusTest.php` | 两条 #11 期用例适配观察器 |

E2E：`tests/e2e/uat-phase12.spec.cjs`（建页 → 拖区块 → 提交审核 → 发布 → 前台可见；双主题区块渲染；草稿预览 noindex + 未登录 403；改 slug 建 301；菜单同步与删空回退）。不进 CI，由本人手跑。

**无新建表迁移**：四张表都在 #11 建好了，所以 README 的「16 张内容表」计数不变，`SitePackageMetadataTest` 仍是 9 通过。

### ⚠️ 本轮遗留（#22–#26，**已于第 6 轮全部收口**）

收工复盘查出 5 项。保留这张表是为了别让上面那些小节读起来像当时就全绿了——**#17 的「建/改菜单项」在第 5 轮结束时其实是坏的**，第 6 轮才修好。回查 #17 的实现请连着第 6 轮的 #23 一起看。

| # | 遗留 | 严重度 |
|---|---|---|
| #22 | 开发库 `plugins` 表缺 `filamentboot-site` 行 → 官网资源路由 0 条、前台 404。本轮所有后台验证因此只经 `Livewire::test()` | 高（阻塞一切手验） |
| #23 | `SiteMenuItemTree` 的 create／edit action 收不到表单数据（三种标准测试手法全失败），#17 的建/改菜单项**可能真的不能用**；已提交的测试只覆盖了 `collapseTarget()`／`expandTarget()` 纯函数与树的读取过滤，**没有一条真正建出过菜单项** | 高 |
| #24 | `tests/e2e/uat-phase12.spec.cjs` 从未真机跑过，选择器按惯例猜；且头注释指向未提交的 `playwright.config.cjs`，clone 下来跑不了 | 中 |
| #25 | `RevisionsRelationManager` 对比表的 `HtmlString` 渲染无断言（`Text` 接受 `Htmlable`、`e()` 不转义，理论可行但没看过实际输出） | 低 |
| #26 | 站点包 10 个 PHPStan 存量告警（非本轮引入，可选） | 低 |

> 第 6 轮逐条收口结果：#22 真因是孤儿行 + 24h 缓存；**#23 确认是真 bug 并已修**；#24 选择器全部重写；#25 补了断言；#26 直接清到 0。详见下一节。

---

## 第 6 轮 · 遗留收口 + 阶段 3 + 阶段 4（#22–#29）

未提交。验证：**766 通过 / 2795 断言**（本轮前 717 / 2585）；Pint 通过；根 PHPStan 0；
**站点包 PHPStan 0**（本轮前记载为 10）；主包 83；站点包元数据 9；
**Playwright `uat-phase12` 真机 8/8 全绿**——这是第一次有后台交互经过真实浏览器。

至此**阶段 1–4 全部交付**（#30 用户明确不做）。

**本轮的真正价值是把「验证过」这件事落实了。** 第 5 轮所有后台验证只经 `Livewire::test()`，
而 #22 一恢复环境就露出两个 `Livewire::test()` 永远看不到的问题：菜单项弹窗因 JS 错误
根本打不开，两个新资源在侧边栏里隐身。

### ✅ #22 · 恢复插件启用状态

`plugins` 表只有一行 `corporate-site-suite`（改名取消前的孤儿行），没有 `filamentboot-site`，
所以 `SitePlugin` 没进面板、前台路由整份不加载。被 `pluginIsEnabled()` 外面那个
`Cache::remember(..., 24h)` 掩盖了很久。

- `php artisan plugin:scan` 一次建出 **9** 行（除官网插件外还有主包与 5 个编辑器/存储插件，全部 `is_enabled=0`）
- 走 `PluginManager::enable()` 而不是直接 update：它会连带 `Cache::forget('plugins.enabled_list')` 与 `{slug}:is_enabled`
- 孤儿行 `corporate-site-suite` 已按用户决定删除
- 顺带补种 `SitePermissionSeeder` / `SiteMenuSeeder` / `SiteRoleSeeder`——开发库里 `manage_site_menu` 等权限点根本不存在（超管靠 `Gate::before` 绕过，所以一直没暴露）

⚠️ **`plugin:scan` 写进 `plugins.post_install_data` 的是 `vendor/composer/installed.json` 里的旧元数据**
（`post_install.seeders` 只有已删除的 `SiteSeeder`，描述还写着「五类内容、双语」）。
path 仓库的 installed.json 不随包内 composer.json 自动刷新。这是 #30 的隐患。

### ✅ #23 · 菜单项树表单绑定（**第 5 轮的真 bug，已修**）

真机点「New 菜单项」时浏览器控制台报：

```
Livewire Entangle Error: Livewire property ['type'] cannot be found on component: [...SiteMenuItemTree]
```

**根因**：基类 `SolutionForest\FilamentTree\Resources\Pages\TreePage::getFormSchema()` 的实现是
`static::getResource()::form(Schema::make($this))->getComponents()`——先把组件绑到一个临时的、
statePath 为空的 Schema 上，Filament 5 在那次解析里就把每个字段的**绝对状态路径缓存成了裸字段名**。
之后 `CreateAction` 用 `mountedActions.0.data` 重新收容这批组件，缓存值不会重算，
前端 `@entangle` 去找页面上并不存在的 Livewire 属性，弹窗连显示都完不成。

**修法**（落点两处，共约 20 行）：

- `SiteMenuItemResource::formComponents()`：把组件列表从 `form()` 里拆出来，返回**没被容器绑过**的新实例；`form()` 变成 `$schema->components(static::formComponents())`
- `SiteMenuItemTree::getFormSchema()`：覆写基类，直接返回 `SiteMenuItemResource::formComponents()`，让动作自己的 Schema 成为这批组件的第一个容器

真机复验：新建落库（`menu_id/parent_id/type/target` 全对）、编辑弹窗回填 `#probe`、改完保存生效、前台导航同步出现。

测试（`tests/Feature/SiteMenuResourcePageTest.php` +4 条）：真正建出菜单项并断言 `menu_id`/`target`；
`?menu=` 归属；编辑回填与写回；**状态路径回归护栏**。

> ⚠️ **护栏不能断言渲染的 HTML**：Filament 5 的模态体是客户端惰性渲染的，`Livewire::test()`
> 拿到的 `wire:partial="action-modals"` 分区永远是空的，`assertSeeHtml` 会恒假。
> 改为取 `getSchema('mountedActionSchema0')` 断言字段的绝对状态路径。同一个坑在 #25 上又踩了一次。

> ⚠️ **主包的 `Filamentboot\Filament\Resources\Menus\Pages\MenuTree` 同样没覆写 `getFormSchema()`，
> 应当有同一个 bug**（后台「菜单规则」的建/改弹窗）。按仓库约定「无关问题提及但不处理」未修——
> 它属于主包，另一条任务链。修法照上面抄即可。

### ✅ #23 顺带 · 两个资源在侧边栏里隐身

后台侧边栏由 `AdminNavigationBuilder` 从主包 `menus` 表构建，Filament 基于 Resource 静态属性
自动生成导航的机制被 Panel 的 `->navigation()` 回调整体旁路。#17 的「导航菜单」与 #18 的「重定向」
交付时没在 `SiteMenuSeeder` 里登记，**只能靠直链访问**。

- `SiteMenuSeeder::menus()` 补两行（权限点是自定义的 `manage_site_menu` / `manage_site_redirect`，不走 BasePolicy 推导）
- `tests/Feature/SitePluginIntegrationTest.php` 加**结构性护栏**：遍历 `SitePlugin` 注册的资源，凡 `shouldRegisterNavigation()` 为真就必须在 `menus` 表有 `{routeBaseName}.index` 行。以后新增资源忘了改种子就会红。

### ✅ #24 · E2E 真机跑通

`tests/e2e/uat-phase12.spec.cjs` 从未真机跑过，选择器基本全错。校准后 8/8 绿（含 #25 那条）。

- 新增**已跟踪**的 `playwright.config.uat.cjs`（`testMatch: 'uat-*.spec.cjs'` + `globalSetup` + `workers: 1`）。此前 spec 头注释指向未跟踪的 `playwright.config.cjs`；仓库里已跟踪的是 `playwright.config.site.cjs`，但它只匹配 `site-*` 且没有登录 setup
- **面板语言是 en**：Filament 自带文案是 `Create` / `Save changes` / `Confirm` / `Add to :label` / `New :modelLabel`，只有本包自己写的动作标签是中文。原 spec 通篇按中文写
- **表单控件 id 是 `form.<path>`**，`wire:model` 才是 `data.<path>`。原 spec 写 `input[id="data.slug"]`，一个都选不中
- `native(false)` 的 Select 不是 `<select>`：触发器 `.fi-select-input-btn`，选项 `li[role=option][data-value=…]`
- 模态里的提交必须限定在 `.fi-modal-window` 内——树页工具栏也有一个 `Save`（保存拖拽顺序）
- Tabs 的 ARIA role 是 `tab` 不是 `button`
- 重定向列表那条断言原来是**假绿**：`from_path` 存的是 `normalizePath()` 去掉两端斜杠的值，列上用 `->prefix('/')` 补回来；模糊匹配 `/${slug}` 会命中 `to_path` 那格（`/旧slug-moved`），整行缺失也照样过。改成带斜杠的精确匹配

### ✅ #25 · 版本对比 Modal 渲染断言

`tests/Feature/SitePageResourcePageTest.php` +2 条：`mountTableAction('view', $revision)` 后取
`getSchema('mountedActionSchema0')`，断言渲染出的 `Text` 组件含 `<table` 而非 `&lt;table`、
含三列表头与新旧两侧的值、不含未变字段；另一条覆盖「与当前一致」时给一句话而非空表格。
E2E 里另有一条在真浏览器里断言表头是真的 `<th>`。

> ⚠️ E2E 那条要点**最后一行**的「查看」：列表按 id 倒序，第一行是最新快照，它与当前内容一致，
> 点开只会得到「该版本与当前内容一致」，看不到表格。

### ✅ #26 · 站点包 PHPStan 清零（23 → 0）

- `getEloquentQuery()` ×5：`Filament\Resources\Resource` 是泛型类，子类补 `@extends \Filament\Resources\Resource<SiteCase>`。**不能**改成从具体模型 `query()`——那会丢掉父类的 tenant 作用域处理
- ⚠️ **Pint 的 `phpdoc_types` 会把 `@extends Resource<X>` 里的 `Resource` 小写成 `resource`**（当成 PHP 伪类型）。虽然 PHP 类名大小写不敏感、PHPStan 仍能解析，但形式很脆——写**全限定名** `\Filament\Resources\Resource<X>` 两边就不打架了
- `newsIndex` 的 `published()`：新增 `NewsCategory::publishedArticles()` 关系，`withCount('publishedArticles as articles_count')`。给 `articles` 套闭包时参数只能被推成 `Builder<Model>`，而把判据在闭包里重写一遍等于让「已发布」有两份定义
- `newsArchiveMonths()` 返回类型：键写 `array-key`（`groupBy()` 的键在类型系统里一律 `int|string`），值写 `int<0, max>`（Collection 的 TValue 不协变）
- `HasFactory` 泛型 ×2：新增 `ContactMessageFactory` / `SiteTagFactory` + 模型的 `newFactory()`。比删 trait 好——删了是拿掉下游的公开 API
- 13 条 `view-string` 随 #22 一起消失（见上一节）

### ✅ #27 · 目录重构 + 删 is_published

48 个文件 `git mv`（history 保住），171 个引用点跟随。**只移动不改名。**

```
src/Cms/          Models Enums Filament/Resources Policies Observers Routing Themes
                  （Blocks / Rendering / Services 原本就在位）
src/Modules/Corporate/  Cases/{Models,Filament,Policies,Enums}  Products/…  Solutions/…  Home/
src/Http/         Controllers Livewire Middleware        ← 留顶层
src/{Models,Policies,Filament,Services,…}/               ← 询盘、标签、设置页等跨模块件留顶层
```

- `Cms/Routing/SiteRedirectMiddleware`（301 是路由层能力）、`Cms/Themes/ThemeAsset`（从 `Support/` 搬来，给 #28 腾位置）
- `Modules/Corporate/Home/HomeSectionProvider`：首页聚合从 `SiteFrontController::home()` 抽出。宿主 `bind()` 掉这个类就能整体换掉首页内容源，控制器与路由都不用动（有测试锁这个替换点真的生效）
- 删 `site_pages.is_published`（迁移 `2026_08_04_100001_…`，`down()` 会按 status 重新派生）+ 模型的 saving 镜像钩子、`casts`、`@property`。**`site_products.is_published` 是产品自己的发布列，没动**；`SearchPushObserver::PUBLISH_COLUMNS` 是跨模型并集，也保留该项

⚠️ **PHPStan 抓出 36 处断链，全是一类问题**：`SiteTag` 与三个内容模型原本同处 `Models` 命名空间，
互相引用不带 `use`，拆开后静默失效。补 6 条导入解决。**这类断链 `php -l` 查不出来，
只有静态分析或运行到那行才会暴露**——搬动同命名空间的一组类之后必须跑一遍 PHPStan。

⚠️ **Policy 约定发现**逐个核对过：`Cms\Models\SitePage` → `Cms\Policies\SitePagePolicy`、
`Modules\Corporate\Cases\Models\SiteCase` → `…\Cases\Policies\SiteCasePolicy`，10 个模型全部解析到位
（Laravel 逐级回退 `\Policies\` 前缀，各模块保持 `Models/` 与 `Policies/` 同级即可）。
权限点名取自 `BasePolicy::resourceName()` 的**短类名**，不受移动影响。

### ✅ #28 · 主题契约与切换预检查

- **`Cms/Themes/ThemeContract`** + 每主题 `resources/views/themes/{theme}/theme.php` 清单（`label` / `templates` / `blocks` / `features`）
- **`ThemeManifest`**：清单缺失时**扫目录推断**（`blocks/*.blade.php`、`pages/templates/*.blade.php`），但 `features` 一律按不支持——文件系统看不出一个 nav 有没有下拉版式。宿主发布的覆盖优先，与视图解析同源
- **`ThemeSwitchCheck`**：算出切到目标主题后哪些**已发布**页面会掉版式/区块。与 `BlockRenderer` 的运行时兜底是两回事，那个是事后降级（缺视图跳过 + warning），页面上悄悄少一块没人收到通知
- **设置页门禁**：`active_theme` 改 `live()`，下面挂受影响页面清单与「仍要切换」确认开关；未勾就在 `mutateFormDataBeforeSave` 里 `throw new Halt` ——**整份设置都不保存**，而不是只忽略主题这一项
  > 没用 `requiresConfirmation()` 的模态：`SettingsPage::getSaveFormAction()` 走的是原生表单 `submit()`，绕过动作模态机制。而且把受影响页面**在点保存之前**就列出来，比弹窗更有用
- **放开二级导航**：`SiteMenuItemTree::$maxDepth` 1 → 2；`MenuResolver` 改返回嵌套（每项带 `children`），缓存里存**嵌套**结构、摊平放读取侧做（形状取决于主题，而嵌套本身与主题无关）
  - 清单未声明 `nested_menu` 时**摊平而不是丢弃**子项——丢弃等于后台配好的入口在前台静默消失
  - 父项解析不出地址时子项一并不渲染：把子项提上来会改变导航语义
  - 页脚改用新增的 `resolveFlat()`：页脚是一列快捷链接，不该跟着主题的 nested_menu 变形状
  - 两套主题的 `components/nav.blade.php` 各加一份下拉版式（桌面 hover+点击开合、移动端缩进平铺）。父项本身仍可点——既是栏目页又有子项的入口若只能开合，桌面端就进不去那一页
- **`landing` 落地页版式**：config 加一项，两套主题各一份 `pages/templates/landing.blade.php`。**刻意不 extends `layouts.app`**（那份带完整导航与页脚），改 extends `layouts.base` 自搭极简头部 + 法定信息页脚；仍 include `floating-contact`，因为询盘面板本体在那里
- 新增 `tests/Feature/SiteThemeContractTest.php`（15 条）：清单与实际视图文件**对账**、无清单推断、预检查列页面/忽略草稿、设置页拒绝与放行、嵌套/摊平/页脚三种解析、父项不可用

> ⚠️ 别复用 `SiteContentRenderTest.php` 里的 `dataset('themes')`：那份定义只在该文件被加载时注册，
> 单跑另一个文件时不存在，`->with('themes')` 会让 Pest 报 failed 但列不出失败用例。用行内数据集。

### ✅ #29 · 缓存边界：公开页零 session

**实测结果**：内容页从 `Cache-Control: no-store, private` + 两个 `Set-Cookie`
（`XSRF-TOKEN` / `filamentboot-session`）变成 **`Cache-Control: public, max-age=600`，零 Set-Cookie**。

开工前先撞上一个拦路点，是这条真正的难点：

> **公开页的 Alpine 原来是 Livewire 注入的 `livewire.js` 捎带进来的**，而那个 script 标签带
> `data-csrf`，渲染时调 `csrf_token()` → 起 session。也就是说「公开页零 Livewire」一旦做完，
> Alpine 随之消失，悬浮询盘面板、移动端抽屉、二级导航下拉、图集轮播会一起失效。
> 包自己的 `resources/` 下没有 js，`vite.config.js` 的 input 只有两份主题 CSS，
> `node_modules` 里也没有 `alpinejs`。

用户在三条路（npm 依赖 + Vite 入口 / vendor 静态产物 / CDN）里选了第一条。

**Alpine 的独立交付**

- 新增 `packages/filamentboot-site/resources/js/site.js`（`import Alpine` → `window.Alpine` → `Alpine.start()`）
- `ThemeAsset::viteEntries($theme)`：返回主题 CSS + 前台脚本两个入口，布局改用它；
  脚本候选路径在 config 的 `assets.script_entries`（与 CSS 同一套「真实安装 / 宿主发布 / monorepo 符号链接」三形态机制）
- `SiteServiceProvider` 增加 `resources/js` 的发布，落在 `resources/js/vendor/filamentboot-site/`
- 宿主 `vite.config.js` 的 input 加一项，`npm i -D alpinejs`（3.15.12），构建产物 45.25 kB / gzip 16.11 kB
- ⚠️ **各处的 `alpine:init` 监听器必须先注册**（`contact-panel-store`、新增的 `attribution-store` 都在 `<head>` 的内联 script 里），而 `@vite` 产出的是 `type="module"`（天然 defer），顺序有保证

**询盘改无状态端点**

- `Services\ContactSubmission`：唯一一份「收到一次询盘该做什么」——机器人识别 → IP 限流 → 校验 → 入库 → 通知。`WithRateLimiting` 是 Livewire 专用，换成 `RateLimiter`
- `Enums\ContactSubmissionResult`（created / discarded / throttled）；校验失败仍走 `ValidationException`
- `Http\Controllers\ContactSubmissionController` + 路由 `POST /contact-submissions`，**不挂 `web` 组**（没有 session 就发不出 CSRF token），另挂 `throttle:30,1` 做粗粒度兜底
- 控制器**自己拼 JSON**（`{ok, errors?}`）而不依赖异常渲染器：宿主的 `bootstrap/app.php` 可能（本仓库就）把 `ValidationException` 改成了自己的 API 信封，包内脚本不能假设那个形状
- `shared/components/contact-form.blade.php` 改纯 Alpine + `fetch`；提交成功后 `window.dispatchEvent(new Event('site-contact-submitted'))`，A3 的统计监听不变

**归因搬客户端**

- `CaptureVisitorAttribution` 中间件退役（类已删）；新增 `shared/components/attribution-store.blade.php`：Alpine store + localStorage，**只在 key 不存在时写一次**（首触语义不变），localStorage 不可用时降级为内存
- 服务端从**请求体**逐键取白名单字段（`landing_url` / `referer` / 5 个 `utm_*`），按列宽截断。⚠️ 不整段展开——`ContactMessage` 的 `$guarded` 为空，摊进 `create()` 等于给任意请求字段开批量赋值入口（有测试锁这条）
- 真机验过：带外部 referer + UTM 落地 → 跳页 → 打开面板提交，`source=nav-desktop`、`utm_source=baidu`、`landing_url` 与 `referer` 全部正确入库

**`case-filter` 改查询串**

- 筛选逻辑进 `SiteFrontController::caseIndex()`，`enumFilter()` 按 `CaseStyle` / `HouseType` 白名单过滤（不在白名单就当没传，避免 `?style=<script>` 渲染出「筛选中：…」的空结果页）
- 兼容旧的 `?houseType=`：Livewire `#[Url]` 用驼峰，改查询串后规范是 `house_type`，但已收录的旧地址不该静默丢掉筛选条件
- ⚠️ 原先筛选 pills + 卡片网格 + 分页整块在 `resources/views/livewire/case-filter.blade.php` 里，是一份**跨主题共享的视觉视图**，本来就违反「双主题完全独立」。这次拆成两套主题各一份，顺带修掉

**响应头与中间件分档**

- `Cms\Routing\SiteCacheHeaders`：只在「安全方法 + 200 + **响应没有 Cookie**」时打 `public, max-age`。第三个条件最关键——把带会话 Cookie 的响应标成公共可缓存，共享缓存会把一个访客的会话发给另一个
- 404 与 301 不缓存：一次误发布导致的 404 被 CDN 缓存住，等于把事故延长到缓存过期
- 内容路由中间件从 `web` 换成 `[SubstituteBindings, SiteCacheHeaders]`；**`/preview/{page}` 单独留在 `web` 组**，它靠 `auth('admin')` 判权

**退役的东西**（0.5.x 的公开 API 移除，下游若用过要注意）

- `Http\Livewire\ContactForm` 与 `Http\Livewire\CaseFilter` 两个组件及其视图
- `Http\Middleware\CaptureVisitorAttribution`
- `SiteServiceProvider::registerLivewireComponents()`——包内已无 Livewire 组件，命名空间不再注册。这让「公开页零 Livewire」从约定变成结构事实
- 连带改了 10 个测试文件 `beforeEach` 里的反射方法列表

**取舍：耗时校验降级**

`MIN_FILL_SECONDS = 3` 原先靠 Livewire 对 `renderedAt` 的 checksum 保护。整页缓存之后服务端渲染的
时间戳会被冻结在缓存里（缓存 10 分钟，所有人拿到的都是 10 分钟前的值），这道校验在服务端已无从锚定。
改为客户端上报「表单可交互到提交」的秒数，服务端只做宽松下限判断——**它降级为可被脚本伪造的
低成本启发式**，蜜罐与 IP 限流仍是真防线。这是「整页缓存」与「服务端可信时间戳」的固有矛盾。

**测试**

- `ContactFormTest` 整份重写（17 条）：改测无状态端点，`humanPace()` 辅助随之删除（耗时改由请求体给）
- `SiteAttributionTest` 重写（7 条）：改验归因脚本注入 + 首触语义写在里面 + **服务端不再往 session 写归因**（回归护栏）+ 中间件类已删
- 新增 `SiteCacheBoundaryTest`（14 条）：内容页无 Set-Cookie / 无 `wire:snapshot` / 带 `public max-age`（7 条路径逐一）、带筛选参数的列表页同样可缓存、404 与 301 不缓存、**草稿预览带 session 且绝不打 public**（若被标 public，草稿会经共享缓存泄露给公众）、签名预览可用、`public_max_age=0` 时不打头

---

## 第 7 轮 · 原「暂不排期的缺口」全清

未提交。`未完成tasks.md` §二 那张表里的 7 条，其中 **2 条其实已在第 6 轮交付**（落地页极简版式、二级导航——#28 做的，文档忘了挪），剩下 5 条本轮全做完。

> 本轮所有前台可见改动都遵守 §0.3 第 1 条（双主题各一份完整副本）与第 5 条（零 Livewire、零 session）。

### ✅ 相关内容推荐（案例 / 方案 / 产品详情页底部）

- 新增 `src/Cms/Services/RelatedContent.php`：两趟查询——第一趟命中**任一**亲和维度（OR），第二趟不够 `LIMIT`（3）时用最新补齐并排除已出现的
- 亲和维度由调用方传，各类不同：案例 `style` / `house_type` / `category_id`，产品 `category_id` / `brand`，方案只有标签（服务自己从记录上读 `tags`）
- 查询由**调用方用具体模型类构造并传入**（已套 `published()` 与排序）。服务不自己调 `published()`：那是局部作用域，在泛型 Builder 上静态分析解析不出来，且各模型判据不同（产品用 `is_published` 布尔列，其余用 `published_at`）
- 两套主题 6 个视图各加一段：`cases/show`（复用 `case-card`）、`products/show`（复用 `product-card`）、`solutions/show`（方案没有卡片组件，各主题按自己列表页的版式压缩一版）
- 新增 `tests/Feature/SiteRelatedContentTest.php`（17 条）

**⚠️ 资讯刻意不用这个服务。** 一开始把 `newsShow()` 也并了过来，`SiteNewsTest` 的「详情页相关阅读取同分类且排除自身」立刻红——那条测试锁的是「相关阅读不跨分类补齐」，是已落地的产品决定。已回退，并在 `newsShow()` 与服务类注释里双向写明分歧理由：**相关阅读是阅读推荐，跨分类会误导；三类详情页底部是浏览出口，断头路比不够精准更糟。**

### ✅ 在线客服脚本位 + 联系页地图嵌入

**客服脚本位**（`live_chat_enabled` + `live_chat_script` 两个设置项 + 迁移 `2026_08_04_200001` + `shared/components/live-chat.blade.php`）

- 开关与代码分开是全部理由所在：换供应商、临时无人值守时运营要能一键停掉，而不是把一大段脚本剪出去存别处
- 与 `head_scripts` 同一套信任模型（原样输出、不过 purifier、仅 `manage_site_settings` 可改、变更写操作日志）
- `SiteSettingsPage` 的高风险字段清单抽成 `SCRIPT_FIELDS` 常量：快照与审计两处都读它，分开写死迟早漏一个，漏掉的那个就是「改了前台执行的代码但日志里查不到」
- 后台字段旁明示：移动端底部已有操作条，多数客服气泡会与它重叠

**地图区块**（`map`，第 8 个内置区块）

- 作者只填**嵌入地址**，`<iframe>` 由视图自己拼。不接受整段 iframe HTML——那等于在页面里开任意标签入口，而 iframe 能加载任何东西并全屏覆盖页面
- 新增 `src/Support/MapEmbed.php`：只放行 https + host **精确**命中 `config` 白名单。用精确匹配而不是「以某域名结尾」：后者会被 `map.baidu.com.evil.com` 绕过，写 `.baidu.com` 又把整棵域名树放进来
- 只放行 https 的理由：http 的 iframe 在 https 页面上被当混合内容直接拦掉，放行等于放行一个必然不显示的地图，作者只会以为是我们的 bug
- 保存时就拦（区块 `rules()` 里一条闭包规则）+ 渲染时再过一遍（库里可能躺着白名单收紧之前存的地址）
- 文字地址不是地图的说明而是它的**降级路径**：拦截插件、企业网络策略与爬虫都会丢掉 iframe
- 顺手改掉 `analytics.blade.php` 里一句过期注释（还写着事件由 Livewire 的 `ContactForm::submit()` 转发，#29 之后是 Alpine 直接 `dispatchEvent`）

### ✅ 站内搜索（前台跨模块）

- 新增 `src/Cms/Services/SiteSearch.php` + `search()` 控制器动作 + 路由 `/search`（已列入 `reserved_slugs`）+ 两套主题各一份 `search.blade.php` + 两套主题导航（桌面图标 + 抽屉条目）
- 五类内容各查一次、各限 5 条，多取一条判「还有更多」比再发一次 `count()` 便宜
- 表单是 `method="get"`：**不能**改 POST 或加 `@csrf`，那会起 session 让整页缓存静默失效；GET 也让每个关键词各自成为可缓存、可分享的 URL
- **`noindex, follow`**：搜索页 URL 空间无限（任意关键词 × 组合），被收录会产出成千上万低价值页面稀释整站权重。canonical 一并关掉——已经 noindex，再自指是矛盾信号
- LIKE 转义符用 `!` 而不是默认的反斜杠：`ESCAPE '\'` 在 MySQL 里是未闭合字符串，写成两个反斜杠在 SQLite / Postgres 里又变成两个字符。换一个不会被任何一方二次处理的字符，三种驱动行为一致
- 摘要不做关键词高亮：高亮要输出 `<mark>` 就得让视图 `{!! !!}`，而那段文本混着作者写的富文本
- 无结果页给一个登记过的询盘出口（`search-empty`），不做成死路
- 新增 `tests/Feature/SiteSearchTest.php`（26 条）

**⚠️ 区块正文搜不到，这是存储格式决定的。** `site_pages.blocks` 是 JSON 列，Eloquent 存入时非 ASCII 字符被 `json_encode` 转成 Unicode 转义序列（实测「中文」落库后是 `u4e2d` / `u6587` 那种六字符转义写法），`LIKE '%中文%'` 永远不可能命中。页面只按 `title_zh` / `content_zh` / `seo_description` 匹配——纯区块搭的页面只能靠标题被搜到。要覆盖区块正文得加一列由观察器维护的 `search_text`（渲染后的纯文本），是独立的一次改动。

也**没有相关度排序**：排序按各类型自己的自然顺序。要按相关度排必须先有全文索引的评分（中文还需 `WITH PARSER ngram`）。

### ✅ 表单字段可配置（不同活动问不同问题）

- `ContactFormBlock` 加 `fields` Repeater（最多 6 个，类型 text / textarea / select）+ `normalizedFields()`（解析「一行一个」选项、丢弃重名与空下拉、截断上限）
- 答案落 `site_contact_messages.extra`（迁移 `2026_08_04_200002`），后台详情（`KeyValueEntry`）、CSV 导出、通知邮件三处都能看到
- 提交侧只做**边界**约束（`MAX_EXTRA_ANSWERS` / `EXTRA_LABEL_LENGTH` / `EXTRA_VALUE_LENGTH`、非标量丢弃、控制字符清除）
- 新增 `tests/Feature/SiteContactExtraFieldsTest.php`（22 条）

**⚠️ 必填只在浏览器里生效，这是有意的取舍。** 端点是无状态的（#29），收到的只是一份键值对，无从知道是哪份区块配置渲染出来的表单——除非把配置连签名一起随请求发出，而那要么引入随机数（毁掉整页缓存的确定性），要么再加一套 HMAC 校验。绕过必填的代价是收到一条答得不全的线索，不是数据被污染；而每在提交链路上多加一个环节，就多一处可能静默丢线索的地方。同 #29 对耗时校验的处理方式。

**⚠️ 开工后才发现：MySQL 的 JSON 对象不保留键顺序。** 最初存 `{问题: 答案}` 映射，测试直接红——两个答案读出来顺序被重排了（MySQL 规范化 JSON 对象）。而答案顺序就是表单上问题的先后，属于有意义的信息。改存**有序列表 `[{label, value}]`**（JSON 数组的顺序 MySQL 会保留），展示层再拼回映射。`ContactMessage::$extra` 的 `@property` 刻意写宽成 `array<int, mixed>`（同 `SitePage::$blocks`）：精确形状只在写入侧保证，读取侧拿到的是 JSON 列的实际内容，seeder / tinker / 历史行都不受那条写入路径约束。

### ✅ 资料索取 / gated content（手册换联系方式）

第 9 个内置区块 `gated-download` + `src/Cms/Services/GatedAssetRegistry.php` + `src/Http/Controllers/GatedDownloadController.php` + 路由 `/downloads/{asset}`（已列入 `reserved_slugs`）+ 两套主题各一份区块视图。

**门由四条共同关住，缺一条整个功能就变成「多点了一次鼠标的公开下载」：**

1. **前台 HTML 里没有文件路径**，只有一个不透明 key（路径的 sha1 前 16 位）
2. 下载链接必须带**有效签名**且有时限（默认 30 分钟，`config` 的 `gated.link_ttl`）
3. key 必须在登记表里，而登记表只收**已发布**页面声明的资料——草稿页的资料下不到
4. 判为机器人时对外回成功但**不放资料**，否则蜜罐就成了「不留真联系方式也能拿手册」的后门

- 文件存 `config` 的 `gated.disk`（默认 `local` = `storage/app`，**Web 根之外**）。⚠️ 宿主改成 `public` 这道门就形同虚设，且不会有任何报错，表现只是「留资率莫名很低」
- key 取 sha1 前缀而不是随机 token：**确定性**的，同一文件每次渲染同一个 key，页面 HTML 才能整页缓存（#29）
- 路由参数是 key 而不是路径：接受路径就等于把任意文件读取挂到公开端点上
- 上传白名单不含 html / svg：它们会被浏览器当页面渲染，等于在自己域名下托管别人的 HTML
- 「索取了哪份资料」复用 `extra` 记录（`索取资料 => 资料名`），不新加列——`extra` 已经会出现在后台详情、导出与通知邮件三处
- 登记表缓存照 `MenuResolver` 的做法（`rememberForever` + 模型事件失效）：`SitePage::booted()` 在 `saved` / `deleted` 时整表 `forget()`。不精确清是因为精确清要判断「这次改动有没有动到 gated-download 区块」，判断写错的后果是「资料下不了」或更糟的「下线了还能下」
- 新增 `tests/Feature/SiteGatedDownloadTest.php`（24 条）

**真机验过整条链路**（`php artisan serve`，非 fake 磁盘）：页面 HTML 里路径出现 0 次、key 出现 1 次；提交后拿到带 `expires` + `signature` 的链接；下载得 200 + `Cache-Control: max-age=0, no-store, private` + 真实文件内容；改签名与不带签名都是 403；线索里 `extra` 记着 `[{"label":"索取资料","value":"冒烟手册"}]`。冒烟数据已清。

### 本轮的验证数字

```
composer test        881 通过 / 3092 断言（本轮开工时 766 / 2795）
composer pint:test   通过
composer phpstan     0（根项目）
站点包 level 6       0
主包 composer test    83 通过
站点包元数据测试      9 通过（本轮只加列不加表，README 的「16 张内容表」不变）
```

真机 `curl` 复核：`/search?q=智能` 得 200 + `public, max-age=600` + `noindex, follow` + 零 Set-Cookie，五类内容共 23 条命中；案例 / 方案 / 产品三个详情页的相关推荐都渲染出来了。

---

## 已知存量问题

### 站点包 PHPStan level 6：已清零（第 6 轮）

`vendor/bin/phpstan analyse --level=6 packages/filamentboot-site/src` 现在是 **0 告警**，此前记载的「10 个存量告警」已全部修掉（第 6 轮 #26）。修法见下一节。

> 顺带查清了一件长期误会的事：那条命令在**插件被禁用**时会多报 13 条 `view()` 的
> `argument.type`。原因是 `filamentboot-site::` 视图命名空间由 `SiteServiceProvider::boot()`
> 在启用分支里注册，插件禁用时 larastan 解析不到任何包内视图。所以「23 条」与「10 条」
> 是同一份代码在两种插件状态下的读数，不是谁数错了。**跑静态分析前先确认插件是启用的。**

### ⚠️ site-contact-cta.spec.cjs 的 3 条 mobile 用例是红的

存量矛盾，非第 6 轮引入。那 3 条断言悬浮气泡在移动端可见（`boundingBox()` 不为 null），
而气泡本体带 `hidden sm:inline-flex`——按设计移动端由底部三段式操作条取代气泡、两者互斥，
`SiteContentRenderTest` 里还有一条 `移动端隐藏悬浮气泡` 断言的正是相反的事。

要么把那 3 条限定成 desktop project，要么改设计让移动端也出气泡。这是个产品选择，没动。
其余 18 条通过。

### 素材空缺

- **18 个产品封面全部空缺**。产品图要与型号对得上，CC0 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。等品牌方渠道商素材包。
- **`news/is-voice-control-useful` 空缺**。CC0 池里「智能音箱」清一色是 Amazon Echo Dot 的棚拍图，放到自有品牌站上等于替竞品打广告。留空反而给前台占位降级留了个活样本。

### 已取消的事项

**包重命名 `filamentboot-site` → `filamentboot-cms` 已于 2026-08-03 取消**：收益小、风险实在。`PluginManager::syncFromInstalled()` 按 slug upsert，改名后会新建行、留下孤儿旧行、`is_enabled` 不继承（阶段 1 期间已在本地 DB 实测确认）。连带取消 `plugins` 表 slug 数据迁移。

沿用 `filamentboot-site` 包名与 `Filamentboot\FilamentbootSite\` 命名空间，新增代码不做任何改名预留。

> 副作用：`is_published` 旧列原定「随包重命名一起删」，锚点没了，改挂到阶段 3 目录重构的破坏性变更批次——**已于第 6 轮 #27 删除**（迁移 `2026_08_04_100001_drop_is_published_from_site_pages_table`）。`site_products.is_published` 是产品自己的发布列，仍在用。
