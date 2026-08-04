# 官网 CMS 未完成 tasks

> **这份文档是给新会话直接开工用的**：读完「零、开工须知」你就有了全部上下文，不需要回溯任何历史对话。
>
> 只列还没做的。已交付的见 [已完成 tasks](已完成tasks.md)——那份逐项记了落点文件、与原计划的差异、开工后才踩到的坑，**改动现有代码前先查它**。
>
> 更新时间：2026-08-04（第四轮 B/C 组交付后）
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
composer test                        # 当前基线：561 通过 / 2026 断言
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

3. **草稿绝不能泄露到前台**（T-10-04-04）。所有前台查询走各模型的 `published()` 作用域。唯一例外是 #16 的预览路由，它有独立的双通道授权。

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
│       │   ├── {cases,solutions,products,news,pages}/
│       ├── shared/components/                      跨主题共享的非视觉件
│       └── livewire/contact-form.blade.php
└── src/
    ├── Cms/Blocks/                  区块契约 + 注册表 + 7 个内置区块（#12 已交付，前台出口是 #13）
    ├── Enums/PageStatus.php         draft → review → scheduled → published → archived
    ├── Filament/                    Resources/ Pages/ Exporters/
    ├── Http/{Controllers,Livewire,Middleware}/
    ├── Models/                      SiteCase SiteSolution SiteProduct SitePage
    │                                SitePageRevision SiteMenu SiteMenuItem SiteRedirect
    │                                ContactMessage SiteTag …
    ├── Modules/News/                资讯模块（已按阶段 3 的目标路径建，重构时不用搬）
    ├── Observers/SearchPushObserver.php
    ├── Services/  Settings/  Support/RichText.php  Jobs/  Console/Commands/
    ├── SitePlugin.php               向 Filament 面板注册 7 个 Resource + 设置页
    └── SiteServiceProvider.php      条件注册前台路由/视图/Livewire/观察器/命令
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
| 内容没有封面图 | `@include('filamentboot-site::components.image-placeholder', [...])`，不要出破图 |
| 封面图 / 图集 / 三档转换 | `src/Concerns/HasCoverImage.php`（`coverUrl()` `galleryUrls()` `ogImageUrl()`；`thumb` 400×300、`card` 800×600、`og` 1200×630） |
| 种子按 slug 增量补种 | `database/seeders/Concerns/SeedsBySlug.php`（slug 的 unique 索引不带 `deleted_at`，必须 `withTrashed()` 查，否则撞软删记录直接 500） |
| 打开询盘面板 | Alpine 全局 store：`$store.contactPanel.show('来源标识')`，来源标识要登记进 `config` 的 `contact.sources` |
| 通知类副作用 | 照 `Services/ContactMessageNotifier.php`：整段 try/catch + `report()`，绝不能把保存打成 500 |
| 模型观察器 | 照 `Observers/SearchPushObserver.php` 的写法与注册方式 |
| 面包屑 / 结构化数据 | 控制器里 `breadcrumbs()` 建数组，视图与 `breadcrumbSchema()` 各消费一次；`$seoData['jsonLd']` 可传节点列表 |
| 权限点 | 加进 `database/seeders/SitePermissionSeeder.php`。`BasePolicy` 从**短类名**推导 `{action}_{resource_snake_case}`，`SitePage` → `site_page` |

## 0.6 测试怎么写

站点相关测试都在根项目的 `tests/Feature/`（18 个文件）。新写测试前先看有没有能扩的：

| 场景 | 扩这个文件 |
|---|---|
| 前台渲染、**双主题各跑一遍** | `SiteContentRenderTest.php`——已有 `dataset('themes', ['decoration','tech-product'])` 与 `switchSiteTheme()` 辅助函数，`->with('themes')` 即可 |
| SEO meta / canonical / JSON-LD | `SiteSeoMetaTest.php`（有 `extractJsonLd()` 与 `extractJsonLdByType()`） |
| 页面四态可见性 | `SitePageStatusTest.php` |
| 富文本白名单 | `SiteRichTextTest.php` |
| 询盘表单 | `ContactFormTest.php`——注意所有提交用例都要 `->tap(humanPace())`，否则会被 C2 的「3 秒内提交判为机器人」挡下 |
| 后台 Filament 页面交互 | `SiteContactResourcePageTest.php` / `SiteNewsResourcePageTest.php` |

