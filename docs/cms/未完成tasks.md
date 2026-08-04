# 官网 CMS 未完成 tasks

> 文档定位：**只列还没做的**，含依赖、估时、落点文件与关键取舍，开工前从这里挑任务。
>
> 已交付的见 [已完成 tasks](已完成tasks.md)，那份逐项记了落点与实现细节，排查时查它。
>
> 更新时间：2026-08-04（B/C 组交付后）
>
> 上游规划：[基于装修网站官网优化 CMS](基于装修网站官网优化cms.md)

---

## 一览

| 批次 | 内容 | 估时 | 阻塞 |
|------|------|------|------|
| 批次 2 | #13 区块前台渲染 + #14 页面编辑发布 | 7h | 无 |
| 批次 3 | #15 版本快照回滚 + #16 草稿预览授权 | 3.5h | 批次 2 |
| 批次 4 | #17 菜单管理 + #18 301 重定向 | 4.5h | 无 |
| 批次 5 | #19 三层角色 + #20 SEO 收口 | 2h | 批次 2、4 |
| 批次 6 | #21 阶段 2 测试与验收 | 2.5h | 批次 2–5 |
| 阶段 3 | #27 目录重构 | 10h | 批次 6 |
| 阶段 4 | #28 主题契约 + #29 缓存边界 + #30 v1.0.0 发布 | 14h | #30 阻塞于 #27–#29 |
| — | **合计** | **≈43.5h** | 按 4h/周约 11 周 |

另有 4 条**你自己做**的手工项，见文末。

### 依赖图

```
批次 2 ──┬─→ #16 预览授权 ──┐
         └─→ #15 版本回滚 ──┤
                            ├─→ #21 阶段 2 验收 ─→ #27 目录重构 ─┐
批次 4 ──┬─→ #19 三层角色 ──┤                                    ├─→ #30 v1.0.0
         └────────────────┘                     #28 主题契约 ───┤
#20 SEO 收口 ───────────────┘                     #29 缓存边界 ───┘
```

**起点是批次 2**，其余全部直接或间接排在它后面（除批次 4 无阻塞，可与批次 2 并行或穿插）。

---

## 批次 2 · CMS 第一次可用（≈7h）

**#13 与 #14 必须一起做。** #13 单做，区块只能靠 seeder 写 JSON 才看得见；#14 单做，Builder 存下的数据前台不显示。

背景：#11 建好了 `site_pages.blocks` 列，#12 建好了区块契约与 7 个内置区块（`hero`、`rich-content`、`media-text`、`feature-grid`、`cta`、`faq`、`contact-form`），但**至今没有任何出口**——既没有前台视图，也没有后台表单。数据底座建好了，上层管道一根没接。

### #13 区块前台渲染与安全过滤（3.5h）

新建 `src/Cms/Rendering/BlockRenderer.php`，`render(?array $blocks): HtmlString` 逐条处理 `[{type, data}, …]`：

1. `BlockRegistry::get($type)` 返回 null → **跳过并 `Log::warning`**，不抛异常。一个失效区块不能把整页打成 500。
2. `$block->withDefaults($data)` 补齐历史 payload 缺失字段。
3. `View::exists($block->view())` 为 false → 同样跳过并记日志。
4. 渲染并拼接。

> **为什么是 PHP 渲染器而不是 Blade 分发器**：视图命名空间的主题优先级由 `SiteServiceProvider::registerThemeViews()` 的 `replaceNamespace()` 控制，渲染器走 `view()` 天然吃到这套解析；而「跳过未知 key 并记日志」写在 PHP 里可单测，写在 Blade 里不能。

> **不建 shared 层区块视图**（与原规划的「缺失时回退 shared」不同）：双主题要保持完全独立、可单独删除。缺视图就按第 3 条优雅降级。

同时新建 `src/Cms/Rendering/BlockSanitizer.php`，保存侧调用，对 `rich-content.content` 跑 `RichText::purify()`。两侧都过是为了让存量数据也被治理。

**14 个区块视图**（7 × 2 主题）：`themes/{decoration,tech-product}/blocks/*.blade.php`。

安全要点：

- 除 `rich-content.content` 外**所有字段一律 `{{ }}` 转义**。
- `image` 字段存的是 FileUpload 磁盘路径，渲染走 `Storage::disk()->url()`；空图走现有 `image-placeholder` 降级。
- **URL 字段加 scheme 白名单**。`cta_url` 现在的规则只校验长度，能塞进 `javascript:`。加 `safeUrl()` 只放行 `/`、`#`、`http(s)://`、`tel:`、`mailto:`。作者是可信管理员，但这是纵深防御，成本 10 行。

