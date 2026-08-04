# 官网 CMS 未完成 tasks

> **这份文档是给新会话直接开工用的**：读完「零、开工须知」你就有了全部上下文，不需要回溯任何历史对话。
>
> 只列还没做的。已交付的见 [已完成 tasks](已完成tasks.md)——那份逐项记了落点文件、与原计划的差异、开工后才踩到的坑，**改动现有代码前先查它**。
>
> 更新时间：2026-08-04（第五轮阶段 2 收口后，#13–#21 已交付）
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
# 起前台
php artisan serve --port=8123        # 然后 http://localhost:8123/

# 全量验证（每个任务收工前都要过）
composer test                        # 当前基线：717 通过 / 2585 断言
composer pint:test                   # 格式，失败就跑 composer pint 自动修
composer phpstan                     # 根项目，必须 0 告警

# 主包（改到 packages/filamentboot/ 才需要）
cd packages/filamentboot && composer test    # 83 通过

# 站点包静态分析：当前 10 个存量告警，见 §0.6，不应增加
vendor/bin/phpstan analyse --level=6 packages/filamentboot-site/src

# 站点包没有独立 vendor/（monorepo path 仓库），元数据测试只能直接指文件
vendor/bin/phpunit --bootstrap vendor/autoload.php --no-configuration \
  packages/filamentboot-site/tests/Unit/SitePackageMetadataTest.php
```

> `SitePackageMetadataTest` 会断言 `packages/filamentboot-site/README.md` 里写的「执行数据库迁移（N 张内容表）」与迁移里 `Schema::create` 的实际条数一致。**每次新增建表迁移都要同步改 README**，否则它会红——而且它不在 `composer test` 里，红了不会被发现。当前是 16 张。

改了视图或配置没生效，先 `php artisan view:clear && php artisan config:clear`。

## 0.3 六条硬约束（违反会被打回）

1. **双主题完全独立。** 两套主题：`decoration`（深色，默认，设计精力给它）与 `tech-product`（浅色）。任何新增或修改的**视觉**视图与样式，必须在 `resources/views/themes/decoration/` 与 `resources/views/themes/tech-product/` 各存一份完整副本，**不许抽公共、不许在 `shared/` 下新建视觉组件**。
   > 理由是用户明确要求的：客户装上后可能只想保留一套、方便删除；而且以后会做样式跨度很大的第三套主题，代码不能耦合。
   > 纯数据、纯逻辑（PHP 服务、meta 标签输出）不受此限，`shared/` 下已有的 `seo-meta`、`analytics`、`floating-contact`、`image-placeholder`、`contact-panel-store` 就是这类。

2. **富文本输出一律走 `Support\RichText::purify()`**（安全硬要求 T-10-05-01）。禁止裸 `{!! $record->content_zh !!}`，也**不要**退回 `app('purifier')->clean()`——那会用 mews/purifier 的 default 画像，把标题、引用、代码块、表格全部静默剥掉（2026-08-03 实测）。

3. **草稿绝不能泄露到前台**（T-10-04-04）。所有前台查询走各模型的 `published()` 作用域。唯一例外是 `/preview/{page}` 预览路由（#16 已交付），它有独立的双通道授权 + `X-Robots-Tag: noindex`。

4. **slug 参数走 Eloquent `where()` 参数绑定**（T-10-04-03），不要拼字符串。

5. **公开页只用 Alpine，不新增 Livewire。** 阶段 4（#29）要把公开页做成可整页缓存，每加一个 Livewire 组件就多一处会话 Cookie 与 `Cache-Control: private, no-store`。

6. **未经明确要求不 commit、不 push。** 删除、覆盖、force push、改全局配置等不可逆操作同理，要做先确认。

另外按仓库约定：**中文输出、中文 PHPDoc、最小且简洁**——只改被要求的，不顺手重构，不加没要求的防御逻辑，无关问题提及但不处理。

## 0.4 代码地图

```
packages/filamentboot-site/
├── config/filamentboot-site.php     路由模式、保留 slug、主题白名单、询盘来源、SEO、purifier 画像
├── database/
│   ├── migrations/                  16 张内容表
│   ├── settings/                    Spatie settings 迁移（站点设置字段）
│   ├── factories/  seeders/
├── resources/
│   ├── css/themes/{decoration,tech-product}.css    各含一份手写 .prose（未装 typography 插件）
│   └── views/
│       ├── themes/{decoration,tech-product}/       ← 视觉层，两份完整副本
│       │   ├── layouts/{base,app}.blade.php
│       │   ├── components/                         nav footer hero *-card breadcrumb mobile-action-bar
│       │   ├── blocks/                             7 个区块视图（#13）
│       │   ├── {cases,solutions,products,news,pages}/
│       ├── shared/components/                      跨主题共享的非视觉件
│       └── livewire/contact-form.blade.php
└── src/
    ├── Cms/Blocks/                  区块契约 + 注册表 + 7 个内置区块
    ├── Cms/Rendering/               BlockRenderer（渲染 + FAQPage）+ BlockSanitizer（保存侧净化）
    ├── Cms/Services/MenuResolver.php  前台导航解析（平铺 + rememberForever 缓存）
    ├── Enums/PageStatus.php         状态机：allowedTransitions() / canTransitionTo()
    ├── Filament/                    Resources/ Pages/ Exporters/ RelationManagers/
    ├── Http/{Controllers,Livewire,Middleware}/    含 SiteRedirectMiddleware（全局，301）
    ├── Models/                      SiteCase SiteSolution SiteProduct SitePage
    │                                SitePageRevision SiteMenu SiteMenuItem SiteRedirect
    │                                ContactMessage SiteTag …
    ├── Modules/News/                资讯模块（已按阶段 3 的目标路径建，重构时不用搬）
    ├── Observers/                   SearchPushObserver（百度推送）+ SitePageObserver（版本快照）
    ├── Policies/                    五类内容 + SiteMenu / SiteMenuItem / SiteRedirect
    ├── Services/  Settings/  Jobs/  Console/Commands/
    ├── Support/                     RichText（富文本白名单）+ SafeUrl（链接 scheme 白名单）+ ThemeAsset
    ├── SitePlugin.php               向 Filament 面板注册 10 个 Resource + 设置页
    └── SiteServiceProvider.php      条件注册前台路由/视图/Livewire/观察器/中间件/命令