> **后台页面测试的关键手法**：官网插件的 Filament 资源路由在应用 boot 时注册，而插件启用状态来自 `plugins` 表，测试库那时还没数据，所以后台页默认渲染不出来。`SiteContactResourcePageTest.php` 的做法是**手工把插件注册进面板 → 重跑 `vendor/filament/filament/routes/web.php` → 刷新路由名查找表**。#14/#17/#18 的后台交互照抄即可，不必留给手工点击。

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

**素材空缺**：18 个产品封面全部没有，`news/is-voice-control-useful` 也没有。原因见 §五。前台会走 `image-placeholder` 降级，不影响开发。

**工作树里有 5 个不属于本任务链的未跟踪文件**（`docs/cms/02-自身官网.md`、两个 `playwright.config*.cjs`、两个 `tests/e2e/uat-phase*.spec.cjs`）。**不要动、不要提交它们。**

---

# 一、任务一览

| 批次 | 内容 | 估时 | 阻塞于 |
|------|------|------|--------|
| **批次 2** | #13 区块前台渲染 + #14 页面编辑发布 | 7h | 无 ← **建议起点** |
| 批次 3 | #15 版本快照回滚 + #16 草稿预览授权 | 3.5h | 批次 2 |
| 批次 4 | #17 菜单管理 + #18 301 重定向 | 4.5h | 无（可与批次 2 并行） |
| 批次 5 | #19 三层角色 + #20 SEO 收口 | 2h | 批次 2、4 |
| 批次 6 | #21 阶段 2 测试与验收 | 2.5h | 批次 2–5 |
| 阶段 3 | #27 目录重构 | 10h | 批次 6 |
| 阶段 4 | #28 主题契约 + #29 缓存边界 + #30 v1.0.0 发布 | 14h | #30 阻塞于 #27–#29 |
| — | **合计** | **≈43.5h** | 按 4h/周约 11 周 |

另有 6 条暂不排期的缺口（§四）和 4 条**用户本人做**的手工项（§五），都不计入上面的工时。

```
批次 2 ──┬─→ #16 预览授权 ──┐
         └─→ #15 版本回滚 ──┤
                            ├─→ #21 阶段 2 验收 ─→ #27 目录重构 ─┐
批次 4 ──┬─→ #19 三层角色 ──┤                                    ├─→ #30 v1.0.0
         └────────────────┘                     #28 主题契约 ───┤
#20 SEO 收口 ───────────────┘                     #29 缓存边界 ───┘
```

**从批次 2 开始。** 它是关键路径的起点，而且做完 CMS 才第一次对使用者可见。

---

# 二、阶段 2 剩余（批次 2–6，≈19.5h）

## 批次 2 · CMS 第一次可用（≈7h）

**#13 与 #14 必须一起做，不要拆开交付。** #13 单做，区块只能靠 seeder 手写 JSON 才看得见；#14 单做，Builder 存下的数据前台不显示。

**背景**：`site_pages.blocks` 列（#11）与区块契约、注册表、7 个内置区块（#12）都已交付，但**至今没有任何出口**——既没有前台视图，也没有后台表单。数据底座建好了，上层管道一根没接。

7 个内置区块：`hero`、`rich-content`、`media-text`、`feature-grid`、`cta`、`faq`、`contact-form`，都在 `src/Cms/Blocks/`，每个提供 `key()` / `label()` / `schema()` / `view()` / `rules()` / `defaults()`。

### #13 区块前台渲染与安全过滤（3.5h）

**新建 `src/Cms/Rendering/BlockRenderer.php`**，`render(?array $blocks): HtmlString` 逐条处理 `[{type, data}, …]`：

1. `BlockRegistry::get($type)` 返回 null → **跳过并 `Log::warning`**，不抛异常。一个失效区块不能把整页打成 500（`BlockRegistry.php` 的注释已明确这个契约）。
2. `$block->withDefaults($data)` 补齐历史 payload 缺失字段。
3. `View::exists($block->view())` 为 false → 同样跳过并记日志。
4. `view($block->view(), ['data' => $payload, 'block' => $block])->render()` 拼接。

> **为什么是 PHP 渲染器而不是 Blade 分发器**：视图命名空间的主题优先级由 `registerThemeViews()` 控制，渲染器走 `view()` 天然吃到这套解析；而「跳过未知 key 并记日志」写在 PHP 里可单测，写在 Blade 里不能。

