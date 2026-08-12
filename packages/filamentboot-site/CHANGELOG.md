# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 规范，
版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

首次建档：本包此前没有独立的变更记录，下面收的是
`filamentboot-web`（五期起）在早期客户交付快照基础上做的改动。

## [Unreleased]

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
