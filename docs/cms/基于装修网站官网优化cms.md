# 基于装修网站官网优化 CMS

> 文档定位：`filamentboot/filamentboot-cms` 的产品边界、目标架构与实施基线。
>
> 更新时间：2026-08-03
>
> 文档状态：规划中
>
> 目标版本：v1.0.0

---

## 1. 决策摘要

Filamentboot 的官网/CMS 采用 **Blade-first 单体架构**：

- 前台使用 Laravel 路由、Controller 和 Blade 服务端渲染。
- 局部交互使用 Livewire，例如询盘、筛选、搜索和移动端导航。
- 后台使用 Filament 管理内容、媒体、菜单、设置和发布流程。
- Vite 只负责样式和少量前端脚本构建，不建设独立 Vue、React、Nuxt 前端项目。
- 不把前台渲染建立在公开 REST API 上。

本期的包名、目录、命名空间和插件入口统一从 `filamentboot-site` 重命名为 `filamentboot-cms`。目标包为：

```text
Composer 包：filamentboot/filamentboot-cms
仓库目录：packages/filamentboot-cms
插件入口：Filamentboot\FilamentbootCms\CmsPlugin
```

CMS 核心、企业站内容模块和官方主题均保留在这一个 Composer 包内，通过代码目录区分职责。**本期不拆分为多个 Composer 包。**

CMS v1 只支持中文内容。当前 `filamentboot-site` 已有的中英文数据字段、`/en` 路由和语言切换属于历史实现，不作为本期正式能力继续扩展。

## 2. 目标与边界

### 2.1 目标用户

| 用户 | 目标 |
|------|------|
| 独立开发者 | 在已有 Laravel + Filamentboot 项目中快速交付企业官网，不再另建前端工程。 |
| 外包团队 | 以包内主题和内容模块复用官网能力，保留项目级视觉与内容定制空间。 |
| 内容运营人员 | 在后台完成页面、菜单、图片、SEO、询盘和发布管理，不需要修改 Blade 文件。 |

### 2.2 v1 目标

- 安装一个 Composer 插件后获得可配置的官网前台和后台内容管理入口。
- 支持中文页面、菜单、内容区块、SEO、媒体、询盘、草稿、定时发布、预览和重定向。
- 支持一个完整可用的官方企业站主题。
- 支持项目通过发布主题视图和样式进行受控定制。
- 前台在插件禁用时不注册官网路由，不影响宿主已有业务。

### 2.3 非目标

- 不做 Headless CMS、公开内容 API 或独立 SPA 前端。
- 本期不集成多语言，不新增翻译表、语言切换、`/en` 路由或多语言后台表单。
- 不做可执行 PHP/Blade 代码的自由页面编辑器。
- 不做任意用户上传主题包并在生产环境执行的运行时主题市场。
- 不把电商、会员、支付、订单、博客、文档等所有内容域一次性内置到 CMS 核心。
- 不在 v1 实现无限自由拖拽排版；内容区块必须由主题和插件显式声明。

## 3. 当前状态与收口结论

### 3.1 已铺垫能力

当前实现来源为 `packages/filamentboot-site`，重命名完成前仍以该目录为准：

| 能力 | 当前状态 | 代码位置 |
|------|----------|----------|
| Filament 插件注册 | 已完成 | `src/SitePlugin.php` |
| 前台 Blade 路由与 Controller | 已完成 | `routes/site.php`、`src/Http/Controllers/SiteFrontController.php` |
| Livewire 询盘与案例筛选 | 已完成 | `src/Http/Livewire/` |
| 站点设置、SEO 默认值、Logo、主题选择 | 已完成 | `src/Settings/SiteSettings.php`、`src/Filament/Pages/SiteSettingsPage.php` |
| 案例、方案、产品、静态页、询盘 Resource | 已完成 | `src/Filament/Resources/` |
| 媒体库与富文本安全过滤 | 已铺垫 | 内容 Model、Filament Media Library、`mews/purifier` |
| 中英文前台路由 | 已完成，但不纳入 CMS v1 目标 | `routes/site.php`、`SetLocaleMiddleware` |
| `decoration` 主题 | 已铺垫且页面较完整 | `resources/views/themes/decoration/` |

