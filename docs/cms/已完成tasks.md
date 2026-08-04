# 官网 CMS 已完成 tasks

> 文档定位：**只记已交付的**，逐项写清落点文件、与原计划的差异、开工后才确定的细节，供回查与排查用。
>
> 还没做的见 [未完成 tasks](未完成tasks.md)。
>
> 更新时间：2026-08-04（第五轮阶段 2 收口后）
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
| 第 5 轮 | 阶段 2 收口：#13–#21（区块出口、发布流转、版本回滚、草稿预览、导航、301、三层角色、SEO） | 未提交 |

当前累计验证状态：

```
composer test        717 通过 / 2585 断言
composer pint:test   通过
composer phpstan     0 告警（根项目，扫 app + database）
主包 composer test    83 通过 / 250 断言
站点包元数据测试      9 通过
站点包 level 6       10 个存量告警（见文末「已知存量问题」，第 5 轮未新增）
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

未提交（用户未要求 commit）。验证：717 通过 / 2585 断言（本轮前 561）；Pint 通过；根 PHPStan 0；站点包 level 6 仍是 10 个存量告警；主包 83；站点包元数据 9。

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

---

## 已知存量问题

### 站点包 PHPStan level 6 的 10 个告警

**不是老文档写的 6 个**——多出的 4 个来自第 3 轮的资讯模块。根 `phpstan.neon` 只扫 `app` 与 `database`，所以 `composer phpstan` 一直是绿的。

| 文件 | 告警 |
|------|------|
| `SiteCaseResource` / `SitePageResource` / `SiteProductResource` / `SiteSolutionResource` / `NewsArticleResource` | `getEloquentQuery()` 返回类型 ×5 |
| `ContactMessage` / `SiteTag` | `HasFactory` 泛型未声明 ×2 |
| `SiteFrontController` | `newsIndex` 的 `published()`、`newsArchiveMonths()` 返回类型、`?->name_zh` 多余的 nullsafe ×3 |

按仓库约定「无关问题提及但不处理」，未修。**新增代码不应让这个数字变大**——第 5 轮新增约 20 个文件，这个数字仍是 10。

> 第 5 轮踩到的两次「新增告警」都是真类型问题，就地修了而不是放进这份清单：
> `BlockContract` 缺 `withDefaults()` 声明（渲染器依赖一个契约里没有的方法）、
> `SitePage::$blocks` 的 `@property` 写成了关联数组而实际是列表。

### 素材空缺

- **18 个产品封面全部空缺**。产品图要与型号对得上，CC0 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。等品牌方渠道商素材包。
- **`news/is-voice-control-useful` 空缺**。CC0 池里「智能音箱」清一色是 Amazon Echo Dot 的棚拍图，放到自有品牌站上等于替竞品打广告。留空反而给前台占位降级留了个活样本。

### 已取消的事项

**包重命名 `filamentboot-site` → `filamentboot-cms` 已于 2026-08-03 取消**：收益小、风险实在。`PluginManager::syncFromInstalled()` 按 slug upsert，改名后会新建行、留下孤儿旧行、`is_enabled` 不继承（阶段 1 期间已在本地 DB 实测确认）。连带取消 `plugins` 表 slug 数据迁移。

沿用 `filamentboot-site` 包名与 `Filamentboot\FilamentbootSite\` 命名空间，新增代码不做任何改名预留。

> 副作用：`is_published` 旧列原定「随包重命名一起删」，锚点没了，已改挂到阶段 3 目录重构的破坏性变更批次。
