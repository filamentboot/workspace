# 官网 CMS 未完成 tasks

> **这份文档是给新会话直接开工用的**：读完「零、开工须知」你就有了全部上下文，不需要回溯任何历史对话。
>
> 只列还没做的。已交付的见 [已完成 tasks](已完成tasks.md)——那份逐项记了落点文件、与原计划的差异、开工后才踩到的坑，**改动现有代码前先查它**。
>
> 更新时间：2026-08-04（第七轮收口后；原 §二 那批缺口已全部交付。本文档现在只剩**用户本人做**的手工项）
>
> 上游规划：[基于装修网站官网优化 CMS](基于装修网站官网优化cms.md)

---

# 零、开工须知

## 0.1 这是什么

`filamentboot/filamentboot-site` 是 Filamentboot 后台平台的**官网插件**，给 Laravel 13 + Filament 5 项目加一整套企业官网内容管理能力。它以 monorepo 子包形式开发，宿主是仓库根目录的演示项目。

真实用途有两个，都要照顾到：

1. **湖北晴空妙享科技有限公司**（武汉智能家居设计施工商）的官网，域名 qkznj.com
2. **CMS 演示站 / 教程案例**——所以演示数据要像真的，但站上没有真实经营数据，素材全部外部获取

| 项 | 值 |
|---|---|
| 包路径 | `packages/filamentboot-site/` |
| 命名空间 | `Filamentboot\FilamentbootSite\` |
| 视图命名空间 | `filamentboot-site::` |
| PHP / Laravel / Filament | `^8.3` / `^13.8` / `^5.0` |
| 后台面板 | `/admin`，默认账号 `admin@example.com` / `password` |
| 前台路由模式 | 本地 `.env` 是 `SITE_ROUTE_MODE=root`，所以前台路径**没有** `/site` 前缀 |

> ⚠️ 包重命名为 `filamentboot-cms` 的计划**已于 2026-08-03 取消**。沿用现有包名与命名空间，新增代码不做任何改名预留。仓库里若还有文档写着要改名，那是过期内容。

## 0.2 环境与命令

数据库连接看 `.env`（MySQL，`127.0.0.1:3380`，库名 `filamentboot`）；测试库是 `filamentboot_test`，配置在 `phpunit.xml`。

```bash
# 起前台（插件已启用，前台六类页面均 200；若全站 404 先查 plugins.is_enabled 与 cache:clear）
php artisan serve --port=8123        # 然后 http://localhost:8123/

# 全量验证（每个任务收工前都要过）
composer test                        # 当前基线：881 通过 / 3092 断言
composer pint:test                   # 格式，失败就跑 composer pint 自动修
composer phpstan                     # 根项目，必须 0 告警

# 主包（改到 packages/filamentboot/ 才需要）
cd packages/filamentboot && composer test    # 83 通过

# 站点包静态分析：当前 0 告警，不应增加
# ⚠️ 插件禁用时这条会多报 13 条 view() 的 argument.type——filamentboot-site:: 视图命名空间
#    由 SiteServiceProvider::boot() 在启用分支里注册，禁用时 larastan 解析不到包内视图。
#    跑之前先确认 plugins.is_enabled = 1。
vendor/bin/phpstan analyse --level=6 packages/filamentboot-site/src

# 站点包没有独立 vendor/（monorepo path 仓库），元数据测试只能直接指文件
vendor/bin/phpunit --bootstrap vendor/autoload.php --no-configuration \
  packages/filamentboot-site/tests/Unit/SitePackageMetadataTest.php
