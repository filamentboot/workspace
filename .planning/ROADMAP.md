### Phase 9: 编辑器插件

**Goal**: 开发富文本编辑器和 Markdown 编辑器两个 Filament 表单组件插件，支持图片上传（与媒体库联动）、代码高亮、自定义工具栏

**Depends on**: Phase 1（基础架构），可与 Phase 5/6/8 并行
**Requirements**: EDITOR-01, EDITOR-02
**Work estimate**: 约 8-12h（富文本 4-6h + Markdown 4-6h）

**Success Criteria**:

1. Filament 表单中使用 `RichEditor::make('content')` 即可渲染富文本编辑器（Tiptap（内置 RichEditor 增强）），支持图片拖拽上传到媒体库、表格、链接、代码块；并可在插件市场一键替换为 wangEditor 独立插件包（D-09-03）
2. Filament 表单中使用 `MarkdownEditor::make('content')` 即可渲染 Markdown 编辑器，支持实时预览、工具栏（加粗/斜体/标题/列表/链接/图片/代码）、图片上传到媒体库
3. 两个编辑器均支持配置工具栏按钮、上传磁盘、文件大小限制，与现有 `UploadSettings` 联动
4. 编辑器输出的内容在详情页正确渲染（HTML 安全过滤 / Markdown 转 HTML）

**Plans**: 4 plans
Plans:
**Wave 1**

- [x] 09-01-PLAN.md — 富文本包骨架 + RichEditorField（Tiptap 增强：动态磁盘+UploadSettings 联动）+ RichEditorPurifier 保存前 XSS 过滤 + Wave 0 测试（EDITOR-01）
- [x] 09-03-PLAN.md — Markdown 包完整：MarkdownEditorField（EasyMDE 增强：分屏预览+代码高亮+动态磁盘）+ MarkdownRenderer（CommonMark+XSS 展示时过滤）+ Wave 0 测试（EDITOR-02）

**Wave 2** *(blocked on 09-01 — 复用 UploadValidator + Phase 8 上传协议)*

- [x] 09-02-PLAN.md — wangEditor 独立插件包（packages/filament-admin-wang-editor/）：独立 composer.json + WangEditorPlugin/ServiceProvider + WangEditorField custom field + Blade/Alpine 桥接 + 图片上传路由（接 UploadValidator 三重校验）+ FilamentAsset 注册（EDITOR-01，D-09-03/D-09-04）

**Wave 3** *(blocked on 09-01 + 09-02 + 09-03)*

- [x] 09-04-PLAN.md — Monorepo 集成（根 composer.json repositories/require/autoload，含 rich-editor / markdown-editor / wang-editor 三包 + config/purifier.php richeditor 白名单）+ 集成测试（plugin:scan 发现 + 类加载 + 渲染过滤）（EDITOR-01/02）

---

### Phase 10: 官网插件

**Goal**: 开发官网插件（`laravelstack/filament-admin-site`），让客户可在后台管理并发布自己公司的前台官网（页面、文章、产品），作为插件市场的示范旗舰插件

**Depends on**: Phase 6（插件市场契约），Phase 8（图片上传磁盘），Phase 9（富文本编辑器）
**Requirements**: SITE-01, SITE-02, SITE-03, SITE-04
**Work estimate**: 约 10-15h

**Success Criteria**:

1. `packages/filament-admin-site/` 独立 Composer 包，按 Phase 6 `extra.filament-admin` 契约构建，可被 `plugin:scan` 发现并在后台启停
2. 后台提供页面（Page）、文章（Article）、产品（Product）的 Filament Resource CRUD，含分类、标签、发布状态、置顶
3. 前台路由在插件启用时自动接管根域 `/`（首页）与 `/{slug}`（单页/文章/产品详情），禁用时不影响现有 routes/web.php
4. 前台模板采用 Blade + Tailwind CSS 响应式设计 + Livewire 按需交互，SEO meta（TDK）直出
5. 支持多套可切换主题（整体皮肤切换，不只配色）

**Plans**: 5 plans

Plans:

**Wave 1**