```

**视图解析优先级**（`SiteServiceProvider::registerThemeViews()` 用 `replaceNamespace()` 实现）：

```
resources/views/vendor/filamentboot-site/themes/{theme}/    ← 宿主发布覆盖
包内 resources/views/themes/{theme}/                        ← 当前主题
包内 resources/views/shared/                                ← 跨主题共享
包内 resources/views/                                       ← Livewire 视图兜底
```

所以 `view('filamentboot-site::cases.show')` 会自动落到当前主题那一份。当前主题读 `SiteSettings.active_theme`，非法值按 `config('filamentboot-site.themes')` 白名单强制回退（防目录穿越）。

**前台路由**在 `routes/site.php`，**仅在 `plugins.is_enabled = true` 时**由 `SiteServiceProvider::registerFrontend()` 加载。`/{slug}` 静态页兜底必须最后注册，并用负向预查排除 `config` 里的 `reserved_slugs`（已含 `admin`、`api`、`livewire`、`storage`、`sitemap.xml`、`robots.txt`、`preview`、`login`、`logout`）。

## 0.5 已有的可复用件（别重造）

| 要做的事 | 用现成的 |
|---|---|
| 输出富文本 | `Support\RichText::purify($html)` |
| 输出作者填的链接 | `Support\SafeUrl::sanitize($url)`——放行 `/` `#` `http(s)` `tel:` `mailto:`，其余返回 null，调用方据此**不渲染**（不要降级成 `#`） |
| 渲染页面区块 | `Cms\Rendering\BlockRenderer`（`render()` / `structuredData()`）；保存侧过 `BlockSanitizer` |
| 读前台导航 | `Cms\Services\MenuResolver::resolve('main'\|'footer')`，返回 null 时各主题 blade 用硬编码数组兜底 |
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
| 区块 / 状态机 / 链接白名单（纯逻辑） | `tests/Unit/Cms/{BlockRegistry,BlockRenderer,PageStatus,SafeUrl}Test.php` |