### 3.2 当前不能标记为完成的部分

| 事项 | 当前问题 | 收口要求 |
|------|----------|----------|
| 包身份 | 目录、Composer 包名、命名空间、Plugin、Service Provider、缓存键和发布 tag 仍使用 `filamentboot-site`。 | 统一重命名为 `filamentboot-cms`，并完成已安装状态迁移。 |
| 通用 CMS | 内容模型固定为案例、方案、产品，尚无通用页面区块、菜单、预览、版本和重定向能力。 | 先完成 CMS 核心，再把企业站内容收敛为包内模块。 |
| 单语言边界 | 当前实现维护中英文字段、`/en` 路由和语言切换。 | CMS v1 仅保留中文内容流，不再新增或展示多语言入口。 |
| `tech-product` 主题 | 当前仅有首页与基础布局；控制器会请求案例、方案、产品和静态页视图。 | 完整实现全部页面，或在完成前从主题选择中隐藏。 |
| 主题覆盖 | Service Provider 发布主题文件，但运行时仅加载包内主题目录，宿主发布后的视图没有加入解析优先级。 | 实现“宿主覆盖 > 包内主题 > 核心兜底”的视图解析顺序，并补测试。 |
| 路由兼容 | 当前默认接管 `/`、`/{slug}` 及若干固定前缀，会与宿主前台路由冲突。 | 增加根路径、前缀、域名三种明确模式，默认不抢占宿主根路由。 |
| 安装元数据 | README 和 Composer 元数据引用 `filamentboot-site-config`，当前 Service Provider 未注册该发布 tag。README 还写“8 张内容表”和 `SiteSeeder`，实际有 9 张内容表且当前 Seeder 为 `SiteDemoSeeder`。 | 重命名时统一为 `filamentboot-cms-*`，并修正文档、发布 tag、Composer `post_install` 与真实类名，加入安装烟雾测试。 |

### 3.3 当前定位

在 CMS 核心完成前，对外只能保守描述为：

> 已铺垫的企业官网解决方案，支持装修/智能家居场景的案例、方案、产品、页面与询盘管理；通用 CMS 核心和 `filamentboot-cms` 包身份仍待收口。

不得对外描述为“完整通用 CMS”“多主题官网系统”或“开箱即用主题定制平台”。

### 3.4 线上方案页审查（2026-08-03）

审查入口：`https://www.qkznj.com/solutions`。已用桌面和 `390 x 844` 移动视口检查页面，并检查首页、方案详情、产品、案例、关于、联系和英文方案页的公开响应。

已确认官网主体页面可以访问，深色 `decoration` 主题的桌面和移动端基础布局、方案列表、详情页、案例筛选和悬浮询盘表单均已具备。以下问题是线上观察结果，按优先级纳入 CMS 规划。