> **不建 shared 层区块视图**（与原规划的「缺失时回退 shared」不同）：违反 §0.3 第 1 条。缺视图就按第 3 步优雅降级。

**新建 `src/Cms/Rendering/BlockSanitizer.php`**，`sanitize(array $blocks): array`，保存侧调用（#14 挂在 `mutateFormDataBeforeSave`），对 `rich-content.content` 跑 `RichText::purify()`。两侧都过是为了让存量数据也被治理，只在渲染侧过则库里一直躺着未净化的内容。

**14 个区块视图**：`resources/views/themes/{decoration,tech-product}/blocks/{hero,rich-content,media-text,feature-grid,cta,faq,contact-form}.blade.php`。

安全要点：

- 除 `rich-content.content` 外**所有字段一律 `{{ }}` 转义**。`rich-content` 用 `{!! RichText::purify($data['content']) !!}` 并套 `.prose`（两套主题 CSS 里各有一份现成的）。
- `image` 字段存的是 FileUpload 的磁盘路径，渲染要 `Storage::disk($disk)->url()`；空图走 `image-placeholder`。
- **URL 字段加 scheme 白名单**。`cta_url` 现在的规则只校验长度（`HeroBlock.php` 的 `rules()`），能塞进 `javascript:`。加 `protected function safeUrl(?string $url): ?string`，只放行 `/` 开头、`#` 开头、`http(s)://`、`tel:`、`mailto:`，其余返回 null，视图据此不渲染按钮。作者是可信管理员，但这是纵深防御，成本 10 行。
- `contact-form` 区块渲染 `@livewire('filamentboot-site::contact-form')`，`source` 用区块配的值（`ContactFormBlock` 的字符集规则已与 `ContactForm::normalizedSource()` 对齐）。

**FAQPage 结构化数据在这里做**（从 B1 移交过来，B1 的 Organization 与 BreadcrumbList 已交付）：`BlockRenderer::structuredData(?array $blocks): array` 扫出 `faq` 区块转成 FAQPage 节点，由 `SiteFrontController::page()` 并入 `$seoData['jsonLd']`（已支持节点列表）。`FaqBlock` 的答案本就存纯文本，直接用。

**接入**：`SiteFrontController::page()` 里 `$blocksHtml = app(BlockRenderer::class)->render($record->blocks)` 传给视图；`themes/*/pages/show.blade.php` 在富文本正文**之后**输出 `{!! $blocksHtml !!}`——正文与区块并存，不是二选一，存量页面全靠 `content_zh`。

**验收**：

```bash
# 先用 tinker 直接给某个已发布页面写 blocks JSON，或等 #14 做完从后台拖
curl -s localhost:8123/<slug> | grep -c 'FAQPage'          # 期望 1
curl -s localhost:8123/<slug> | grep -c '<script>alert'    # 期望 0
# 把某条 type 改成 'no-such-block'，页面仍 200 且 laravel.log 有 warning
```
单测覆盖：未知 key 被跳过、`safeUrl()` 的 scheme 白名单、两套主题 14 个视图都存在。

### #14 页面编辑与发布流转（3.5h）

落点：`src/Filament/Resources/SitePageResource.php` + `SitePageResource/Pages/{ListSitePages,CreateSitePage,EditSitePage}.php`。

**a. Builder 表单**——在「内容」Tab 的富文本之后加：

```php
Builder::make('blocks')->label('页面区块')->blocks(
    collect(app(BlockRegistry::class)->all())
        ->map(fn (BlockContract $b) => Builder\Block::make($b->key())
            ->label($b->label())
            ->schema($b->schema()))
        ->values()->all()
)->collapsible()->cloneable()->columnSpanFull(),
```

`Filament\Forms\Components\Builder` 存的正是 `[{type, data}]`，与区块契约天然一致，**不需要转换层**。`SitePage.blocks` 已 cast 为 `array`。

**b. `template` 选择**——`config/filamentboot-site.php` 新增 `page_templates` 映射（`'default' => '标准页面'` 起步），`Select` 从 config 取。控制器解析 `pages.templates.{template}`，`View::exists()` 为假回退 `pages.show`。落地页版式属阶段 4，这里只留口子。

**c. 状态流转**——**转移规则写在 `PageStatus` 枚举上**（`canTransitionTo(self $to): bool` / `allowedTransitions(): array`），不写在 Filament 里，这样状态机能脱离 Filament 单测。允许的边：

