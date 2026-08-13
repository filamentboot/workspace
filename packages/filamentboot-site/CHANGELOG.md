# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 规范，
版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

首次建档：本包此前没有独立的变更记录，下面收的是
`filamentboot-web`（五期起）在早期客户交付快照基础上做的改动。

## [Unreleased]

## [0.14.0] - 2026-08-13

> 九期·完善包（批次 2-9）的成果，随 monorepo 同一个 tag 与其余 6 个包一起发布。

### Added

- **`filamentboot-site:install` 一键安装命令**：依次发布配置/前端资源 → 执行迁移
  （25 张内容表）→ 写入权限点/三层角色/后台导航菜单三项结构性数据 → 扫描并启用
  插件 → 清缓存，全程幂等。加 `--with-demo` 顺带播种案例/方案/产品/资讯示例内容
- **`filamentboot-site:doctor` 健康检查命令**：七项检查——插件启用状态、迁移完整性
  （23 张核心表）、结构性种子（权限点）、关键路由（26 个 route name）、内容配置
  完整性（复用 `SiteHealthCheck`）、首页 HTTP 响应头（`Cache-Control`/无
  `Set-Cookie`）、媒体磁盘可写。任意一项不通过退出码非零，报告为 Markdown 清单
- **演示数据后台开关**：`SiteSettingsPage` 新增"种入演示数据"/"清空演示数据"两个
  超管专属 Header Action（背后是 `Services\DemoDataToggle::seed()`/`clear()`）。
  `clear()` 按各 Seeder 的 `seededSlugs()` 精确 `forceDelete()`（非软删，避免孤儿
  媒体文件）+ 删除 `main`/`footer` 两条导航菜单 + 复位列表页导语字段，种入/清空/
  再种入幂等
- **`filamentboot-site-tests` publish tag**：把 `tests/e2e/`（6 个 Playwright
  spec + `global-setup.cjs`）与根目录的 `playwright.config.site.cjs` 发布到宿主
  项目，`packages/filamentboot-site/tests/e2e/` 是唯一源。刻意不进
  `post_install.publish_tags` 自动发布清单（需要下游装 Node + Playwright 才能跑，
  不该跟着每次安装强行落地）
- `composer.json` 补 `"ext-intl": "*"` 到 `require`：`SiteProductResource`/
  `SitePackageResource` 的价格列（`->money('CNY')`）依赖它，缺失时 500
- `HasDataScope` 真实接入示范：`ContactMessageResource` 用 `personal` 档 + 属主列
  `assigned_to`，且放行未分配（`NULL`）的线索——新询盘在分配之前对跟进人也可见

### Changed

- **BREAKING**：资讯摘要字段 `excerpt_zh` 改名为 `description_zh`，与其余五类
  内容统一命名。硬改，不留 accessor 兼容——下游若在覆盖视图里直接读
  `$article->excerpt_zh`，迁移后会静默变成 `null`。迁移
  `2026_08_13_150000_rename_excerpt_zh_to_description_zh_in_site_news_articles_table.php`
  除改列名外，**同步改写 `site_revisions` 里已存资讯快照 payload 的 JSON 键**，
  否则回滚到改名前的历史版本时 `description_zh` 会静默恢复不生效
- `composer.json` 的 `extra.filamentboot.post_install.seeders` 摘掉
  `SiteDemoSeeder`/`SiteNewsSeeder`：此前"插件市场"一键安装路径会无条件跑这两个
  演示种子，与命令行 `filamentboot-site:install` 的 `--with-demo`（默认不种）行为
  不一致，现两条安装路径统一为默认不种演示数据，需要时用上面的后台开关或
  `--with-demo`
- 5 个 site Seeder（`SitePermissionSeeder`/`SiteRoleSeeder`/`SiteMenuSeeder`/
  `SiteDemoSeeder`/`SiteNewsSeeder`）补跑完输出确认信息，此前跑完零反馈无法确认
  是否成功

### Removed

- **BREAKING**：删除 `filamentboot-site-migrations` publish tag。本包的
  `loadMigrationsFrom()` 已自动加载全部迁移，发布这个 tag 会导致 `migrate` 重复
  扫描同一批文件。**升级注意**：若项目里已经发布过这个 tag，删除
  `database/migrations/` 下对应的已发布迁移文件即可，本包自带的自动加载不受影响

## [0.13.0] - 2026-08-12

> **本包首次正式发布。** 版本号与主包 `filamentboot/filamentboot` 保持一致——两者随
> monorepo 同一个 tag 一起 split 发布，不单独起版本序列。

### Added

- **可配置内容类型系统**（七期批次 5，YZNCMS 式物理列）：`Cms\ContentTypes` 下
  的字段类型注册表（9 个内置类型：text/textarea/rich-text/number/boolean/
  date/select/image/url）+ 声明式 `ContentTypeDefinition` + `content-type:sync`
  生成命令，一份字段清单跑一条命令即可生成迁移/Model/Resource（含 3 个
  Page）/Policy 五个真实文件（人工 review 后 `migrate`，不做运行时无审查
  DDL）；前台由通用渲染器 `ConfigurableContentRenderer` 按字段类型逐字段
  渲染（两套主题共用局部视图，降级哲学同 `BlockRenderer`：未知类型/视图
  缺失跳过并记日志）。用**友情链接**（`friend_link`）、**广告位**
  （`ad_slot`）两个内置内容类型验收：全部由生成命令产出，没有一行手写
  Model/Resource/Policy/视图/Seeder/测试