| 优先级 | 已验证问题 | 线上表现 | CMS/前台收口要求 |
|--------|------------|----------|------------------|
| P0 | 主导航咨询 CTA 失效 | 桌面导航和移动菜单的“预约咨询”按钮只移除不存在的 `hidden` class；面板实际由 Alpine `contactPanelOpen` 控制。浏览器验证：主导航 CTA 点击后面板不可见，悬浮按钮可正常打开。 | 所有 CTA 统一调用一个可测试的打开动作；为桌面导航、移动菜单、列表页和详情页分别加入浏览器回归测试。 |
| P0 | 方案与产品图片仍是外部占位资源 | 列表和详情页使用 `picsum.photos`。桌面及移动截图中图片持续模糊或出现低质量占位，无法展示真实智能家居方案。 | 上线内容禁止使用外部占位图；封面、图集和 Open Graph 图统一接入 Media Library、本地或已配置云存储，并生成列表与详情所需变体。 |
| P0 | SEO 基础资源不完整 | 首页、案例、方案、产品列表的 `meta description` 为空；默认 `og:image` 指向的 `/img/og-default.jpg` 返回 404；`/sitemap.xml` 返回 404。 | 站点发布前校验默认 SEO、页面 SEO、OG 图、Canonical、`robots.txt` 和站点地图；缺失时阻止发布或在后台明确告警。 |
| P0 | 全局联系方式未配置 | 页脚渲染“联系我们”标题但没有电话、地址、二维码或有效联系入口；页面文案仍含示例号码 `027-88888888`。 | 将公司名称、电话、地址、二维码、备案号和隐私链接列为站点发布前必填项；未配置时不渲染空栏目。 |
| P1 | 公开页面无法缓存 | 方案页响应包含会话/XSRF Cookie，且使用 `Cache-Control: private, no-store`；每个公开页面都内嵌 Livewire 询盘组件。 | 公开内容页默认可缓存；询盘改为按需挂载、独立轻量表单或仅在打开面板后加载，避免会话和 Livewire 快照阻断 CDN/浏览器缓存。 |
| P1 | 方案详情信息密度不足 | 方案列表只有预算、短描述和详情链接；详情页缺少适用空间、交付清单、核心设备、服务流程、关联案例、常见问题和针对该方案的 CTA。 | 在 `Modules/Corporate` 中补充结构化方案字段与区块：适用场景、预算说明、交付范围、产品关联、案例关联、流程、FAQ 和咨询 CTA。 |
| P1 | 移动端转化与可读性需收口 | 单列卡片布局正常，但固定悬浮询盘按钮会贴近或遮挡卡片区域；深色背景上的次级文字与价格标签对比度偏低。 | 为固定 CTA 预留安全间距和滚动避让；复核 WCAG 对比度、卡片文字字号、按钮点击区和小屏截图。 |
| P2 | 多语言仍在线上暴露 | 页面同时输出语言切换、`/en` 路由和 `hreflang="zh"`；这与 CMS v1 的中文单语言决策不一致。 | P0 完成中文单语言迁移后，移除语言切换与英文路由，或在未来单独评审多语言版本。 |

## 4. 目标架构

### 4.1 单包目录边界

v1 使用一个 Composer 包交付，不拆分 CMS 核心、企业站内容模块或主题包：

```text
packages/filamentboot-cms/
├── src/
│   ├── Cms/                    # 通用 CMS 核心
│   │   ├── Blocks/
│   │   ├── Filament/
│   │   ├── Models/
│   │   ├── Routing/
│   │   ├── Services/
│   │   └── Themes/
│   ├── Modules/
│   │   └── Corporate/          # 企业站内容模块
│   │       ├── Cases/
│   │       ├── Products/
│   │       └── Solutions/
│   ├── Http/
│   ├── CmsPlugin.php
│   └── CmsServiceProvider.php
├── config/
│   └── filamentboot-cms.php
├── resources/
│   ├── css/
│   └── views/
│       └── themes/
└── database/
```

`Cms/`、`Modules/Corporate/` 和 `resources/views/themes/` 都是包内实现，不构成独立安装单元。后续增加行业方案时，优先继续在包内按模块隔离；只有存在明确的版本独立、依赖独立和发布独立需求时才重新评审拆包。

### 4.2 CMS 核心数据模型

CMS v1 为中文单语言，不新增翻译表。现有 `_zh`、`_en` 字段保留以兼容现有数据，但不再用于新增通用页面。

通用页面优先演进既有 `site_pages` 表和 `SitePage` 模型，避免并行创建第二套页面表。迁移必须先将现有中文字段转换到新字段，再逐步清理旧后台表单和前台语言分支。

| 表/模型 | 关键字段 | 职责 |
|---------|----------|------|
| `site_pages` | `id`、`slug`、`template`、`title`、`blocks`、`seo_*`、`status`、`published_at`、`sort` | 中文页面内容、路由和发布状态。 |
| `site_page_revisions` | `page_id`、`payload`、`created_by` | 草稿保存点、回滚和审核追溯。 |
| `site_menus` | `key`、`name` | 例如主导航、页脚导航。 |
| `site_menu_items` | `menu_id`、`parent_id`、`type`、`target`、`sort` | 页面链接、外链、锚点和层级导航。 |
| `site_redirects` | `from_path`、`to_path`、`status_code` | 旧链接迁移和 SEO 301 重定向。 |
| `site_contact_messages` | 保留现有模型并规范化状态流转 | 询盘数据与后台跟进。 |