> **后台页面测试的关键手法**：官网插件的 Filament 资源路由在应用 boot 时注册，而插件启用状态来自 `plugins` 表，测试库那时还没数据，所以后台页默认渲染不出来。做法是**手工把插件注册进面板 → 重跑 `vendor/filament/filament/routes/web.php` → 刷新路由名查找表**，现成模板见 `SitePageResourcePageTest.php` 的 `beforeEach`。后台交互别留给手工点击。

> **全局中间件在测试里不会自动挂上**（`SiteServiceProvider` 用 `callAfterResolving(Kernel::class)`，而测试启动时 Kernel 早已解析完）。要测 `SiteRedirectMiddleware` 得显式 `app(Kernel::class)->pushMiddleware(...)`，见 `SiteRedirectTest.php`。

> **前台测试**同理要手工调 `SiteServiceProvider` 的 `registerLivewireComponents` / `registerThemeViews` / `shareSiteSettings` / `registerFrontend`，再 `refreshNameLookups()`。现成模板见 `SiteSeoMetaTest.php` 的 `beforeEach`。

Pest 断言注意：`toContain($needle, $message)` 会把第二个参数当成**另一个 needle**，要带失败说明就用 `$this->assertStringContainsString($needle, $haystack, $message)`。

## 0.7 已知存量问题（不是你引入的，别去修）

**站点包 PHPStan level 6 有 10 个存量告警**，根 `phpstan.neon` 只扫 `app` 与 `database` 所以 `composer phpstan` 是绿的。按仓库约定「无关问题提及但不处理」，未修——**但新增代码不应让这个数字变大**。

| 文件 | 告警 |
|---|---|
| `SiteCaseResource` / `SitePageResource` / `SiteProductResource` / `SiteSolutionResource` / `NewsArticleResource` | `getEloquentQuery()` 返回类型 ×5 |
| `ContactMessage` / `SiteTag` | `HasFactory` 泛型未声明 ×2 |
| `SiteFrontController` | `newsIndex` 的 `published()`、`newsArchiveMonths()` 返回类型、`?->name_zh` 多余 nullsafe ×3 |

> 踩过的坑：模型作用域经 `class-string` 变量或 `Builder<Model>` 调用时，PHPStan 看不见它（`method.notFound`）。写成具体类调用即可，既保住类型又仍复用各模型自己的判据——`Observers/SearchPushObserver.php` 的 `isVisible()` 是现成范例。

**素材空缺**：18 个产品封面全部没有，`news/is-voice-control-useful` 也没有。原因见 §四。前台会走 `image-placeholder` 降级，不影响开发。

**工作树里有 5 个不属于本任务链的未跟踪文件**（`docs/cms/02-自身官网.md`、两个 `playwright.config*.cjs`、`tests/e2e/uat-phase03.spec.cjs`、`tests/e2e/uat-phase11.spec.cjs`）。**不要动、不要提交它们。**（`tests/e2e/uat-phase12.spec.cjs` 是第五轮新增的，属本任务链。）

**第五轮的改动尚未提交**：#13–#21 的全部代码与测试都在工作树里未 commit（按硬约束第 6 条，未经明确要求不 commit）。

---

# 一、任务一览

| 批次 | 内容 | 估时 | 阻塞于 |
|------|------|------|--------|
| **阶段 3** | #27 目录重构 | 10h | 无 ← **建议起点** |
| 阶段 4 | #28 主题契约 | 5h | 无（可与 #27 并行） |
| 阶段 4 | #29 缓存边界 | 5h | 无（可与 #27 并行） |
| 阶段 4 | #30 v1.0.0 发布验证 | 4h | #27–#29 |
| — | **合计** | **≈24h** | 按 4h/周约 6 周 |

另有 6 条暂不排期的缺口（§三）和 4 条**用户本人做**的手工项（§四），都不计入上面的工时。

```
#27 目录重构 ─┐
#28 主题契约 ─┼─→ #30 v1.0.0 发布验证
#29 缓存边界 ─┘
```