```
draft     → review | scheduled | published
review    → draft  | scheduled | published
scheduled → draft  | published
published → draft  | archived
archived  → draft
```

Action 放 `EditSitePage::getHeaderActions()`：`提交审核`、`发布`、`定时发布`（带 DateTimePicker）、`退回草稿`、`归档`。每个 Action 的 `visible()` 查 `canTransitionTo()`，**发布类 Action 额外 `authorize('publish_site_page')`**——编辑者只能提交审核，不能直接发布。

**d. 列表分 Tab**——`ListSitePages::getTabs()` 按 `PageStatus::cases()` 生成，各 Tab 带计数 badge，外加「全部」。

**e. 保存侧过滤**——`mutateFormDataBeforeSave()` 与 `mutateFormDataBeforeCreate()` 里跑 `BlockSanitizer::sanitize()`。

> ⚠️ **原规划有个依赖倒挂，这里纠正**：`publish_site_page` 等权限点原定在 #19 创建，而 #19 又标注阻塞于 #14——但 #14 的「编辑者只能提交审核」现在就需要它存在。**改为：权限点由第一个需要它的任务创建**（#14 加 `publish_site_page`，#17 加 `manage_site_menu`，#18 加 `manage_site_redirect`；`manage_site_settings` 已在 A3 时加进 `SitePermissionSeeder`），#19 退化为纯粹的三层角色组装。

**验收**：后台建页 → 拖 4 个区块 → 存草稿 → 提交审核 → 发布 → 前台可见。后台交互测试照 §0.6 的手法写，不要留给手工点击。

## 批次 3 · 编辑闭环（≈3.5h，阻塞于批次 2）

### #15 版本快照与回滚（2h）

**用 Observer 而非 Filament 钩子**——新建 `src/Observers/SitePageObserver.php`，在 `SiteServiceProvider::boot()` 注册。钩子只覆盖后台表单，Observer 连 seeder、tinker、未来的 API 一起覆盖。

- `saved` 时比对 `wasChanged(['title_zh','slug','template','content_zh','blocks','seo_title','seo_description','seo_keywords','seo_og_image','status','published_at'])`，有变化才写 `SitePageRevision`（模型已就绪：`payload` cast array、`UPDATED_AT = null`、有 `author()` 关联）。
- `created_by` = `auth('admin')->id()`，CLI 场景为 null，模型已允许。
- **保留上限** `config('filamentboot-site.revisions_keep', 50)`，写入后删超出的旧快照。不加上限，高频编辑的页面会把表撑爆。
- **回滚 = 用旧 payload `update()` 当前页面**，Observer 自然又写一条新快照。「回滚产生新版本而非删除历史」这条要求因此是免费的。
- ⚠️ **回滚不恢复 `status`**，只恢复内容字段。回滚一篇已归档页的旧版本不应把它偷偷重新发布。

UI 用 `SitePageResource\RelationManagers\RevisionsRelationManager`：列时间 / 操作人 / 变更字段摘要，两个 Action——`查看`（Modal 展示字段级新旧对比，`blocks` 只显示区块 type 序列，**不做全文 diff**，那是过度设计）、`回滚`（`requiresConfirmation()`）。

**验收**：改标题保存 3 次 → 版本列表 3 条 → 回滚第 1 条 → 变成 4 条、内容为第 1 版、`status` 未变。

### #16 草稿预览授权（1.5h）

- 路由 `Route::get('/preview/{page}', 'preview')->name('site.page.preview')`，注册在 `/{slug}` **之前**。`preview` 已在 `reserved_slugs` 里，不用改配置。
- **双通道**：签名有效 **或** 已登录管理员且 `can('view', $page)`。只挂 `signed` 中间件会把已登录管理员挡在门外，所以签名校验在控制器里手工做（`URL::hasValidSignature($request)`），两条通道都不满足 → `abort(403)`。
- 预览响应加 **`X-Robots-Tag: noindex, nofollow`**。签名 URL 泄漏后被收录，等于草稿进了搜索结果。
- 后台入口：`EditSitePage` 加 `预览` Header Action，`URL::temporarySignedRoute(..., now()->addMinutes(15), ...)` + `openUrlInNewTab()`。
- 控制器**不走 `scopePublished()`**（这是它存在的理由），但保留软删除作用域——已删除的页面不该能预览。

**验收**：