- [x] 10-01-PLAN.md — 包骨架：composer.json extra.filament-admin 契约 + SitePlugin/SiteServiceProvider/SiteSettings/SiteSettingsPage + 8 个 Wave 0 测试桩（SITE-04）
- [x] 10-02-PLAN.md — 数据层：8 张 site_ 迁移表 + 7 个 Eloquent 模型 + 3 枚举 + 工厂 + SiteDemoSeeder（SITE-01）  *(wave 2, blocked on 10-01)*
- [x] 10-03-PLAN.md — 后台：5 个 Filament Resource CRUD（双语 Tab/SEO/图片/分类/标签/发布/置顶）+ Policy + 未读 Widget + SitePlugin 注册（SITE-01）  *(wave 3, blocked on 10-02)*
- [x] 10-04-PLAN.md — 前台接管层：条件路由 + SiteFrontController + 双语中间件 + ContactForm/CaseFilter Livewire + monorepo 集成（SITE-02）  *(wave 4, blocked on 10-03)*
- [x] 10-05-PLAN.md — 前台视觉层：decoration 全套 Blade + tech-product 骨架 + 2 套 Tailwind 主题 CSS + 主题切换 + SEO 直出（SITE-02/SITE-03）  *(wave 5, blocked on 10-04)*

---

### Phase 11: 官网插件实战 + 晴空上线

**Goal**: 以"晴空智能家"真实项目为蓝本，调试并打磨 filament-admin-site 官网插件与 decoration 主题，使其达到开箱即用的展示级水准，同时将晴空官网部署上线替换现有 Next.js 站

**Depends on**: Phase 10（官网插件）
**Requirements**: SITE-DEBUG-01, SITE-THEME-01, SITE-DEPLOY-01
**Work estimate**: 约 20-30h

**Background**:
decoration 主题的参考实现即晴空智能家官网（湖北晴空妙享科技有限公司，智能家居系统设计安装）。现有站为 Next.js + Ant Design v5，内容图片文案齐备，替换后同域名上线。主题打磨成果回合进 filament-admin-site 包，让其他用户拿到的 Seeder 示例数据具备真实质感，换图换文即可直接投入使用。

**Delivery Steps**:

1. **主题打磨** — decoration 主题品牌适配：晴空配色、Hero 真实背景图、service-card 数据循环、展示级视觉
2. **补全安装命令** — 新增 `filament-admin:install` Artisan 命令，自动生成 AdminPanelProvider、注册插件、跑迁移（修补包完成度不足的缺口，成果回合进主包）
3. **新建独立项目** — 全新独立目录 + 独立 git 仓库，不在 monorepo 内
4. **走完整安装流程** — 命令能跑的用命令；若有残缺顺手修命令，确保安装流程端到端可复现
5. **数据初始化 + Playwright 全流程验证** — 运行 SiteDemoSeeder，Playwright 覆盖：所有页面加载、联系表单提交、案例筛选、主题切换
6. **SSH 部署上线** — 域名/git/服务器由用户提供；SSH 上服务器 git clone + composer install + nginx 配置 + SSL；替换 Next.js 站，旧路由 301 重定向

**Success Criteria**:

1. `php artisan filament-admin:install` 命令可在全新 Laravel 13 项目中一键完成基础接入（AdminPanelProvider + 迁移 + 插件注册）
2. filament-admin-site 在独立新项目中可完整安装、plugin:scan 发现、后台管理内容
3. decoration 主题完成晴空品牌适配，视觉效果明显优于原 Ant Design 站
4. Playwright 完整流程全绿：所有页面加载正常，联系表单可提交，案例筛选可用，主题切换正常
5. 晴空智能家官网 SSH 部署上线，同域名替换 Next.js 站，旧路由 301 重定向
6. SiteDemoSeeder 更新为晴空品质示例数据，主题改进与 install 命令均合并回对应包

**Plans**: 5 plans

Plans:

**Wave 1**

- [x] 11-01-PLAN.md — filament-admin:install 命令（七步一键接入）+ AdminPanelProvider stub + InstallCommandTest（SITE-DEBUG-01）
- [x] 11-02-PLAN.md — decoration 主题晴空品牌适配（Hero 背景图 + service-card 文案）+ SiteDemoSeeder 真实内容（本地图片优先降级）（SITE-THEME-01）

**Wave 2** *(blocked on 11-01 + 11-02，需先 commit)*

- [x] 11-03-PLAN.md — filament-admin-site subtree split 发布 v0.10.0 → 独立 GitHub 仓库 → Packagist 收录 + 干净环境验证（SITE-DEBUG-01）

**Wave 3** *(blocked on 11-03 发包)*

- [x] 11-04-PLAN.md — 晴空独立项目端到端安装链路（composer require → install → plugin:scan → 启用 → Seeder）+ 真实图片 + Playwright 全流程（SITE-DEBUG-01 / SITE-THEME-01）