**FAQPage 结构化数据在这里做**（从 B1 挪过来）：`BlockRenderer::structuredData()` 扫出 `faq` 区块转成 FAQPage 节点，并入 `$seoData['jsonLd']`（已支持节点列表）。`FaqBlock` 的答案本就存纯文本。

### #14 页面编辑与发布流转（3.5h）

落点 `SitePageResource` + 三个 Pages。

- **Builder 表单**：从 `BlockRegistry` 生成 `Builder\Block`。Filament 的 `Builder` 存的正是 `[{type, data}]`，与区块契约天然一致，不需要转换层。
- **`template` 选择**：config 新增 `page_templates` 映射，控制器解析 `pages.templates.{template}`，`View::exists()` 为假回退 `pages.show`。落地页版式属阶段 4，这里只留口子。
- **状态流转**：转移规则写在 `PageStatus` 枚举上（`canTransitionTo()`），不写在 Filament 里，这样状态机能脱离 Filament 单测。Action 放 `EditSitePage::getHeaderActions()`，发布类 Action 额外 `authorize('publish_site_page')`。
- **列表分 Tab**：按 `PageStatus::cases()` 生成，带计数 badge。
- **保存侧过滤**：`mutateFormDataBeforeSave/Create` 里跑 `BlockSanitizer`。

> ⚠️ **原文档有个依赖倒挂，本轮纠正**：`publish_site_page` 等权限点原定在 #19 创建，而 #19 又标注阻塞于 #14——但 #14 的「编辑者只能提交审核」现在就需要它存在。**改为：权限点由第一个需要它的任务创建**（#14 加 `publish_site_page`，#17 加 `manage_site_menu`，#18 加 `manage_site_redirect`；`manage_site_settings` 已在 A3 时加进 `SitePermissionSeeder`），#19 退化为纯粹的三层角色组装。

**验收**：后台建页 → 拖 4 个区块 → 存草稿 → 提交审核 → 发布；页面含 `FAQPage`、不含 `<script>alert`；手工把某条 type 改成不存在的值，页面仍 200 且日志有 warning。

---

## 批次 3 · 编辑闭环（≈3.5h）

### #15 版本快照与回滚（2h）

**用 Observer 而非 Filament 钩子**——钩子只覆盖后台表单，Observer 连 seeder、tinker、未来的 API 一起覆盖。

- `saved` 时比对内容相关列有无变化，有才写 `SitePageRevision`（模型已就绪）。
- 保留上限 `config('filamentboot-site.revisions_keep', 50)`，不加上限高频编辑的页面会把表撑爆。
- **回滚 = 用旧 payload update 当前页面**，Observer 自然又写一条新快照，「回滚产生新版本而非删除历史」这条要求因此是免费的。
- ⚠️ **回滚不恢复 `status`**，只恢复内容字段。回滚一篇已归档页的旧版本不应把它偷偷重新发布。

UI 用 RelationManager，`查看`（字段级新旧对比，`blocks` 只显示 type 序列，不做全文 diff）+ `回滚`（需确认）。

### #16 草稿预览授权（1.5h）

- 路由 `/preview/{page}` 注册在 `/{slug}` **之前**。`preview` 已在 `reserved_slugs` 里，不用改配置。
- **双通道**：签名有效 **或** 已登录管理员且 `can('view', $page)`。只挂 `signed` 中间件会把已登录管理员挡在门外，所以签名校验在控制器里手工做，两条通道都不满足 → 403。
- 预览响应加 **`X-Robots-Tag: noindex, nofollow`**。签名 URL 泄漏后被收录，等于草稿进了搜索结果。
- 控制器**不走 `scopePublished()`**（这是它存在的理由），但保留软删除作用域——已删除的页面不该能预览。

---

## 批次 4 · 站点结构（≈4.5h，无阻塞，可与批次 2 并行）

### #17 菜单管理与前台接入（3h）

后台 `SiteMenuResource`（`main` / `footer` 两条菜单）+ 菜单项树形页，照主包 `Menu` 那套抄，`SiteMenuItem` 已覆盖好 filament-tree 的三处约定。

菜单项四种类型：

| type | `target` 存什么 | 解析 |
|---|---|---|
| `page` | `SitePage` 的 **id** | 页面不存在/未发布则该项不渲染 |
| `route` | 命名路由 | 白名单校验后 `route()` |
| `url` | 完整外链 | 走 #13 的 `safeUrl()` scheme 白名单 |
| `anchor` | `#section-id` | 原样 |