```bash
curl -sI localhost:8123/preview/1 | grep -i 'x-robots-tag'   # noindex
# 未登录 + 无签名 → 403；签名过期（用 travel()）→ 403；已登录管理员直接访问 → 200
```

## 批次 4 · 站点结构（≈4.5h，无阻塞，可与批次 2 并行）

### #17 菜单管理与前台接入（3h）

**后台**：`src/Filament/Resources/SiteMenuResource.php`（菜单本体，`main` / `footer` 两条）+ 菜单项树形页。树形操作照主包 `packages/filamentboot/src/Models/Menu.php` 与它的 Resource 抄——`SiteMenuItem` 已覆盖好 filament-tree 的三处约定（`determineOrderColumnName() => 'sort'`、`determineTitleColumnName() => 'label'`、`defaultParentKey() => 0`）。权限点 `manage_site_menu` 在本任务加进 `SitePermissionSeeder`。

菜单项四种 `type`：

| type | `target` 存什么 | 解析 |
|---|---|---|
| `page` | `SitePage` 的 **id** | `route('site.page', $page->slug)`；页面不存在或未发布则该项不渲染 |
| `route` | 命名路由（`site.cases.index` 等） | 白名单校验后 `route()` |
| `url` | 完整外链 | 走 #13 的 `safeUrl()` scheme 白名单 |
| `anchor` | `#section-id` | 原样 |

> **存 id 不存 slug**：slug 改了菜单不能断。#18 管的是外部链接，站内链接应当直接跟着走。

**前台**：新建 `src/Cms/Services/MenuResolver.php`，`resolve(string $key): ?array` 返回嵌套数组，**无数据时返回 null**：

```php
// themes/decoration/components/nav.blade.php 里现有的 $navLinks 硬编码数组
$navLinks = app(MenuResolver::class)->resolve('main') ?? [ /* 原数组原样保留 */ ];
```

四个文件同样处理：`themes/{decoration,tech-product}/components/{nav,footer}.blade.php`（footer 用 key `footer`）。**兜底数组留在各主题的 blade 里**——抽到 PHP 会把两个主题的导航结构焊死，违反 §0.3 第 1 条。

缓存 `Cache::rememberForever("site:menu:{$key}")`，`SiteMenu` / `SiteMenuItem` 的 `saved`/`deleted` 里 `forget`。菜单每页都读，不缓存等于全站每请求多两条查询。

**验收**：建 main 菜单 3 项 → 前台导航同步；删光菜单 → 回退到硬编码列表，**不白屏**（这是升级安全的硬要求）。

### #18 301 重定向（1.5h）

**方案已定：全局中间件 + 挂载路径早退。** 旧 URL 已经 404，路由中间件跑不到，必须选一层拦截：

| 方案 | 结论 |
|---|---|
| `Route::fallback()` | ❌ Laravel 只认一个 fallback，包一注册就顶掉宿主自己的 404 处理，对要发 Packagist 的包是硬伤 |
| 接管 404 异常渲染 | ❌ 配置在宿主的 `bootstrap/app.php`，包无法自己挂钩，要求下游手工加一行，违反「composer require 即可用」 |
| **全局中间件** | ✅ 采用 |

新建 `src/Http/Middleware/SiteRedirectMiddleware.php`，由 `SiteServiceProvider` 在**插件启用时**通过 `app(Kernel::class)->pushMiddleware()` 注册。第一件事是早退：

```php
// 请求路径不落在官网挂载范围（prefix / root / domain）内直接放行，
// 宿主自己的路由不为此付出一次 DB 查询。
if (! $this->isSitePath($request)) { return $next($request); }
```

其余：查 `site_redirects.from_path`（已有 unique 索引）命中就 `redirect($to, $statusCode)`；`hits` 用 `DB::table()->increment()` 单条 UPDATE 不走模型（省一次 SELECT 和全部模型事件）；`from_path` 入库与查询都归一（去前后斜杠、去查询串），避免 `/old` 与 `old/` 对不上；`to_path == from_path` 时不建不跳。

**`SiteRedirectResource`**：CRUD + `hits` 只读列（可排序，一眼看出哪条旧链接还有人在走）。权限点 `manage_site_redirect` 在本任务加进 `SitePermissionSeeder`。