> **阶段 2 已于 2026-08-04 全部收口**（#13–#21，第五轮）。落点、与原计划的差异、开工后才踩到的坑全部记在
> [已完成 tasks](已完成tasks.md) 的「第 5 轮」一节——**动这批代码前先查它**，尤其是
> `BlockRenderer` 的降级契约、`SitePageObserver` 的 `created`/`updated` 分工、
> `MenuResolver` 只返回平铺列表的理由，以及 `SiteRedirectMiddleware::targetUrl()` 里
> 那个「按 scheme 判而非按 `://` 判」的伪协议绕过。

**从 #27 开始。** 它是 #30 的前置里工作量最大的一项，且属破坏性变更，越早做越少返工。

---

# 二、阶段 3 与阶段 4

## #27 目录重构（≈10h，无阻塞）

```
src/Cms/{Blocks,Filament,Models,Rendering,Routing,Services,Themes}/
src/Modules/Corporate/{Cases,Products,Solutions}/
```

第五轮新建的 `src/Cms/Rendering/`（BlockRenderer / BlockSanitizer）与 `src/Cms/Services/`（MenuResolver）已在目标位置，不用搬。`src/Modules/News/` 当初就是按这个目标路径建的，也不用搬。

- ⚠️ **只移动不改名。** `BasePolicy` 从**短类名**推导权限点，`SiteCase` → `CorporateCase` 会静默改掉 `view_any_site_case` 并让现有角色权限全部失效。确需改名必须在 Policy 显式覆盖权限前缀。**`SiteRoleSeeder` 里硬编码了那批权限点名**，改名要同步改它，否则三层角色会全部授不上权。
- 首页聚合从 `SiteFrontController::home()` 抽成模块提供的 `HomeSectionProvider`，通用控制器不再硬编码 `SiteCase::featured()`。
- 路由 URL 与数据表名**全部不变**。
- **顺带删 `site_pages.is_published` 旧列**：它由 `SitePage::booted()` 的 saving 钩子镜像维护，原定「随包重命名一起删」，改名取消后锚点没了，挪到这里的破坏性变更批次。删列时同步删钩子与 `casts` 里的 `'is_published' => 'boolean'`。
- 改完必须同步：`composer.json` 的 PSR-4 autoload、`SitePlugin` 的 Resource 注册（现在是 10 个）、`SiteServiceProvider` 的 Observer / 中间件 / 迁移路径注册、所有 `use` 语句。`composer dump-autoload` 后跑全量测试。
- ⚠️ **Policy 靠 Laravel 的约定发现解析**（`Models\X` → `Policies\XPolicy`），没有一处显式 `Gate::policy()` 登记。搬动 `Models/` 或 `Policies/` 的相对位置会让约定失效，且**不报错**——只会静默变成「所有人都没权限」。搬完必须跑一遍带权限断言的用例（`SitePageResourcePageTest` / `SiteMenuResourcePageTest` / `SiteRoleSeederTest`）。

## #28 主题契约与切换预检查（≈5h）

`ThemeContract` + 每主题 `theme.php` manifest（声明支持的 template 与 block key）。切换主题前校验已发布页面用到的 template/block 是否被目标主题支持，不支持则列出受影响页面并要求确认。

`BlockRenderer` 已做了运行时兜底（缺视图跳过 + 记 warning），这里补的是**切换前的预检查**。

顺带在这里放开两处被刻意压住的能力（都是为了守住「后台配得出来的，前台一定显示得出来」）：

- **二级导航**：`SiteMenuItemTree::$maxDepth` 现在是 1，`MenuResolver` 只返回平铺列表，因为两套主题都没有下拉版式。manifest 里声明支持二级的主题才放开。
- **`page_templates`**：config 里目前只有 `default`。落地页极简版式（无导航干扰、单一转化目标）在这里连版式一起做，控制器的 `pageTemplate()` 已留好 `pages.templates.{key}` 解析与回退。

## #29 缓存边界（≈5h）

面板骨架改纯 Alpine，Livewire 组件用 `<template x-if>` 延迟挂载，公开页响应头改 `public, max-age=…`。

当前每个公开页都无条件 `@include` 含 `@livewire` 的 `floating-contact`，导致会话 Cookie + `Cache-Control: private, no-store`。**要一起改的有三处**：`floating-contact`、移动端操作条、以及 #13 的 `contact-form` 区块（它同样内联 `@livewire('filamentboot-site::contact-form')`）。