```

> `SitePackageMetadataTest` 会断言 `packages/filamentboot-site/README.md` 里写的「执行数据库迁移（N 张内容表）」与迁移里 `Schema::create` 的实际条数一致。**每次新增建表迁移都要同步改 README**，否则它会红——而且它不在 `composer test` 里，红了不会被发现。当前是 16 张。

后台 UAT（真机，需先起 serve）：

```bash
BASE_URL=http://localhost:8123 npx playwright test uat-phase12 --config=playwright.config.uat.cjs
```

> 选择器约定见该 spec 的头注释——**面板语言是 en**，表单控件 id 是 `form.<path>` 而非 `data.<path>`，
> `native(false)` 的 Select 不是 `<select>`。照 Filament 文档想当然写会一个都选不中。
> 跑完会在开发库留下 `uat12-*` 前缀的页面 / 重定向 / 菜单项，记得清。

改了视图或配置没生效，先 `php artisan view:clear && php artisan config:clear`。

## 0.3 六条硬约束（违反会被打回）

1. **双主题完全独立。** 两套主题：`decoration`（深色，默认，设计精力给它）与 `tech-product`（浅色）。任何新增或修改的**视觉**视图与样式，必须在 `resources/views/themes/decoration/` 与 `resources/views/themes/tech-product/` 各存一份完整副本，**不许抽公共、不许在 `shared/` 下新建视觉组件**。
   > 理由是用户明确要求的：客户装上后可能只想保留一套、方便删除；而且以后会做样式跨度很大的第三套主题，代码不能耦合。
   > 纯数据、纯逻辑（PHP 服务、meta 标签输出）不受此限，`shared/` 下已有的 `seo-meta`、`analytics`、`floating-contact`、`image-placeholder`、`contact-panel-store` 就是这类。

2. **富文本输出一律走 `Support\RichText::purify()`**（安全硬要求 T-10-05-01）。禁止裸 `{!! $record->content_zh !!}`，也**不要**退回 `app('purifier')->clean()`——那会用 mews/purifier 的 default 画像，把标题、引用、代码块、表格全部静默剥掉（2026-08-03 实测）。

3. **草稿绝不能泄露到前台**（T-10-04-04）。所有前台查询走各模型的 `published()` 作用域。唯一例外是 `/preview/{page}` 预览路由（#16 已交付），它有独立的双通道授权 + `X-Robots-Tag: noindex`。

4. **slug 参数走 Eloquent `where()` 参数绑定**（T-10-04-03），不要拼字符串。

5. **公开页零 Livewire、零 session**（#29 已落地，见 §一 的新硬约束）。加一个 Livewire 组件就会把 `livewire.js` 拉进页面，那个 script 标签带 `data-csrf` → 起 session → 整页缓存全面失效，且**不会有任何报错**。有交互需求就用 Alpine（已由 `resources/js/site.js` 独立交付）+ 无状态端点。
   > 连带约束：**公开页的 HTML 必须是确定性的**。任何随机数（token、`Str::random()`、`uniqid()`）都会让
   > 同一份内容每次请求产出不同 HTML。这也是资料索取的 key 取路径 sha1 前缀而不是随机 token 的原因。

6. **未经明确要求不 commit、不 push。** 删除、覆盖、force push、改全局配置等不可逆操作同理，要做先确认。

另外按仓库约定：**中文输出、中文 PHPDoc、最小且简洁**——只改被要求的，不顺手重构，不加没要求的防御逻辑，无关问题提及但不处理。

## 0.4 代码地图

```
packages/filamentboot-site/
├── config/filamentboot-site.php     路由模式、保留 slug、主题白名单、页面版式、询盘来源、SEO、
│                                   缓存、地图 host 白名单、资料索取磁盘与链接时限、purifier 画像
├── database/
│   ├── migrations/                  16 张内容表
│   ├── settings/  factories/  seeders/
├── resources/
│   ├── css/themes/{decoration,tech-product}.css    各含一份手写 .prose（未装 typography 插件）
│   └── views/
│       ├── themes/{decoration,tech-product}/       ← 视觉层，两份完整副本
│       │   ├── theme.php                           主题清单（#28：templates / blocks / features）
│       │   ├── layouts/{base,app}.blade.php
│       │   ├── components/                         nav footer hero *-card breadcrumb mobile-action-bar
│       │   ├── blocks/                             9 个区块视图（含 map / gated-download）
│       │   ├── pages/show.blade.php  pages/templates/landing.blade.php
│       │   ├── {cases,solutions,products,news}/  search.blade.php
│       ├── shared/components/                      跨主题共享的非视觉件（seo-meta analytics live-chat
│       │                                           contact-form contact-panel-store attribution-store
│       │                                           floating-contact image-placeholder）
│       └── mail/new-contact-message.blade.php      新询盘通知邮件正文
│                                                   （#29 起包内没有 livewire/ 目录了）
└── src/
    ├── Cms/                         CMS 核心（与行业内容无关）
    │   ├── Blocks/                  区块契约 + 注册表 + 9 个内置区块
    │   ├── Rendering/               BlockRenderer（渲染 + FAQPage）+ BlockSanitizer（保存侧净化）
    │   ├── Services/               MenuResolver（导航解析，嵌套 + rememberForever）
    │   │                           RelatedContent（详情页相关推荐）
    │   │                           SiteSearch（跨模块搜索）
    │   │                           GatedAssetRegistry（可索取资料登记表，rememberForever）
    │   ├── Models/                  SitePage SitePageRevision SiteMenu SiteMenuItem SiteRedirect
    │   ├── Enums/PageStatus         状态机：allowedTransitions() / canTransitionTo()
    │   ├── Filament/Resources/      SitePage / SiteMenu / SiteMenuItem / SiteRedirect
    │   ├── Policies/                上面四类的 Policy
    │   ├── Observers/SitePageObserver   版本快照
    │   ├── Routing/SiteRedirectMiddleware   全局 301
    │   └── Themes/                  ThemeContract + ThemeManifest + ThemeSwitchCheck + ThemeAsset
    ├── Modules/
    │   ├── Corporate/
    │   │   ├── Cases/{Models,Filament,Policies,Enums}
    │   │   ├── Products/{Models,Filament,Policies}
    │   │   ├── Solutions/{Models,Filament,Policies}
    │   │   └── Home/HomeSectionProvider     首页聚合（宿主可 bind 替换）
    │   └── News/                    资讯模块
    ├── Http/Controllers/            SiteFront / Sitemap / ContactSubmission / GatedDownload
    │                                （#29 起没有 Livewire/ 与 Middleware/）
    ├── Models/                      ContactMessage ContactMessageNote SiteTag（跨模块，留顶层）
    ├── Policies/ContactMessagePolicy
    ├── Filament/                    ContactMessageResource / SiteSettingsPage / Exporters / Widgets
    ├── Observers/SearchPushObserver     百度推送
    ├── Services/  Settings/  Jobs/  Mail/  Console/Commands/
    ├── Support/                     RichText（富文本白名单）+ SafeUrl（链接 scheme 白名单）
    │                                + MapEmbed（地图 iframe host 白名单）
    ├── SitePlugin.php               向 Filament 面板注册 10 个 Resource + 设置页
    └── SiteServiceProvider.php      条件注册前台路由/视图/区块注册表/观察器/中间件/命令