页面状态统一为：

```text
draft -> review -> scheduled -> published -> archived
```

- `draft`、`review`、`scheduled` 内容不得出现在公开查询中。
- `published_at` 到期后才允许公开访问。
- 预览必须需要后台会话或短期签名 URL，不能通过猜测的公开参数访问草稿。

### 4.3 内容区块与模板契约

页面正文存储为受控 JSON 区块，后台通过 Filament Builder 编辑。每个区块必须同时提供：

- 稳定的区块键，例如 `hero`、`rich-content`、`feature-grid`、`cta`、`faq`。
- 后台表单 Schema。
- 前台 Blade 渲染器。
- 输入验证和默认值。
- 必需媒体字段及 alt 文本规则。

主题通过 `ThemeContract` 或 manifest 声明：

```php
return [
    'id' => 'corporate-light',
    'templates' => ['default', 'landing'],
    'blocks' => ['hero', 'rich-content', 'feature-grid', 'cta', 'faq'],
    'assets' => ['css' => ['...'], 'js' => []],
];
```

主题切换前必须校验：当前已发布页面使用的模板和区块是否被新主题支持。不支持时阻止切换，并列出受影响页面。

### 4.4 前台路由与主题解析

官网路由必须由配置显式决定：

| 模式 | 用途 | 示例 |
|------|------|------|
| `prefix` | 宿主已有前台业务时的默认安全模式 | `/site/about` |
| `root` | 项目本身就是官网时启用 | `/about` |
| `domain` | 官网使用独立域名或子域名 | `www.example.com/about` |

固定系统路径优先于页面 slug：`sitemap.xml`、`robots.txt`、`contact`、`preview` 等不得被动态页面路由吞掉。根路径模式下还必须提供保留 slug 配置。

主题视图解析顺序固定为：

```text
宿主 resources/views/vendor/filamentboot-cms/themes/{theme}
    -> 包内 resources/views/themes/{theme}
    -> CMS 核心默认主题
```

主题、站点设置和插件启停状态变更后，必须统一清除对应缓存；不能依赖长时间缓存让前台在启停后保持旧状态。

### 4.5 权限与安全

| 角色 | 权限边界 |
|------|----------|
| 内容编辑 | 编辑草稿、上传媒体，不得发布或修改全局设置。 |
| 内容审核/发布 | 审核、发布、定时发布、回滚版本。 |
| 站点管理员 | 管理设置、主题、菜单、重定向和询盘。 |
| 超级管理员 | 沿用主包 `Gate::before()` 绕过机制。 |

安全要求：

- 富文本统一经过 HTML 白名单过滤，禁止在内容字段中执行 Blade、PHP 或任意脚本。
- 询盘保留频率限制、CSRF、防刷与 PII 权限控制。
- 前台只查询已发布内容，所有动态 slug 使用参数绑定。
- 媒体上传统一使用主包上传配置；图片应有 alt 文本、合理尺寸与封面比例约束。
- 主题只能来自 Composer 已安装包或宿主受控发布目录，不支持后台上传并执行任意主题代码。

## 5. 功能范围

### 5.1 CMS 核心 v1

- 站点基础设置：品牌、联系方式、备案、默认 SEO、社交链接、默认主题。
- 页面管理：中文页面、草稿、审核、定时发布、预览、回滚、软删除。
- 内容区块：至少 Hero、富文本、图片图文、卡片列表、CTA、FAQ、联系表单。
- 菜单管理：多菜单、嵌套、页面链接、外链、排序和新窗口。
- SEO：标题、描述、Canonical、Open Graph、`sitemap.xml`、`robots.txt`、301 重定向。
- 媒体：复用 Media Library，补齐 alt 文本、封面、图集和内容图片规范。
- 询盘：复用现有表单与状态流转，补齐通知、来源页和隐私保留策略。