**Wave 4** *(blocked on 11-04 本地验证)*

- [x] 11-05-PLAN.md — SSH 部署 qkznj.com 上线（/var/www/qkznj + nginx php8.4-fpm + acme.sh SSL + 旧路由 301）替换 Next.js 站（SITE-DEPLOY-01）

---

### Phase 12: 插件市场重构 — Filament 生态对接

**Goal**: 将 Phase 6 实现的自定义插件协议迁移到 `Filament\Contracts\Plugin` 标准，使 filament-admin 后台插件市场能无障碍容纳并管理任意符合 Filament 规范的社区开源插件，实现"浏览 → 安装 → 启用 → 卸载"全流程

**Depends on**: Phase 6（插件市场基础架构），Phase 11（完成）
**Requirements**: MKTPLACE-01~09, DOC-09, DOC-10, DOC-11
**Work estimate**: 约 30-40h
**UI hint**: yes（插件目录浏览页、安装状态 UI、依赖冲突提示）

**Success Criteria**:

1. `PluginScanCommand` 扫描 `vendor/` 中实现 `Filament\Contracts\Plugin` 的类，而非依赖自定义协议
2. 后台"安装"按钮能触发 `composer require` 并全程反馈状态
3. 安装成功后自动完成 publish / migrate / dump-autoload，无需手动干预
4. 依赖冲突时显示可读错误 + 手动安装命令，不崩溃
5. 插件目录显示 Filament 兼容版本标签，不兼容插件禁止安装
6. 卸载流程完整（composer remove + DB 清理 + cache 清理）
7. 安装前环境自检通过（权限 + Composer 路径）
8. 所有现有一方插件（OSS / COS / 编辑器系列 / filament-admin-site）通过合规审查，符合 Filament\Contracts\Plugin 规范
9. `wiki/plugin-development.md` 完整，国内开发者可照着文档从零写一个兼容插件
10. `wiki/plugin-usage.md` 完整，涵盖手动安装、后台管理、常见问题排查
11. 主包 README 新增插件生态章节，一方插件列表与文档链接齐备

**Plans**: 9/9 plans complete

Plans:
**Wave 1**

- [x] 12-00-PLAN.md — Wave 0 红测脚手架 + Queue::fake/Http::fake/Process 替身（Nyquist）
- [x] 12-01-PLAN.md — 混合发现（Filament\Contracts\Plugin classmap grep）+ plugins 表重塑 + post_install_data（MKTPLACE-01）

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 12-02-PLAN.md — 环境自检 + ComposerInstall/Remove Job（symfony/process）+ PluginManager 安装/卸载/post-install（MKTPLACE-02/03/04/06/07）
- [x] 12-03-PLAN.md — PackagistService 社区实时搜索 + 三态兼容判定（MKTPLACE-05/08）

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 12-04-PLAN.md — 三视图市场 UI + 安装/卸载 Action + 徽章/降级/风险弹窗/轮询（MKTPLACE-02/04/05/06/08）
- [x] 12-05-PLAN.md — 6 个一方插件合规修复（post_install）+ audit-plugins 报告命令（MKTPLACE-09）

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 12-06-PLAN.md — 插件生态文档 wiki/plugin-development.md + plugin-usage.md + README 章节（DOC-09/10/11）

**Gap closure** *(from 12-UAT.md — major/minor findings)*

- [x] 12-07-PLAN.md — 市场一键安装 firstOrCreate 修复（installPlugin/installCommunityPlugin no-op）+ composer require 版本约束（MKTPLACE-02/04）
- [x] 12-08-PLAN.md — Filament 自定义主题（->viteTheme）编译市场 blade 自定义 utility，修复未渲染样式（MKTPLACE-04）

---

### Phase 21: 代码整理收尾

**Goal**: 清理累积的技术债、修复已登记 bug、将代码质量提升到发版标准

**Depends on**: Phase 1~10 全部完成
**Requirements**: CLEANUP-01, CLEANUP-02, CLEANUP-03
**Work estimate**: 约 15-20h

**Success Criteria**:

1. PHPStan Level 6 零错误
2. Pint 格式零警告，Feature 层测试覆盖率 ≥ 80%
3. 所有已登记 bug（CR-01 等，含 parent_id=0 根菜单保存 bug）修复完毕