```

**视图解析优先级**（`SiteServiceProvider::registerThemeViews()` 用 `replaceNamespace()` 实现）：

```
resources/views/vendor/filamentboot-site/themes/{theme}/    ← 宿主发布覆盖
包内 resources/views/themes/{theme}/                        ← 当前主题
包内 resources/views/shared/                                ← 跨主题共享
包内 resources/views/                                       ← Livewire 视图兜底
```

所以 `view('filamentboot-site::cases.show')` 会自动落到当前主题那一份。当前主题读 `SiteSettings.active_theme`，非法值按 `config('filamentboot-site.themes')` 白名单强制回退（防目录穿越）。

**前台路由**在 `routes/site.php`，**仅在 `plugins.is_enabled = true` 时**由 `SiteServiceProvider::registerFrontend()` 加载。`/{slug}` 静态页兜底必须最后注册，并用负向预查排除 `config` 里的 `reserved_slugs`（已含 `admin`、`api`、`livewire`、`storage`、`sitemap.xml`、`robots.txt`、`preview`、`login`、`logout`、`search`、`downloads`）。

## 0.5 已有的可复用件（别重造）

| 要做的事 | 用现成的 |
|---|---|
| 输出富文本 | `Support\RichText::purify($html)` |
| 输出作者填的链接 | `Support\SafeUrl::sanitize($url)`——放行 `/` `#` `http(s)` `tel:` `mailto:`，其余返回 null，调用方据此**不渲染**（不要降级成 `#`） |
| 渲染页面区块 | `Cms\Rendering\BlockRenderer`（`render()` / `structuredData()`）；保存侧过 `BlockSanitizer` |
| 读前台导航（带层级） | `Cms\Services\MenuResolver::resolve('main')`，每项带 `children`；主题清单未声明 `nested_menu` 时自动摊平。返回 null 时各主题 blade 用硬编码数组兜底 |
| 读页脚快捷链接（强制平铺） | `MenuResolver::resolveFlat('footer')`——页脚不该跟着主题的 nested_menu 变形状 |
| 问某个主题支持什么 | `Cms\Themes\ThemeManifest::for($theme)` / `::active()`（`templates()` `blocks()` `supports('nested_menu')`）；清单缺失时扫目录推断，features 一律按不支持 |
| 切主题前检查内容兼容性 | `Cms\Themes\ThemeSwitchCheck::inspect($theme)` / `passes($theme)`，只查已发布页面 |
| 详情页底部「相关内容」 | `Cms\Services\RelatedContent::for($query, $record, $affinities)`——查询由调用方用**具体模型类**构造并套好 `published()` 与排序（局部作用域在泛型 Builder 上解析不出来）。⚠️ 资讯不用它，见其类注释 |
| 跨模块检索内容 | `Cms\Services\SiteSearch::search($term)`，按类型分组。区块正文搜不到（JSON 列的中文是 Unicode 转义），见其类注释 |
| 输出地图 iframe | `Support\MapEmbed::sanitize($url)`——只放行 https + host 精确命中 config 白名单，不通过返回 null，调用方据此**不渲染 iframe** |
| 放一份要留资才能下的资料 | `gated-download` 区块 + `Cms\Services\GatedAssetRegistry`。文件放非公开磁盘，前台只出不透明 key，链接由提交端点现签 |
| 页面状态流转 | `Enums\PageStatus::canTransitionTo()`；发布类动作要 `authorize('publish')` |
| 内容没有封面图 | `@include('filamentboot-site::components.image-placeholder', [...])`，不要出破图 |
| 封面图 / 图集 / 三档转换 | `src/Concerns/HasCoverImage.php`（`coverUrl()` `galleryUrls()` `ogImageUrl()`；`thumb` 400×300、`card` 800×600、`og` 1200×630） |
| 种子按 slug 增量补种 | `database/seeders/Concerns/SeedsBySlug.php`（slug 的 unique 索引不带 `deleted_at`，必须 `withTrashed()` 查，否则撞软删记录直接 500） |
| 打开询盘面板 | Alpine 全局 store：`$store.contactPanel.show('来源标识')`，来源标识要登记进 `config` 的 `contact.sources` |
| 通知类副作用 | 照 `Services/ContactMessageNotifier.php`：整段 try/catch + `report()`，绝不能把保存打成 500 |
| 模型观察器 | 照 `Observers/SearchPushObserver.php` 的写法与注册方式（注意：**新建记录在 `saved` 里 `wasChanged()` 恒为 false**，要覆盖新建就得分开监听 `created`，见 `SitePageObserver`） |
| 面包屑 / 结构化数据 | 控制器里 `breadcrumbs()` 建数组，视图与 `breadcrumbSchema()` 各消费一次；`$seoData['jsonLd']` 可传节点列表 |
| 权限点 | 加进 `database/seeders/SitePermissionSeeder.php`，再按需接进 `SiteRoleSeeder` 的三档角色。`BasePolicy` 从**短类名**推导 `{action}_{resource_snake_case}`，`SitePage` → `site_page`；BasePolicy 之外的动作（`publish` / `rollback`）必须在 Policy 里显式加方法，否则 Gate 对非超管一律拒绝 |