> **存 id 不存 slug**：slug 改了菜单不能断。#18 管的是外部链接，站内链接应当直接跟着走。

前台新建 `MenuResolver::resolve(string $key): ?array`，**无数据时返回 null**，两套主题的 nav/footer 各自 `?? [现有硬编码数组]` 兜底。兜底数组留在各主题的 blade 里——抽到 PHP 会把两个主题的导航结构焊死。

缓存 `Cache::rememberForever("site:menu:{$key}")`，模型 saved/deleted 时 forget。菜单每页都读，不缓存等于全站每请求多两条查询。

### #18 301 重定向（1.5h）

**已定方案：全局中间件 + 挂载路径早退。** 旧 URL 已经 404，路由中间件跑不到，必须选一层：

- ~~`Route::fallback()`~~ 会顶掉宿主自己的 404 处理，对要发 Packagist 的包是硬伤。
- ~~接管 404 异常渲染~~ 需要宿主手工改 `bootstrap/app.php`，违反「composer require 即可用」。
- ✅ **全局中间件**，第一件事就是判断请求路径是否落在官网挂载范围（prefix / root / domain）内，不在就直接放行，宿主路由零 DB 查询成本。

其余：`from_path` 归一（去前后斜杠、去查询串）；`hits` 用 `DB::table()->increment()` 单条 UPDATE 不走模型；`to_path == from_path` 时不建不跳。

**slug 变更自动建重定向**——原文档写的是「弹出确认」，**改为自动创建 + 通知里给撤销按钮**。默认永不丢旧 URL，比默认弹窗少一次点击、少一次误关。

---

## 批次 5 · 权限与 SEO 收口（≈2h）

### #19 三层角色（1h）

权限点此时已由批次 2/4 创建完毕，本任务只做角色组装：

| 角色 | 权限 |
|---|---|
| 内容编辑 | 五类内容 `view_any/view/create/update` + 媒体，**无** `publish_site_page` |
| 内容发布 | 内容编辑全部 + `publish_site_page` + `delete_*` + 版本回滚 |
| 站点管理 | 内容发布全部 + `manage_site_settings` / `manage_site_menu` / `manage_site_redirect` / 询盘查看与导出 |

加 `SitePagePolicy::publish()` 覆写，新建 `SiteRoleSeeder` 并注册进 `composer.json` 的 `post_install.seeders`，README 补一张三层权限表。超管沿用主包 `Gate::before()`。

### #20 SEO 收口（1h）

**页面级 `seo_og_image` 现在被完全忽略**：`buildSeo()` 只在 `method_exists($record, 'ogImageUrl')` 时取封面，而 `SitePage` 不是 media-library 模型没有这个方法，于是后台填的「社交分享图 URL」从来没进过 `og:image`。修：回退链最前面加 `$record->seo_og_image`。

canonical 复核清单：归档页应自指、`?category=` 参数应保留、预览页不出 canonical（已有 noindex，再出 canonical 是矛盾信号）。

---

## 批次 6 · #21 阶段 2 测试与验收（≈2.5h）

- **Unit**：`PageStatus::canTransitionTo()` 全矩阵、`BlockRenderer` 跳过未知 key、`safeUrl()` scheme 白名单。
- **Feature**：签名预览过期 403、未授权预览 403、菜单权限、重定向命中与 hits 累加、区块内 `<script>` 被剥离、版本回滚不改 status。
- **E2E**：页面编辑 → 拖区块 → 提交审核 → 发布 → 前台可见；菜单改动后前台导航同步。**两套主题各跑一遍**。
- 同步 README 的「N 张内容表」计数（`SitePackageMetadataTest` 会断言它与迁移实际建表数一致）。
- 把 #13–#21 从本文件挪进 [已完成 tasks](已完成tasks.md)，连同落点与开工后才确定的细节。

---

## 阶段 3 · #27 目录重构（≈10h）

```
src/Cms/{Blocks,Filament,Models,Rendering,Routing,Services,Themes}/
src/Modules/Corporate/{Cases,Products,Solutions}/
```

批次 2–4 新建的 `src/Cms/Rendering/`、`src/Cms/Services/` 已在目标位置，不用搬。

- ⚠️ **只移动不改名**。`BasePolicy` 从**短类名**推导权限点，`SiteCase` → `CorporateCase` 会静默改掉 `view_any_site_case` 并让现有角色权限全部失效。确需改名必须在 Policy 显式覆盖前缀。
- 首页聚合从 `SiteFrontController::home()` 抽成模块提供的 `HomeSectionProvider`。
- 路由 URL 与数据表名**全部不变**。
- **顺带删 `site_pages.is_published` 旧列**：它由 `SitePage::booted()` 的 saving 钩子镜像维护，原定「随包重命名删」，改名取消后锚点没了，挪到这里的破坏性变更批次。删列时同步删钩子与 casts。
- 改完必须同步 `composer.json` 的 PSR-4、`SitePlugin` 的 Resource 注册、`SiteServiceProvider` 的 Policy/Observer/迁移路径、所有 `use` 语句。

