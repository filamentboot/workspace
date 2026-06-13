# Phase 10: 官网插件 - Context

**Gathered:** 2026-06-13
**Status:** Ready for planning

<domain>
## Phase Boundary

开发 `packages/filament-admin-site/` 独立 Composer 包（`laravelstack/filament-admin-site`），作为插件市场的示范旗舰插件，同时为**湖北晴空妙享科技有限公司**（qkznj.com，智能家居方案 + 设计驱动改造）构建真实可部署的官网。

**业务定位：** 设计驱动 + 局部改造 + 智能家居方案  
**域名：** qkznj.com（新注册，未配置，Phase 10 结束后可部署）  
**风格：** 高科技感，深色主题，科技蓝/青色渐变

**不在本 Phase 范围内：**
- 电商购物车/结账（产品只做展示，不含交易）
- 用户评论系统
- 站内全文搜索
- 多语言 v2（本 Phase 完成中英双语基础框架；完整 i18n 完善推迟）
- 图片 CDN 集成（使用 Phase 8 默认磁盘）

</domain>

<decisions>
## Implementation Decisions

### 包结构与契约
- **D-10-01:** 独立 Composer 包 `packages/filament-admin-site/`，名称 `laravelstack/filament-admin-site`，按 Phase 6 `extra.filament-admin` 契约构建（slug、name、plugin_class、service_provider、description），可被 `plugin:scan` 发现并在后台启停。
- **D-10-02:** 插件启用时 `SiteServiceProvider::boot()` 注册前台路由（接管 `/`、`/en/` 及各内容前缀）；禁用时不注册，不影响现有 `routes/web.php`。

### 内容类型（5 种，独立表）
- **D-10-03:** 独立三（五）张表，不共享 contents 基础表——行业标准做法，字段完全自定义。

| 类型 | 表名 | 核心字段 |
|------|------|---------|
| 装修案例（Case） | `site_cases` | title_zh/en, slug, style(enum), house_type(enum), area, budget_range, smart_features(text), cover_image, gallery(json), description_zh/en, seo_title/description/keywords, is_featured, published_at |
| 智能方案（Solution） | `site_solutions` | title_zh/en, slug, description_zh/en, content_zh/en(richtext), cover_image, price_range, is_featured, sort, seo fields, published_at |
| 智能产品（Product） | `site_products` | title_zh/en, slug, description_zh/en, cover_image, price, brand, category_id, is_featured, sort, seo fields |
| 静态页面（Page） | `site_pages` | title_zh/en, slug, content_zh/en(richtext), seo fields, is_published |
| 询盘（ContactMessage） | `site_contact_messages` | name, phone, message, status(enum: unread/contacted/closed), ip, created_at |

- **D-10-04:** SEO 字段：每张内容表独立 `seo_title`、`seo_description`、`seo_keywords` 三列（而非 JSON），直观可查。
- **D-10-05:** 案例附带分类（`site_case_categories` 表：name_zh/en, slug）和标签（`site_tags` 表复用，多态）；产品附带分类（`site_product_categories`）。

### 前台路由与 URL 结构
- **D-10-06:** 分前缀路由（SEO 友好，slug 按类型独立空间）：
  - `/` — 首页
  - `/cases/` — 案例列表；`/cases/{slug}` — 案例详情
  - `/solutions/` — 方案列表；`/solutions/{slug}` — 方案详情
  - `/products/` — 产品列表；`/products/{slug}` — 产品详情
  - `/{slug}` — 静态页面（about、contact 等）
  - `/en/` 系列对应英文版本

### 多语言
- **D-10-07:** 中文（默认）+ 英文（`/en/` URL 前缀）双语。
- **D-10-08:** 数据库存储方式：每张内容表直接增加 `_zh`/`_en` 字段对（如 `title_zh`, `title_en`）——比 spatie/laravel-translatable 实现更简单直观，researcher 可评估是否改用 translatable 包（需权衡迁移成本）。
- **D-10-09:** 语言检测：URL 前缀 `/en/` 触发英文模式，中间件注入 `app()->setLocale('en')`；默认中文。
- **D-10-10:** 后台编辑页展示中英双语 Tab，两套字段都在同一 Filament Resource 表单中。