## 0.6 测试怎么写

站点相关测试都在根项目的 `tests/Feature/`（26 个文件）与 `tests/Unit/Cms/`。新写测试前先看有没有能扩的：

| 场景 | 扩这个文件 |
|---|---|
| 前台渲染、区块渲染、**双主题各跑一遍** | `SiteContentRenderTest.php`——已有 `dataset('themes', ['decoration','tech-product'])` 与 `switchSiteTheme()` 辅助函数，`->with('themes')` 即可 |
| SEO meta / canonical / JSON-LD / og:image | `SiteSeoMetaTest.php`（有 `extractJsonLd()` 与 `extractJsonLdByType()`） |
| 页面四态可见性 | `SitePageStatusTest.php` |
| 富文本白名单 | `SiteRichTextTest.php` |
| 询盘表单 | `ContactFormTest.php`——注意所有提交用例都要 `->tap(humanPace())`，否则会被 C2 的「3 秒内提交判为机器人」挡下 |
| 后台 Filament 页面交互 | `SiteContactResourcePageTest.php` / `SiteNewsResourcePageTest.php` / `SitePageResourcePageTest.php` / `SiteMenuResourcePageTest.php` |
| 版本快照与回滚 | `SitePageRevisionTest.php` |
| 草稿预览授权 | `SitePagePreviewTest.php` |
| 导航菜单 | `SiteMenuTest.php` |
| 301 重定向 | `SiteRedirectTest.php` |
| 三层角色 | `SiteRoleSeederTest.php` |
| 详情页相关推荐 | `SiteRelatedContentTest.php`——⚠️ 资讯不走那个服务，改资讯的相关阅读要看 `SiteNewsTest` |
| 站内搜索 | `SiteSearchTest.php`（取数 + noindex/缓存边界 + 双主题渲染） |
| 询盘自定义字段 | `SiteContactExtraFieldsTest.php` |
| 资料索取 gated content | `SiteGatedDownloadTest.php`——四道闸各有用例，动这块前先读它的文件头注释 |
| 客服脚本位与统计注入 | `SiteAnalyticsInjectionTest.php` |
| 地图区块与 host 白名单 | `tests/Unit/Cms/MapEmbedTest.php` |
| 区块 / 状态机 / 链接白名单（纯逻辑） | `tests/Unit/Cms/{BlockRegistry,BlockRenderer,PageStatus,SafeUrl}Test.php` |