---

## 阶段 4 · 主题契约 / 缓存 / 发布（≈14h）

### #28 主题契约与切换预检查（5h）

`ThemeContract` + 每主题 `theme.php` manifest（声明支持的 template 与 block key）。切换主题前校验已发布页面用到的 template/block 是否被目标主题支持，不支持则列出受影响页面并要求确认。

批次 2 的 `BlockRenderer` 已做了运行时兜底（缺视图跳过 + 记日志），这里补的是**切换前的预检查**。

### #29 缓存边界（5h）

面板骨架改纯 Alpine，Livewire 组件用 `<template x-if>` 延迟挂载，公开页响应头改 `public, max-age=…`。

当前每个公开页都无条件 `@include` 含 `@livewire` 的 floating-contact，导致会话 Cookie + `Cache-Control: private, no-store`。**批次 2 的 contact-form 区块与已交付的移动端操作条都会加重这一点，做 #29 时要把这三处一起改。**

### #30 v1.0.0 发布验证（4h）

干净的独立 Laravel 13 项目 `composer require` 验证：安装 → 迁移 → seed → 登录 → 建页面 → 前台可见。

---

## 暂不排期，但已识别的缺口

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

## 你自己做的四条

| # | 内容 | 说明 |
|---|---|---|
| #31 | 隐私政策页补访客数据收集范围 | **优先级比看起来高**。A1 已在收集 source / landing_url / referer / UTM 五项，而页脚隐私链接读 `SiteSettings.privacy_url`，未配置时整个链接不渲染——也就是说线上目前**没有隐私政策入口**，数据却已经在收了。建议尽快补。 |
| #32 | 生产收尾 | qkznj.com 后台填电话 / 地址 / ICP / 隐私链接 / 默认 SEO / OG 图 / logo / 微信二维码，直到设置页健康检查无告警。 |
| #10 | 手动验收 | 双主题手点 + Playwright。本轮新增的移动端操作条与微信弹层只有单元级断言，没在真机视口跑过。 |
| #11 | 产品封面图 | 18 张产品图空缺，等品牌方渠道商素材包。CC0 图库里没有对应 SKU 的白底图，硬凑等于挂着别人的产品当自己的。详见 [cc0-assets](cc0-assets/README.md)。 |

---

## 验收命令

```bash
composer test                              # 根项目全量（当前 561 通过 / 2026 断言）
composer pint:test
composer phpstan                           # 根项目（扫 app + database，0 告警）
cd packages/filamentboot && composer test  # 主包（83 通过）

# 站点包 level 6：当前 10 个存量告警，不应增加
vendor/bin/phpstan analyse --level=6 packages/filamentboot-site/src

# 站点包没有独立 vendor/，元数据测试直接指文件
vendor/bin/phpunit --bootstrap vendor/autoload.php --no-configuration \
  packages/filamentboot-site/tests/Unit/SitePackageMetadataTest.php
```

> 站点包 PHPStan 的存量告警是 **10 个**，不是老文档写的 6 个——多出的 4 个来自资讯模块（`NewsArticleResource::getEloquentQuery()`、`newsIndex` 的 `published()`、`newsArchiveMonths()` 返回类型、`?->name_zh`）。

本地 dev 用 `SITE_ROUTE_MODE=root`，前台路径无 `/site` 前缀；`php artisan serve --port=8123`。

---

## 全程不变的约束

- **双主题完全独立**：任何新增/修改的**视觉**视图与样式，在 `themes/decoration/` 与 `themes/tech-product/` 各存一份完整副本。纯数据/纯逻辑（PHP 服务、meta 输出）不受此限。客户装上后可能只保留一套，代码不能耦合。
- **富文本一律 `Support\RichText::purify()`**（T-10-05-01），不要退回 `app('purifier')->clean()`——那会退到 default 画像，把标题、引用、代码块、表格全静默剥掉。
- **草稿不得泄露前台**（T-10-04-04）；slug 走 Eloquent `where()` 参数绑定（T-10-04-03）。
- **公开页只用 Alpine，不新增 Livewire**（阶段 4 要整页缓存）。
- 中文输出与中文 PHPDoc；最小且简洁，不顺手重构；未经明确要求不 commit / push。