**Plans**: TBD（执行前用 gsd-plan-phase 读已登记缺陷账目生成计划）

---

### Phase 22: 发版与仓库整理

**Goal**: 打 v0.5.0 tag，完成 Packagist 发版、subtree split 子包同步、CHANGELOG 更新

**Depends on**: Phase 21
**Requirements**: RELEASE-07
**Work estimate**: 约 1-2h

**Success Criteria**:

1. v0.5.0 tag 推送到 GitHub 和 Gitee
2. Packagist 自动更新，`composer require laravelstack/filament-admin:^0.5` 可安装
3. subtree split 各子包仓库同步
4. CHANGELOG 更新，README 版本号更新

**Plans**: TBD

---

### Phase 13: filamentboot 生态改名与基础设施

**Goal**: 完成项目改名（filamentboot/filamentboot）、搭建 10 个 GitHub repo + Gitee 镜像、配置 GitHub Actions 发布流水线与部署（CONTEXT D-02/D-03 修正成功标准：repo 数 10、过渡域名 xitongapp.com）

**Depends on**: Phase 12
**Requirements**: 见 `.planning/todos/pending/rename-filamentboot.md`、`.planning/todos/pending/setup-ecosystem-infrastructure.md`
**Work estimate**: 约 8-12h

**Success Criteria** (CONTEXT D-02/D-03 为准，覆盖下方过期表述):

1. 所有 composer.json 包名从 `laravelstack/filament-admin*` 改为 `filamentboot/filamentboot*`
2. PHP namespace 从 `FilamentAdmin\` 全面改为 `Filamentboot\`，`composer dump-autoload` 无报错
3. GitHub org `filamentboot` 下 **10 个** repo（workspace + 核心包 + 6 插件 + www + demo）创建完毕，Gitee 同名镜像配置完成（D-02）
4. 本地 remote 重配 origin→filamentboot/workspace + gitee；镜像由 GitHub Actions 自动推 Gitee（D-11，取代手动双 push）
5. `.github/workflows/release.yml` 重写：tag 触发 → splitsh-lite 7 包 subtree push → Gitee mirror → Packagist 验证
6. 部署走 Gitee Go（保留现有 deploy.sh，按 D-20 调路径/worker）；官网走 GitHub Actions SSH rsync
7. `composer require filamentboot/filamentboot` 在干净 Laravel 13 项目可安装（需人工验证，D-03）
8. **`demo.xitongapp.com`** 可登录访问（过渡域名；`demo.filamentboot.com` 卡备案，DEFERRED 用户手动切换，D-03，需人工验证）
9. `www.xitongapp.com` 官网占位页返回 200（修复当前 500，需人工验证，D-03/D-22）
10. 泄露的 webhook token 已轮换并从代码库清除（D-18）

**Plans**: 6/6 plans complete

Plans:

**Wave 1 — 全量改名（agent 全自动 + 测试门，D-04）**

- [x] 13-01-PLAN.md — Wave 0 基线 + 主包改名（composer.json/namespace/类 git mv/config/命令前缀/publish tag）+ PackageMetadataTest（rename-filamentboot）
- [x] 13-02-PLAN.md — 6 插件包逐个改名（cos/oss/rich/markdown/wang/site，依赖顺序 + 每包测试门）（rename-filamentboot）  *(wave 1, blocked on 13-01)*
- [x] 13-03-PLAN.md — preview app/config/resources + 前端目录 + 品牌名 + GitHub URL + 泄露 token 清除 + 改名收尾门（rename-filamentboot）  *(wave 1, blocked on 13-02)*

**Wave 2 — 生态基础设施（agent 写代码/脚本 + 手动 checklist，D-01）**

- [x] 13-04-PLAN.md — release.yml 重写（splitsh-lite 7 包 + Gitee mirror）+ ci.yml/deploy.sh/master-pipeline 路径调整 + SECRETS-CHECKLIST（setup-ecosystem-infrastructure）  *(wave 2, blocked on 13-03)*
- [x] 13-05-PLAN.md — INFRA-CHECKLIST 手动操作总线 + 本地 remote 重配 + 服务器迁移脚本 + demo repo scaffold（setup-ecosystem-infrastructure）  *(wave 2, blocked on 13-04)*
- [x] 13-06-PLAN.md — filamentboot-www 静态占位页 + SSH rsync 部署 workflow + 上线指引（setup-ecosystem-infrastructure）  *(wave 2, blocked on 13-04)*