### 5.2 企业站内容模块

以下能力保留为包内 `Modules/Corporate`，不进入 CMS 核心：

- 案例、案例分类、标签、装修风格、户型。
- 产品、产品分类、品牌、价格。
- 解决方案、价格区间。
- 针对企业站首页的精选内容聚合。

### 5.3 后续可选模块

- 新闻/文章。
- 下载中心。
- 文档中心。
- 招聘职位。
- 多站点和多域名。

这些模块必须依赖 CMS 核心的页面、媒体、SEO、菜单和主题契约，不能各自复制一套前台路由与主题逻辑。

## 6. 实施阶段

### P0：重命名与现有企业站方案收口

目标：`filamentboot/filamentboot-cms` 可真实安装、启用、禁用并完整展示一个企业站主题。

- 将目录、Composer 包名、PSR-4 命名空间、`SitePlugin`、`SiteServiceProvider`、配置文件、缓存键、数据库插件 slug 和发布 tag 从 `filamentboot-site` 统一重命名为 `filamentboot-cms`。
- 数据表 `site_*` 保持现有名称，包重命名不进行无收益的全库改表。
- 若旧包名已对外发布，提供迁移说明与一次主版本升级；若尚未对外稳定发布，直接完成重命名，不保留双包并存。
- 修复发布 tag、Seeder、迁移数量和 README/Composer 元数据不一致问题。
- 修复所有主导航、移动导航和内容页的“预约咨询”CTA，使其与悬浮询盘按钮共用同一个可测试的打开逻辑。
- 用 Media Library 中的真实封面、图集和 Open Graph 图替换全部 `picsum.photos` 占位资源；禁止生产内容继续依赖图片占位服务。
- 补齐默认 SEO、列表页 SEO、OG 图、站点地图和发布前 SEO 健康检查。
- 补齐站点联系方式、备案、隐私链接和询盘说明；未配置的全局栏目不渲染空白区。
- 增加路由模式配置，默认不抢占宿主根路径。
- 修复宿主发布主题的解析优先级。
- 完成 `tech-product` 全部页面，或在完成前禁止选择。
- 移除或关闭 `/en` 前台路由、语言切换组件和双语设置入口；CMS v1 只保留中文内容流。
- 统一插件启停、主题切换、站点设置保存后的缓存失效。
- 验收安装、迁移、初始化、启用、前台访问、禁用恢复宿主路由的完整流程。

### P1：CMS 核心

目标：让不属于案例、产品、方案的官网页面可由运营人员独立维护。

- 演进 Page、Revision、Menu、Redirect 数据模型和迁移。
- 实现后台页面编辑、草稿、审核、发布、定时发布、预览和回滚。
- 实现受控 Builder 区块与默认主题渲染器。
- 实现站点地图、robots、SEO 回退、Canonical 和 301 跳转。
- 建立内容编辑、发布、站点管理三层权限。

### P2：企业站模块解耦

目标：保留已完成业务价值，同时让 CMS 核心保持通用。

- 将现有案例、产品、方案 Model、Resource、路由和首页聚合逻辑收敛到包内 `Modules/Corporate`。
- 将首页业务聚合改为主题/模块能力，不写死在通用前台控制器。
- 保持旧 URL、旧数据和现有后台权限点的兼容迁移策略。

### P3：主题、质量与发布

目标：官方主题可稳定切换，`filamentboot-cms` 作为单包方案插件公开交付。

- 定义 `ThemeContract`、包内主题 manifest、模板和区块兼容性校验。
- 完成至少一个官方主题的桌面和移动端体验。
- 为移动端固定询盘入口增加安全间距与内容避让，复核深色主题的文本、标签和交互状态对比度。
- 提供发布主题视图、样式和主题配置的命令与文档。
- 为主题切换提供预检查、失败提示和回退方案。
- 优化公开内容页的缓存边界：不在首屏无条件挂载 Livewire 询盘表单，不让会话 Cookie 和 `private, no-store` 成为所有公开页面的默认响应。
- 补齐包 README、wiki 安装/配置/主题定制文档和升级说明。
- 在独立演示项目验证安装，不以当前工作区 Path Repository 作为唯一验收。