延迟挂载时注意 `ContactForm` 的两个属性：`$renderedAt`（C2 的提交耗时校验基准，必须是**真实渲染时刻**，`<template x-if>` 推迟挂载会让它变成"点开表单的时刻"，那正是想要的语义，不要改回页面加载时刻）与 `$tracksPanelSource`（#13 加的，区块内联实例靠它不被面板 store 覆盖 source）。

## #30 v1.0.0 发布验证（≈4h，阻塞于 #27–#29）

干净的独立 Laravel 13 项目 `composer require` 验证，走一遍安装 → 迁移 → seed → 登录 → 建页面 → 前台可见。

seed 一步现在有两个**必需**的种子（少跑就是「除超管外无人能进官网管理」）：`SitePermissionSeeder` 建权限点，`SiteRoleSeeder` 建三层角色。两者都已登记进 `composer.json` 的 `extra.filamentboot.post_install.seeders`，README 的安装章节也写了，验证时要确认那条自动化真的跑了。

---

# 三、暂不排期，但已识别的缺口

以下功能确认缺失，因依赖阶段 2/4 的能力或工作量较大，不进上面的批次。**排期前先确认前置是否已就位。**

| 缺口 | 归属 | 原因 |
|------|------|------|
| 站内搜索（前台跨模块） | 阶段 4 后 | 内容模型已随阶段 2 定型，前置已就位 |
| 相关内容推荐（详情页底部） | 阶段 4 后 | 资讯详情页已有同分类 `$related` 兜底，案例/方案/产品还没有 |
| 落地页极简版式（无导航干扰、单一转化目标） | 阶段 4 | 依赖 #28 主题契约；`template` 字段与控制器的 `pageTemplate()` 解析已就位，只缺 config 里加一项 + 两套主题各一份 `pages/templates/{key}.blade.php` |
| 二级导航（下拉菜单） | 阶段 4（#28） | 后台与数据层都支持（`site_menu_items.parent_id`），但树页 `maxDepth=1`、`MenuResolver` 返平铺，因为两套主题没有下拉版式 |
| 资料索取 / gated content（手册换联系方式） | 阶段 4 后 | 依赖 A1 的线索链路（已就位） |
| 在线客服脚本位、联系页地图嵌入 | 阶段 4 后 | A3 的注入位已做完，成本很低 |
| 表单字段可配置（不同活动问不同问题） | 阶段 4 后 | 区块 Schema 机制已随 #13/#14 就位 |

---

# 四、用户本人做的四条（不要代劳）

| # | 内容 | 说明 |
|---|---|---|
| #31 | 隐私政策页补访客数据收集范围 | **优先级比看起来高。** A1 已在收集 source / landing_url / referer / UTM 五项，而页脚隐私链接读 `SiteSettings.privacy_url`，未配置时整个链接不渲染——也就是说线上目前**没有隐私政策入口**，数据却已经在收了。 |
| #32 | 生产收尾 | qkznj.com 后台填电话 / 地址 / ICP 备案号 / 隐私链接 / 默认 SEO 标题与描述 / OG 图 / logo / 微信二维码，直到设置页健康检查无告警。部署后 `php artisan view:clear && config:clear`、`npm run build`，确认生产 `.env` 有 `SITE_ROUTE_MODE=root`。 |
| #10 | 手动验收 | 双主题手点 + Playwright。移动端操作条与微信弹层只有单元级断言，没在真机视口跑过。第五轮新增 `tests/e2e/uat-phase12.spec.cjs`（建页 → 拖区块 → 发布 → 前台可见、改 slug 建 301、菜单同步、草稿预览），**从未真机跑过，选择器多半要现场调**。 |
| #11 | 产品封面图 | 18 张产品图空缺，等品牌方渠道商素材包。CC0 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。详见 [cc0-assets](cc0-assets/README.md)。 |

> 素材使用边界（用户已定）：✅ 品牌官方产品图、参数/型号/价格带、详情页版式结构；❌ 店铺自制促销长图（带旗舰店角标，放自己站上穿帮）、买家秀（真实用户自家环境，含人脸，属个人信息）。抓来的文案只做结构参考 + 批量改写，**不原样入库**。