**slug 变更自动建重定向**——原规划写的是「弹出确认」，**改为自动创建 + 通知里给撤销按钮**：`EditSitePage` 在 `mutateFormDataBeforeSave()` 记下旧 slug，`afterSave()` 里 slug 变了就建 301，并 `Notification::make()->body('已创建 /old → /new 的 301 跳转')->actions([Action::make('撤销')…])`。默认永不丢旧 URL，比默认弹窗少一次点击、少一次误关。

**验收**：

```bash
curl -sI localhost:8123/old-slug | head -1     # HTTP/1.1 301
curl -s  localhost:8123/                       # 宿主与官网其它路由不受影响
# 后台改某页 slug → 重定向列表出现对应 301 → 旧 URL 可跳且 hits 递增
```

## 批次 5 · 权限与 SEO 收口（≈2h，阻塞于批次 2、4）

### #19 三层角色（1h）

权限点此时已由批次 2/4 创建完毕，本任务只做三件事：

1. `SitePagePolicy::publish()` 等方法覆写，把 `publish_site_page` 接进 Filament Action 的 `authorize()`。`BasePolicy` 从短类名推导前缀，覆写只需加方法不需改前缀。
2. 新建 `SiteRoleSeeder`：

   | 角色 | 权限 |
   |---|---|
   | 内容编辑 | 五类内容 `view_any/view/create/update` + 媒体，**无** `publish_site_page` |
   | 内容发布 | 内容编辑全部 + `publish_site_page` + `delete_*` + 版本回滚 |
   | 站点管理 | 内容发布全部 + `manage_site_settings` / `manage_site_menu` / `manage_site_redirect` + 询盘查看与导出 |

3. 注册进 `composer.json` 的 `post_install.seeders`（`SitePermissionSeeder` 旁边），并在 `packages/filamentboot-site/README.md` 补一张三层权限表。

超管沿用主包 `Gate::before()`，不动。

### #20 SEO 收口（1h）

**页面级 `seo_og_image` 现在被完全忽略**：`SiteFrontController::buildSeo()` 只在 `method_exists($record, 'ogImageUrl')` 时取封面，而 `SitePage` 不是 media-library 模型没有这个方法，于是后台「SEO」Tab 里填的「社交分享图 URL」从来没进过 `og:image`。**修**：回退链最前面加 `$record->seo_og_image`。

canonical 复核清单：

- `/news/archive/2026/08` 的 canonical 应自指，不指 `/news`
- `/news?category=xxx` 的 `category` 参数应保留（它区分内容），确认不在 `canonical_ignored_params` 里
- 预览页不出 canonical（已有 noindex，再出 canonical 是矛盾信号）

## 批次 6 · #21 阶段 2 测试与验收（≈2.5h）

- **Unit**：`PageStatus::canTransitionTo()` 全矩阵、`BlockRenderer` 跳过未知 key、`safeUrl()` scheme 白名单、区块 payload 校验。
- **Feature**：签名预览过期 403、未授权预览 403、菜单权限、重定向命中与 hits 累加、区块内 `<script>` 被剥离、版本回滚不改 `status`。四态可见性已有 `SitePageStatusTest` 覆盖。
- **E2E**（`tests/e2e/`）：页面编辑 → 拖区块 → 提交审核 → 发布 → 前台可见；菜单改动后前台导航同步。**两套主题各跑一遍。**
- 跑通 §0.2 的全部命令。
- 同步 README 的「N 张内容表」计数（见 §0.2 的提醒）。
- **把 #13–#21 从本文件挪进 [已完成 tasks](已完成tasks.md)**，连同落点与开工后才确定的细节。

---

# 三、阶段 3 与阶段 4

## #27 目录重构（≈10h，阻塞于批次 6）

```
src/Cms/{Blocks,Filament,Models,Rendering,Routing,Services,Themes}/
src/Modules/Corporate/{Cases,Products,Solutions}/
```

批次 2–4 新建的 `src/Cms/Rendering/`、`src/Cms/Services/` 已在目标位置，不用搬。`src/Modules/News/` 当初就是按这个目标路径建的，也不用搬。