## 7. 验收标准

### 7.1 P0 验收

- 新 Laravel 项目能够通过 `composer require filamentboot/filamentboot-cms` 安装、迁移、初始化并在后台启用插件。
- 后台插件记录、缓存键、配置、发布 tag 和说明文档均使用 `filamentboot-cms`。
- 插件默认不覆盖宿主根路由；配置 `root` 后官网可接管根路径。
- 禁用插件后，官网路由、导航和前台资源不再可访问，宿主路由恢复。
- 可选主题均覆盖控制器会请求的全部视图。
- 宿主发布并修改主题视图后，前台渲染使用发布版本。
- 桌面导航、移动导航、详情页和悬浮入口的咨询 CTA 均可打开同一个询盘面板，并有浏览器测试覆盖。
- 站点不再输出 `picsum.photos` 等外部占位图片；全部封面、列表图和 OG 图可访问且来自受控媒体存储。
- 首页、案例、方案、产品等列表页有非空 `meta description`；默认 OG 图、`robots.txt`、`sitemap.xml` 均返回 200。
- 站点设置缺少电话、地址、备案、隐私链接或默认 SEO 时，后台出现可操作告警，且前台不输出空白联系栏目。
- CMS 前台与后台不再显示语言切换和 `/en` 路由入口。

### 7.2 CMS 核心验收

- 编辑可创建草稿，但不能直接发布；发布者可审核和发布。
- 未发布、未来定时发布和已归档内容无法通过公开 URL 访问。
- 授权用户可预览草稿，未授权用户不可访问。
- 每个已发布页面都有有效 canonical、SEO 回退和站点地图条目。
- 页面 slug 修改后可选择创建 301 重定向。
- 页面中仅允许主题已声明的区块，不支持任意 HTML、Blade 或 PHP 执行。

### 7.3 质量与发布验收

- Unit：区块 payload 校验、主题契约、SEO 回退和路由解析。
- Feature：草稿隔离、定时发布、预览授权、菜单权限、重定向和插件禁用。
- Browser：官方主题的首页、列表、详情、静态页和询盘在桌面/移动端均可用。
- Browser：导航/移动导航/浮动咨询 CTA、首屏图片清晰度、移动端固定按钮避让和公开页缓存边界均有回归用例。
- 包 README 中的命令、发布 tag、Seeder 类名和实际 Service Provider 注册完全一致。
- 全量测试、PHPStan、Pint 通过。
- 对外文档明确 `filamentboot-cms` 是单包交付，并说明包内 CMS 核心、企业站模块和后续可选模块的边界。

## 8. 不采用的方案

| 方案 | 不采用原因 |
|------|------------|
| 前后端分离 + Nuxt/Next | 增加两套工程、构建、鉴权、SEO 和部署链路，不符合快速交付官网插件的目标。 |
| Headless CMS | 用户需要的是官网成品和后台运营能力，而不是为另一个前端提供内容 API。 |
| 无限自由拖拽页面搭建器 | 主题难以升级，内容质量和响应式布局不可控，安全边界也会扩大。 |
| 多包拆分 | 当前只有一个官网方案，拆分会增加安装、版本和依赖管理复杂度，收益不足。 |
| 把所有行业模型放进 CMS 核心 | 核心会迅速与具体行业耦合，后续模块无法独立演进。 |

## 9. 关联文档

- [项目规范与目录结构](../prd/01-项目规范与目录结构.md)
- [插件市场 PRD](../prd/06-插件市场.md)
- [近期开发规划](../dev/项目开发规划.md)
- [一期开发后梳理](../dev/一期开发后的梳理.md)
- [官网插件当前 README（重命名前）](../../packages/filamentboot-site/README.md)