- **`software` 主题**：第二套官方模板，`decoration` 复制改名而来，同源共享
  `theme.php` 结构；首页按《官网对标》七屏重排，导航/页脚信息架构独立于
  `decoration`
- **Roadmap 区块**（`RoadmapBlock`）：按「已有 / 开发中 / 计划中」三档渲染
  功能矩阵，某档无条目时整组不渲染
- 内容工作流四件套（修订历史、草稿预览、`draft→…→archived` 状态机）从只覆盖
  `SitePage` 扩展到全部 7 类内容
- Cases / Products 分类前台展示（筛选 pills、详情页分类徽标）
- `SiteDemoSeeder` / `SiteNewsSeeder` 按模板拆分为两套虚构主体的演示数据
  （装修公司 / 软件公司），不再写死单一真实公司信息
- `CONTRIBUTING.md`
- **`Support\ContentTypeLabels`**：5 个内容类型（案例/方案/套餐/产品/资讯）的公开侧
  栏目名按 `active_theme` 分岔，写法照抄 4 个 Filament Resource 已验证过的三元式
  （七期批次 2）。`getModelLabel()` 用的更短管理语境词不受影响，两者是刻意分开的
  两套词表
- `tests/Feature/SiteFrontendAssetTest.php`（七期批次 3）：静态对账 `vite.config.js`
  的 input 声明、`config('filamentboot-site.assets.*')` 候选路径模板、`package.json`
  的 `alpinejs` 依赖三处是否漂移，并对着真实 `public/build/manifest.json` 验证
  `ThemeAsset` 解析结果命中的不是回落值——此前这条链路没有任何测试盯着

### Changed

- **BREAKING**：`SiteProduct` 发布语义由布尔 `is_published` 统一为 `published_at`，
  支持预约上架，与全站其余内容类型口径对齐——下游若直接查过 `is_published` 列
  （报表脚本、自定义查询）需要改成 `whereNotNull('published_at')`
  `->where('published_at', '<=', now())` 的判断方式
- **两套主题 CSS 的 token / `@utility` 语义类 / `.prose` 定义合并进共享文件**
  `resources/css/themes/shared.css`：实测 `decoration.css`/`software.css` 这一层
  逐字节相同（构建产物早就被 Vite 按内容哈希去重成同一个物理文件），`decoration.css`/
  `software.css` 现在各自只剩一行 `@import './shared.css'`，仍是两个独立 Vite 入口，
  下游 `vite.config.js` 的两行入口声明不用改。编译产物经比对逐字节等价（详见七期批次 1 记录）
- `SearchPushObserver` 的百度主动推送覆盖范围补上 `SiteCityPage`（此前 7 类内容里唯一
  漏掉的一类）；因城市页没有 `slug` 列，URL 走单独分支通过 `SiteCityPage::url()` 拼
- 两套主题 nav/footer 组件的兜底数组、`SiteFrontMenuSeeder` 改为读取 `ContentTypeLabels`
  （七期批次 2）：三处此前各自硬编码同一批词、只能靠人工"逐条对齐"，文案本身不变，
  只是不用再手工核对

### Fixed

- **`SiteFrontController`/`SitemapController` 与批次 1 下沉到 `shared/` 的约 10 个
  视图此前完全不感知 `active_theme`，一律硬编码 decoration 措辞**（七期批次 2）。
  实测本仓库 `active_theme` 当前就是 `software`，面包屑、SEO 列表标题、`llms.txt`
  栏目名、图片占位符标签在这个配置下**当时就在显示错误的行业术语**（如
  「装修案例」「全屋智能套餐」「智能家居资讯」），不是潜在风险，是已经文不对题。
  改用 `ContentTypeLabels` 后两套主题各自显示对应措辞；decoration 内部此前不一致
  的用词（如「全屋套餐」与「全屋智能套餐」并存）一并收口为单一说法

### Removed

- **两套主题 CSS 里失效的 `:root[data-theme="dark"]` 惰性块**（各 48 行）：当前没有任何
  入口会给 `<html>` 加 `data-theme="dark"`，且这批取值是二期之前亮青配色（`#00d4ff`）的
  考古快照，与当前克莱因蓝主色（`#002FA7`）无关，还漏了 3 个 rgb 通道 token，若真被启用会
  让固定导航条等半透明元素在深色下渲染成不透明白色——是陷阱不是可用资产。「配色变体层」
  改列为独立命题，不在本次改动范围。取值可从 git 历史找回
  （`git log -p -- packages/filamentboot-site/resources/css/themes/decoration.css`）

## 0.x（早期客户交付快照之前）

早期开发未按语义化版本管理，历史变更未系统整理，从本文件建档起点开始记录。