> **后台页面测试的关键手法**：官网插件的 Filament 资源路由在应用 boot 时注册，而插件启用状态来自 `plugins` 表，测试库那时还没数据，所以后台页默认渲染不出来。做法是**手工把插件注册进面板 → 重跑 `vendor/filament/filament/routes/web.php` → 刷新路由名查找表**，现成模板见 `SitePageResourcePageTest.php` 的 `beforeEach`。后台交互别留给手工点击。

> **全局中间件在测试里不会自动挂上**（`SiteServiceProvider` 用 `callAfterResolving(Kernel::class)`，而测试启动时 Kernel 早已解析完）。要测 `SiteRedirectMiddleware` 得显式 `app(Kernel::class)->pushMiddleware(...)`，见 `SiteRedirectTest.php`。

> ⚠️ **Filament 5 的模态体是客户端惰性渲染的**：`mountAction()` / `mountTableAction()` 之后，
> `Livewire::test()` 拿到的 `wire:partial="action-modals"` 分区仍然是空的，
> 断言模态里的 HTML（`assertSee` / `assertSeeHtml`）会**恒假**。要验模态内容就取
> `->instance()->getSchema('mountedActionSchema0')` 再断言组件的状态路径或渲染结果，
> 现成范例见 `SiteMenuResourcePageTest` 的状态路径护栏与 `SitePageResourcePageTest` 的版本对比断言。
> 真要看浏览器里的样子只能进 Playwright。

> ⚠️ **filament-tree 的树页动作曾经完全驱动不起来**，根因是基类 `getFormSchema()` 把组件绑到了
> 一个 statePath 为空的临时 Schema 上（#23 已修，覆写 `getFormSchema()` 返回未绑定的新组件）。
> 新增树页时照 `SiteMenuItemTree` 抄那个覆写，否则弹窗会因 Entangle Error 打不开。

> ⚠️ **别复用别的测试文件里 `dataset()` 定义的数据集**：Pest 的 `dataset('themes')` 只在定义它的
> 文件被加载时注册，单跑另一个文件时 `->with('themes')` 会让 Pest 报 failed 却列不出失败用例
> （`SiteContentRenderTest` 里有一份 `themes`）。跨文件要双主题就用行内数据集
> `->with(['decoration', 'tech-product'])`。

> **前台测试**同理要手工调 `SiteServiceProvider` 的 `registerLivewireComponents` / `registerThemeViews` / `shareSiteSettings` / `registerFrontend`，再 `refreshNameLookups()`。现成模板见 `SiteSeoMetaTest.php` 的 `beforeEach`。