- ⚠️ **只移动不改名。** `BasePolicy` 从**短类名**推导权限点，`SiteCase` → `CorporateCase` 会静默改掉 `view_any_site_case` 并让现有角色权限全部失效。确需改名必须在 Policy 显式覆盖权限前缀。
- 首页聚合从 `SiteFrontController::home()` 抽成模块提供的 `HomeSectionProvider`，通用控制器不再硬编码 `SiteCase::featured()`。
- 路由 URL 与数据表名**全部不变**。
- **顺带删 `site_pages.is_published` 旧列**：它由 `SitePage::booted()` 的 saving 钩子镜像维护，原定「随包重命名一起删」，改名取消后锚点没了，挪到这里的破坏性变更批次。删列时同步删钩子与 `casts` 里的 `'is_published' => 'boolean'`。
- 改完必须同步：`composer.json` 的 PSR-4 autoload、`SitePlugin` 的 Resource 注册、`SiteServiceProvider` 的 Policy / Observer / 迁移路径注册、所有 `use` 语句。`composer dump-autoload` 后跑全量测试。

## #28 主题契约与切换预检查（≈5h）

`ThemeContract` + 每主题 `theme.php` manifest（声明支持的 template 与 block key）。切换主题前校验已发布页面用到的 template/block 是否被目标主题支持，不支持则列出受影响页面并要求确认。

批次 2 的 `BlockRenderer` 已做了运行时兜底（缺视图跳过 + 记日志），这里补的是**切换前的预检查**。

## #29 缓存边界（≈5h）

面板骨架改纯 Alpine，Livewire 组件用 `<template x-if>` 延迟挂载，公开页响应头改 `public, max-age=…`。

当前每个公开页都无条件 `@include` 含 `@livewire` 的 `floating-contact`，导致会话 Cookie + `Cache-Control: private, no-store`。**批次 2 的 `contact-form` 区块与已交付的移动端操作条都会加重这一点，做 #29 时要把这三处一起改。**

## #30 v1.0.0 发布验证（≈4h，阻塞于 #27–#29）

干净的独立 Laravel 13 项目 `composer require` 验证，走一遍安装 → 迁移 → seed → 登录 → 建页面 → 前台可见。

---

# 四、暂不排期，但已识别的缺口

以下功能确认缺失，因依赖阶段 2/4 的能力或工作量较大，不进上面的批次。**排期前先确认前置是否已就位。**

| 缺口 | 归属 | 原因 |
|------|------|------|
| 站内搜索（前台跨模块） | 阶段 4 后 | 依赖阶段 2 的内容模型定型 |
| 相关内容推荐（详情页底部） | 阶段 4 后 | 资讯详情页已有同分类 `$related` 兜底，案例/方案/产品还没有 |
| 落地页极简版式（无导航干扰、单一转化目标） | 阶段 4 | 依赖 #28 主题契约；#14 的 `template` 字段已留好口子 |
| 资料索取 / gated content（手册换联系方式） | 阶段 4 后 | 依赖 A1 的线索链路（已就位） |
| 在线客服脚本位、联系页地图嵌入 | 阶段 4 后 | A3 的注入位已做完，成本很低 |
| 表单字段可配置（不同活动问不同问题） | 阶段 4 后 | 依赖阶段 2 的区块 Schema 机制 |

---

# 五、用户本人做的四条（不要代劳）

| # | 内容 | 说明 |
|---|---|---|
| #31 | 隐私政策页补访客数据收集范围 | **优先级比看起来高。** A1 已在收集 source / landing_url / referer / UTM 五项，而页脚隐私链接读 `SiteSettings.privacy_url`，未配置时整个链接不渲染——也就是说线上目前**没有隐私政策入口**，数据却已经在收了。 |
| #32 | 生产收尾 | qkznj.com 后台填电话 / 地址 / ICP 备案号 / 隐私链接 / 默认 SEO 标题与描述 / OG 图 / logo / 微信二维码，直到设置页健康检查无告警。部署后 `php artisan view:clear && config:clear`、`npm run build`，确认生产 `.env` 有 `SITE_ROUTE_MODE=root`。 |
| #10 | 手动验收 | 双主题手点 + Playwright。移动端操作条与微信弹层只有单元级断言，没在真机视口跑过。 |
| #11 | 产品封面图 | 18 张产品图空缺，等品牌方渠道商素材包。CC0 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。详见 [cc0-assets](cc0-assets/README.md)。 |

> 素材使用边界（用户已定）：✅ 品牌官方产品图、参数/型号/价格带、详情页版式结构；❌ 店铺自制促销长图（带旗舰店角标，放自己站上穿帮）、买家秀（真实用户自家环境，含人脸，属个人信息）。抓来的文案只做结构参考 + 批量改写，**不原样入库**。