### 主题
- **D-10-11:** 内置 **2 套主题**，后台设置页可切换：
  - `decoration`（本项目）：深色背景 + 科技蓝/青渐变，面向装修/智能家居公司
  - `tech-product`（为下一个科技产品公司项目预留）：参数化但不必第一版完美
- **D-10-12:** 每套主题 = 独立 Blade 模板目录（`resources/views/themes/decoration/` 等）+ 独立 CSS 文件。用户可 `vendor:publish` 后在 `resources/views/vendor/filament-admin-site/` 目录中覆盖。
- **D-10-13:** 主题选择存入 SiteSettings，前台 ServiceProvider 根据当前主题加载对应 Blade 目录。

### 网站设置（SiteSettings）
- **D-10-14:** 使用 Spatie laravel-settings（与 Phase 8 OSS/COS 一致）：
  ```
  company_name_zh / company_name_en
  phone / phone_en
  address_zh / address_en
  logo（Media Library）
  wechat_qrcode（Media Library）
  icp_number
  seo_default_title_zh/en
  seo_default_description_zh/en
  active_theme（default: 'decoration'）
  ```

### 询盘表单
- **D-10-15:** 极简：姓名 + 电话 + 留言。后台 ContactMessage Resource 支持状态流转（unread → contacted → closed）。不发邮件通知（首期），后台定期查看即可。

### 前台技术栈
- **D-10-16:** Blade + Tailwind CSS（响应式，移动端适配）+ Livewire（按需交互，如表单提交、筛选）。
- **D-10-17:** SEO：每页 `<head>` 直出 `<title>`、`<meta name="description">`、`<meta name="keywords">`，Open Graph 基础标签。

### 示范内容（Demo）
- **D-10-18:** 案例图片从 Unsplash API 或 Pexels 获取高质量智能家居/装修图（关键词：`smart home interior`, `modern living room`，中性用途图），植入 DatabaseSeeder。图片仅作开发/演示用，生产替换真实图。

### Claude's Discretion
- 多语言包选型：spatie/laravel-translatable vs `_zh/_en` 字段 — researcher 评估后确定
- Livewire 组件粒度（案例筛选器是否拆为独立组件）
- Tailwind 暗色模式实现方式（class-based dark mode vs CSS variables）
- 前台图片懒加载方案
- Open Graph 图片生成策略

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### 插件市场契约（Phase 6 标准）
- `.planning/phases/06-plugin-marketplace-launch/06-CONTEXT.md` — Phase 6 已锁定决策（`extra.filament-admin` 合同字段、插件启停机制）
- `packages/filament-admin-oss/composer.json` §extra.filament-admin — 契约字段范本（slug/name/plugin_class/service_provider/settings_page_slug/requires/compatibility）

### Phase 8/9 跨切依赖
- `.planning/phases/08-cloud-storage-plugins/08-CONTEXT.md` — 图片上传走当前默认磁盘的契约
- `packages/filament-admin/src/Settings/UploadSettings.php` — 上传配置来源
- `packages/filament-admin-rich-editor/` — Phase 9 富文本字段，Case/Solution/Page 的 content 字段编辑器

### 需求权威来源
- `.planning/ROADMAP.md` §Phase 10 — SITE-01~04 + Success Criteria
- `.planning/REQUIREMENTS.md` §官网插件（Phase 10） — SITE-01~04 正式定义

### 现有包模式参考
- `packages/filament-admin-oss/src/OssServiceProvider.php` — ServiceProvider 模式（loadRoutesFrom、loadMigrationsFrom、publishes）
- `packages/filament-admin-oss/src/OssPlugin.php` — Plugin 类实现范本
- `packages/filament-admin-oss/src/Settings/OssSettings.php` — Spatie Settings 用法