> ⚠️ **别复用别的测试文件里定义的辅助函数**：和 `dataset()` 一样，Pest 测试文件里的 `function`
> 声明是全局的——重名会在两文件同时加载时直接 fatal，单跑另一个文件时又找不到。
> 现在有五份各自命名的主题切换辅助（`switchSiteTheme` / `switchThemeForRelated` /
> `switchThemeForSearch` / `switchThemeForGated` / `switchThemeForExtraFields`），
> 要共用得先搬进 `tests/Pest.php`。

> ⚠️ **MySQL 的 JSON 对象不保留键顺序。** 存 `{键: 值}` 映射再读出来，顺序会被 MySQL 规范化打乱
> （`site_contact_messages.extra` 就因此改成了有序列表 `[{label, value}]`）。
> 顺序有意义的数据一律存 JSON **数组**。同理，JSON 列里的中文是 Unicode 转义序列，
> `LIKE '%中文%'` 匹配不到——站内搜索因此搜不到区块正文。

Pest 断言注意：`toContain($needle, $message)` 会把第二个参数当成**另一个 needle**，要带失败说明就用 `$this->assertStringContainsString($needle, $haystack, $message)`。

## 0.7 已知存量问题（不是你引入的，别去修）

> ⚠️ **开工前先确认插件是启用的**：`plugins` 表要有 `filamentboot-site` 且 `is_enabled = 1`。
> 禁用时前台全站 404、后台官网资源路由 0 条、站点包 PHPStan 多报 13 条 view-string 告警。
> `pluginIsEnabled()` 外面套着 24h 缓存，改了 DB 记得 `php artisan cache:clear`。

**主包的 `MenuTree` 应当有 #23 那个 bug。** `Filamentboot\Filament\Resources\Menus\Pages\MenuTree`
同样继承 filament-tree 的 `TreePage` 且没覆写 `getFormSchema()`，后台「菜单规则」的建/改弹窗
大概率同样打不开。按仓库约定「无关问题提及但不处理」未修——它属于主包，另一条任务链。
修法照 `SiteMenuItemTree::getFormSchema()` 抄即可（另外驱动它时还会先撞上
`Cannot access protected property MenuTree::$title`，页面的 `$title` 属性与表单字段名撞车）。

**`plugins.post_install_data` 里存的是过期元数据。** `plugin:scan` 读
`vendor/composer/installed.json`，而 path 仓库的 installed.json 不随包内 `composer.json`
自动刷新——当前库里那行的 `post_install.seeders` 只有已删除的 `SiteSeeder`，描述还写着
「五类内容、双语」。属 #30 的隐患，#30 已确认不做，留个记录。

**`tests/e2e/site-contact-cta.spec.cjs` 的 3 条 `[mobile]` 用例是红的**（存量矛盾，非 #29 引入）。
它们断言悬浮气泡在移动端可见，而气泡本体带 `hidden sm:inline-flex`——按设计移动端由底部操作条
取代气泡，两者互斥，`SiteContentRenderTest` 里还有一条 `移动端隐藏悬浮气泡` 断言的正是相反的事。
要么把那 3 条限定成 desktop project，要么改设计让移动端也出气泡；这是个产品选择，没动。

**`shared/components/contact-form.blade.php` 的 `$formKey` 有一个非确定性兜底。**
不传 `formKey` 时它落到 `Str::random(6)`，同一份内容每次请求产出不同 HTML，
会毁掉整页缓存的确定性（也让 ETag 无从谈起）。当前所有调用点都传了 formKey，
`SiteAnalyticsInjectionTest` 里有一条「输出与关闭开关逐字节相同」的断言会在有人漏传时变红。
新增表单实例时记得传。

**素材空缺**：18 个产品封面全部没有，`news/is-voice-control-useful` 也没有。原因见 §二 的 #11。
前台会走 `image-placeholder` 降级，不影响开发。

**工作树里有 5 个不属于本任务链的未跟踪文件**（`docs/cms/02-自身官网.md`、
`playwright.config.cjs`、`playwright.config.phase11.cjs`、`tests/e2e/uat-phase03.spec.cjs`、
`tests/e2e/uat-phase11.spec.cjs`）。**不要动、不要提交它们。**
（`tests/e2e/uat-phase12.spec.cjs` 与 `playwright.config.uat.cjs` 属本任务链。）

**第六轮未提交**：#22–#29 的代码与测试还在工作树里。

---

# 一、任务一览

**阶段 1、2、3、4 全部已交付，原「暂不排期的缺口」也已全部交付。** #30 v1.0.0 发布验证用户明确不做。

**剩下的只有 4 条用户本人做的手工项（§二）。** 代码侧没有已识别、待开发的功能了。

> 第 6 轮（#22–#29）的落点、与原计划的差异、开工后才踩到的坑全部记在
> [已完成 tasks](已完成tasks.md) 的「第 6 轮」一节——**动这批代码前先查它**，尤其是：
> `SiteMenuItemTree::getFormSchema()` 那个必须覆写的理由（filament-tree 把状态路径缓存成裸字段名）、
> Filament 5 模态体是客户端惰性渲染因此 `Livewire::test()` 断言 HTML 恒假、
> Pint 会把 `@extends Resource<X>` 小写掉所以要写全限定名、
> `MenuResolver` 缓存嵌套结构而摊平放读取侧的理由、
> **公开页零 session 之后哪些事再也不能做**（见下）。

## ⚠️ 公开页的新硬约束（#29 之后）

这三条一旦违反，症状都是「CDN 命中率一直是 0」或「草稿经共享缓存泄露」——**不会有任何报错**：

1. **公开页不许起 session。** 不许出现 Livewire 组件（它注入的脚本带 `data-csrf`，
   渲染时调 `csrf_token()`）、不许在前台视图里调 `csrf_token()` / `@csrf`、
   不许往内容路由挂 `web` 中间件组。
   护栏：`tests/Feature/SiteCacheBoundaryTest.php` 断言内容页响应**无 Set-Cookie**、
   HTML 里无 `wire:snapshot`。
2. **`/preview/{page}` 必须留在 `web` 组，且绝不能被打上 `public`。** 它是全站唯一读未发布
   内容的入口，靠 `auth('admin')` 判权。同一份护栏里锁住了这条。
3. **Alpine 由 `resources/js/site.js` 经 Vite 交付，不再是 Livewire 捎带的。**
   宿主必须 `npm i alpinejs` 并把命中的入口路径加进 `vite.config.js` 的 `input`，
   否则前台所有 `x-data` 都不工作（导航抽屉、询盘面板、二级下拉、图集轮播）。
   候选路径见 config 的 `assets.script_entries`。

---

# 二、用户本人做的四条（不要代劳）

| # | 内容 | 说明 |
|---|---|---|
| #31 | 隐私政策页补访客数据收集范围 | **优先级比看起来高。** A1 已在收集 source / landing_url / referer / UTM 五项，而页脚隐私链接读 `SiteSettings.privacy_url`，未配置时整个链接不渲染——也就是说线上目前**没有隐私政策入口**，数据却已经在收了。 |
| #32 | 生产收尾 | qkznj.com 后台填电话 / 地址 / ICP 备案号 / 隐私链接 / 默认 SEO 标题与描述 / OG 图 / logo / 微信二维码，直到设置页健康检查无告警。部署后 `php artisan view:clear && config:clear`、`npm run build`，确认生产 `.env` 有 `SITE_ROUTE_MODE=root`。⚠️ 若用了资料索取，确认 `SITE_GATED_DISK` **不是** public——指到公开磁盘那道门就形同虚设，且不会报错。 |
| #10 | 手动验收 | 双主题手点。移动端操作条与微信弹层只有单元级断言，没在真机视口跑过。`uat-phase12` 已于第 6 轮真机跑通（8/8），但第 7 轮新增的东西还没有 E2E 覆盖：站内搜索、地图区块、可配置字段的下拉、资料索取（后者用 `curl` 验过整条链路，没在浏览器里点过）。 |
| #11 | 产品封面图 | 18 张产品图空缺，等品牌方渠道商素材包。CC0 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。详见 [cc0-assets](cc0-assets/README.md)。 |

> 素材使用边界（用户已定）：✅ 品牌官方产品图、参数/型号/价格带、详情页版式结构；❌ 店铺自制促销长图（带旗舰店角标，放自己站上穿帮）、买家秀（真实用户自家环境，含人脸，属个人信息）。抓来的文案只做结构参考 + 批量改写，**不原样入库**。