### 公司信息
- 公司名：湖北晴空妙享科技有限公司
- 域名：qkznj.com（新注册，待配置）
- 业务：智能家居方案 + 设计驱动改造

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `packages/filament-admin-oss/src/OssPlugin.php` + `OssServiceProvider.php`：完整插件 + ServiceProvider 骨架，可直接复用结构
- `packages/filament-admin-rich-editor/src/Forms/RichEditorField.php`：内容富文本编辑器，Case/Solution/Page 的 `content` 字段直接复用
- `packages/filament-admin/src/Settings/UploadSettings.php`：图片上传配置读取，cover_image/gallery 上传统一走此配置
- `packages/filament-admin/src/Policies/BasePolicy.php`：权限命名约定（`view_any_{resource_snake}`），Site 资源 Policy 继承即可
- `solution-forest/filament-tree`：已安装，Case 分类/产品分类如有层级可复用

### Established Patterns
- **Phase 8/9 monorepo 集成**：path repository + symlink + @dev require + autoload PSR-4 双向映射——Site 包按同一模式集成到根 composer.json
- **`extra.filament-admin` 插件合同**：slug/name/plugin_class/service_provider/description/requires/compatibility 字段必须完整
- **Spatie Settings**：OssSettings/CosSettings 模式——Settings 类 + Filament Settings 页面 + 后台设置入口按钮
- **测试约定**：包内 Unit 测试用 Orchestra Testbench；集成测试写到根 `tests/Feature/`

### Integration Points
- `app/Providers/Filament/AdminPanelProvider.php`：注册 SitePlugin，添加设置页入口
- `routes/web.php`：Site 包路由在 ServiceProvider 中 `loadRoutesFrom()`，与现有静态路由平行（插件禁用时不加载）
- `composer.json` §repositories / §require / §autoload：新增 site 包 path repository 和 PSR-4 映射

</code_context>

<specifics>
## Specific Ideas

- **晴空妙享品牌色**：深色背景（#0a0e1a 或 #0d1117），主色调科技蓝（#00d4ff 或 #1e90ff），点缀色青绿渐变。参考 Apple Vision Pro 发布页风格。
- **首页结构（参考土巴兔 + 高端品牌站）**：全屏 Hero（大图/视频 + Slogan + CTA「预约咨询」）→ 服务亮点（3列卡片）→ 精选案例（瀑布流/网格）→ 智能产品展示→ 公司资质/荣誉 → 联系 CTA。
- **Demo 图片**：Unsplash 关键词：`smart home living room`、`modern interior design`、`home automation`、`intelligent lighting`；使用 `https://source.unsplash.com/` 或 Pexels API。
- **DK-AI 联系表单样式**：弹出式留言表单（姓名 + 电话(必填) + 邮箱 + 地址 + 留言），风格参考 dk-ai.com 右侧悬浮联系入口。

</specifics>

<deferred>
## Deferred Ideas

- **多语言完善（v1.x）**：本 Phase 只做中英双语框架（`_zh`/`_en` 字段 + `/en/` 前缀路由）；完整 i18n 语言切换 UI、翻译管理后台推迟。
- **评论系统（v1.x）**：案例/文章评论不在 Phase 10 范围。
- **全文搜索（v1.x）**：站内搜索（MeiliSearch/Scout）推迟。
- **新闻/资讯（Article）（可选扩展）**：本 Phase 未包含 Article 内容类型；如工期允许可在 Phase 10 末期追加，否则 v1.x。
- **科技产品主题完善（v1.x）**：`tech-product` 主题第一版只做基础骨架（layout + 配色），完整模板 v1.x 完善。
- **购物车/电商（v2.x）**：产品只做展示，不含交易系统。
- **ICP 备案自动化**：qkznj.com 的 ICP 备案需线下操作，不在代码范围。

</deferred>

---

*Phase: 10-official-website-plugin*
*Context gathered: 2026-06-13*
