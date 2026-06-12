# Roadmap: FilamentAdmin v0.5

**Milestone:** v0.5 — 让主包"全部完成"形态 ➔ v0.6+ — 补缺闭环 + 插件市场 + 成品收尾
**Core Value:** 别人执行 `composer require laravelstack/filament-admin` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态符合 Laravel 开源市场规范
**Created:** 2026-06-09
**Granularity:** Fine
**Parallelization:** Phase 1-4 串行；Phase 5-9 合并规划，单人按优先级推进

---

## Phases

- [x] **Phase 1: 包发布合规** — 修复 ServiceProvider `publishes()` 缺失 + PublishCommand 真实实现 + Composer 规范字段 + CI 门槛，让主包真正符合 Laravel 开源包标准
- [x] **Phase 2: 文档与品宣** — README 重写 / wiki 完整化 / CHANGELOG 规范 / UPGRADING，让别人装下来看得懂、用得了、愿意 Star (completed 2026-06-10)
- [x] **Phase 3: 包功能补强** — Impersonation + Scramble API 文档 + CRUD 生成器，补齐 kaido-kit 已有的核心差异化功能 (completed 2026-06-10)
- [ ] **Phase 4: 发布自动化** — release.yml + 发版脚本三件套 + Codecov，让下次发版从 9 条手工命令变为打 tag 就完事
- [x] **Phase 5: 演示站** — demo.xitongapp.com 部署 + 数据重置 cron + 高危操作屏蔽，先拿出看得见的成果 (completed 2026-06-11)
- [x] **Phase 6: 插件市场 + 对外展示** — 启停控制、安装链路、安全校验、市场数据边界 + 官网、Release Notes、CI audit（计划全部完成，待人工验证 2 项 UI 行为） (completed 2026-06-12)
- [x] **Phase 7: 质量基座** — 7个Bug修复 + 4项一期补缺（密码重置/2FA强制/数据权限/日志策略） (completed 2026-06-12)
- [x] **Phase 8: 云存储插件** — 阿里云 OSS + 腾讯云 COS Filament 插件 (completed 2026-06-12)
- [ ] **Phase 9: 编辑器插件** — 富文本编辑器 + Markdown 编辑器 Filament 插件
- [ ] **Phase 10: 官网插件** — 普通企业官网插件，页面管理、文章/产品发布、SEO、主题切换
- [ ] **Phase 11: 代码整理收尾** — 基于已开发功能和所有已知 Bug，全面审查整理代码、修复问题、统一风格
- [ ] **Phase 12: 发版与仓库整理** — 修复包仓库 subtree split 问题、重新打正确的 v0.5.0 tag、推送两端包仓库、创建 GitHub Release、Gitee 同步

---

## Phase Details

### Phase 1: 包发布合规

**Goal**: 主包 ServiceProvider 提供完整的 `publishes()` 出口，PublishCommand 真实可用，Composer 规范字段齐全，CI 门槛对齐，让主包从"已发包但未对外可用"变为"符合 Laravel 开源包标准"
**Depends on**: 无（第一个 phase）
**Requirements**: COMPLY-01, COMPLY-02, COMPLY-03, COMPLY-04, COMPLY-05, COMPLY-06, COMPLY-07, COMPLY-08, COMPLY-09
**Work estimate**: 约 10-15h（M1 1h + M2 PublishCommand 真实实现 6h + M3 1.5h + M4 0.5h + M5 1.5h + M6 0.3h + M7 0.1h + M11 0.5h + COMPLY-09 邮箱验证 0.5h）

**Success Criteria**（以下全部为 TRUE 才算 Phase 1 完成）:

1. 在干净的 Laravel 13 项目中执行 `php artisan vendor:publish --tag=filament-admin-config` 能在 `config/filament-admin.php` 落地配置文件；其余 4 个 tag（migrations / views / lang / stubs）同理各自落地到对应目录
2. 执行 `php artisan filament-admin:publish --model=Product --resource=ProductResource` 输出"已生成 2 个文件"清单，并在用户项目的 `app/Models/Product.php` 和 `app/Filament/Resources/ProductResource.php` 生成对应 stub 渲染文件；`--all` 参数四件套全部输出
3. `packages/filament-admin/composer.json` 包含 `extra.branch-alias.dev-main`、`require-dev` 含 larastan + pint、`scripts` 段含 test/phpstan/pint、`suggest` 段含 ext-redis、`support.docs` 有效链接、`authors[0].email` 已填写
4. 在 `packages/filament-admin/` 目录执行 `vendor/bin/phpstan analyse` 和 `vendor/bin/pint --test` 均退出码为 0（level 6+）
5. GitHub 仓库主页显示 "MIT License" 标签（根目录 LICENSE 文件已补）；根目录 `/src/` 孤儿已删除；`CONTRIBUTING.md` 含 SemVer 发版规范小节

**Plans**: 6 plans
Plans:
**Wave 1**

- [x] 01-01-PLAN.md — 质量门禁与测试脚手架（phpstan.neon / pint.json / phpunit.xml.dist 修正 + 测试骨架）
- [x] 01-05-PLAN.md — 仓库治理（COMPLY-06/07/08/09）：根 LICENSE / 删除 /src/ / CONTRIBUTING SemVer / 邮箱验证

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 01-02-PLAN.md — composer.json 规范字段补齐（COMPLY-03）+ PackageMetadataTest 扩充
- [x] 01-03-PLAN.md — ServiceProvider 5 个 publishes + lang 骨架（COMPLY-01）
- [x] 01-04-PLAN.md — PublishCommand 真实实现（COMPLY-02）+ 8 项行为断言

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 01-06-PLAN.md — 包 CI 升级到 phpstan/pint/audit + PHP matrix（COMPLY-05）

---

### Phase 2: 文档与品宣

**Goal**: 包 README 完整重写，wiki/installation.md 可独立引导新用户从零安装，CHANGELOG / UPGRADING 规范齐全，旧坐标批量替换，让"装下来"的用户能顺利完成配置并愿意点 Star
**Depends on**: Phase 1
**Requirements**: DOC-01, DOC-02, DOC-03, DOC-04, DOC-05, DOC-06, DOC-07, DOC-08
**Work estimate**: 约 12h（M8 4h + M9 0.3h + M10 2h + M12 1.5h + M13 2h + M14 2h + DOC-07 0.5h + DOC-08 0.5h）

**Success Criteria**（以下全部为 TRUE 才算 Phase 2 完成）:

1. `packages/filament-admin/README.md` 第一屏包含项目定位 + 至少 1 张截图（登录页或后台首页）、5 个 Badges（Packagist version/downloads/PHP/License/Tests）、5 行可复制的 Quick Start 代码块（含 vendor:publish + migrate + seed + 默认账号说明）
2. 一个完全没有读过本项目的开发者，只跟着 `wiki/installation.md`，能在干净 Laravel 13 环境中完成安装并以 `admin@example.com / password` 登录后台（Prerequisites 表 + Quick Start + AdminPanelProvider 示例三者齐全）
3. 根 `CHANGELOG.md` 和 `packages/filament-admin/CHANGELOG.md` 均符合 Keep-a-Changelog 1.1.0 格式（Added/Changed/Fixed 分组；包含 `[Unreleased]` 段；v0.4.0/v0.4.1 历史内容已补齐）
4. `UPGRADING.md` 存在于根目录，列出 v0.4 → v0.5 的 breaking changes（包含 vendor:publish 新增 5 tag、PublishCommand 新参数、配置文件变化）
5. `docs/` 和 `wiki/` 中不再出现旧坐标 `filament-admin/filament-admin`（全部替换为 `laravelstack/filament-admin`）；`CONTRIBUTING.md` 含"本地 3380 / CI 3306"端口差异说明

**Plans**: 3 plans
Plans:
**Wave 1**

- [x] 02-01-PLAN.md — 资产与配置基线（DOC-02 .env.example 修正 / DOC-07 旧坐标替换 / art/dashboard.png 截图复制，README 截图引用前置）

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 02-02-PLAN.md — 包对外文档（DOC-01 包 README 重写 / DOC-04 包 CHANGELOG 全量回填 / DOC-05 包 UPGRADING，随 split 进 Packagist，相对路径自包含）
- [x] 02-03-PLAN.md — 根仓库文档（DOC-03 wiki/installation.md 完整化 / DOC-06 根 README 改写含登录页截图 TODO 占位 / DOC-04 根 CHANGELOG / DOC-05 根 UPGRADING / DOC-08 CONTRIBUTING CI 端口）

---

### Phase 3: 包功能补强

**Goal**: 集成 User Impersonation、Scramble API 自动文档、CRUD 生成器四件套，补齐 kaido-kit 已有但本包缺失的开发者工具能力
**Depends on**: Phase 2
**Requirements**: FEAT-01, FEAT-02, FEAT-03
**Work estimate**: 约 14h（Impersonation 2-3h + Scramble 1-2h + CRUD 生成器 8-12h）

**Success Criteria**（以下全部为 TRUE 才算 Phase 3 完成）:

1. 超管在 AdminUserResource 列表页能看到"模拟登录"按钮，点击后切换为目标用户身份，顶栏显示"正在模拟 {username}（结束模拟）"提示，点击结束后回到超管会话；本次模拟操作在 activity log 中有记录
2. 访问 `/docs/api` 返回 200，展示由 dedoc/scramble 自动生成的 OpenAPI 3.0 文档界面，其中包含已有的 Sanctum API 路由（admin/api/v1）
3. 执行 `php artisan make:filament-admin-model Product` 生成 `app/Models/Product.php`（命名空间正确）；`make:filament-admin-resource ProductResource` 生成对应 Filament Resource 文件；`make:filament-admin-migration` 生成 migration stub；`make:filament-admin-test` 生成 Feature Test stub；四个命令各自有对应 PHPUnit 测试覆盖

**Plans**: 4 plans
Plans:
**Wave 1**

- [x] 03-01-PLAN.md — 抽取 StubGenerator 共享服务 + 重构 PublishCommand 委托（FEAT-03 基石，保持 Phase 1 公开契约不变）
- [x] 03-03-PLAN.md — Impersonation：require stechstudio/filament-impersonate + AdminUserResource Action（仅超管）+ ImpersonationListener 接入 ActivityLogger（FEAT-01）
- [x] 03-04-PLAN.md — Scramble API 文档（演示项目根）：dedoc/scramble + config/scramble.php + 路由过滤，与 Scribe 共存（FEAT-02，含人工验证 checkpoint）

**Wave 2** *(blocked on 03-01 completion)*

- [x] 03-02-PLAN.md — make:filament-admin-{model,resource,migration,test} 四命令薄包装 + 注册 + 四个 PHPUnit 测试（FEAT-03）

---

### Phase 4: 发布自动化

**Goal**: GitHub Actions release workflow 能在 push tag 时全自动完成 subtree split + 推包仓库 + 创建 Release + 验证安装；发版脚本三件套覆盖正常发版和回滚；CI 安全审计和覆盖率上报到位
**Depends on**: Phase 3
**Requirements**: RELEASE-01, RELEASE-02, RELEASE-03, RELEASE-04, RELEASE-05, RELEASE-06
**Work estimate**: 约 13-14h（S1 release.yml 6h + S2 脚本三件套 4h + S3 0.3h + S4 0.3h + S5 1.5h + RELEASE-06 干净环境手动接收测试 1-2h，不含卡点修复）

**Success Criteria**（以下全部为 TRUE 才算 Phase 4 完成）:

1. 向主仓库 push `v0.5.0` tag 后，GitHub Actions `release.yml` 自动运行完毕（不需人工干预），最终在 `github.com/john-captain/filament-admin` 包仓库出现同名 tag，并在主仓库 GitHub Releases 页面出现 v0.5.0 release 条目（含 CHANGELOG 提取的版本说明）
2. 执行 `scripts/release-package.sh v0.5.0` 能按 PRD 07 发布链路 2.1-2.7 顺序完成全部步骤；若任一步骤失败，脚本以非 0 退出码终止（`set -e`）；执行 `scripts/release-rollback.sh v0.5.0` 能删除本地 + GitHub + Gitee 上的同名 tag
3. 根 CI 在每次 PR 运行后，`composer audit --abandoned=report` 步骤结果可见（失败为 warning 不阻塞 CI，但在 Actions summary 中有输出）
4. 根 CI 和包 CI 中不再有 `APP_KEY: base64:AAAA...` 硬编码占位符（改为 secret 引用或 `php artisan key:generate --show` 动态生成）
5. PR/push 后 Codecov 页面能看到本仓库的覆盖率报告；`packages/filament-admin/README.md` 的 Codecov 徽章链接有效且显示当前覆盖率数值
6. **v0.5 出版闸门**：在 `/tmp/v0.5-acceptance` 干净 Laravel 13 环境（`composer create-project` 起新项目）以新用户身份执行 `composer require laravelstack/filament-admin` 并严格按 wiki/installation.md 走通完整路径，全部 7 项 acceptance 检查（publish 5 tag / migrate-seed / 登录 / Impersonation / Scramble / `make:filament-admin-resource` / `filament-admin:publish`）通过；`/tmp/v0.5-acceptance-log.md` 记录无 blocker — **此项不通过不能打 v0.5.0 正式 tag**

**Plans**: 4 plans
Plans:
**Wave 1**

- [x] 04-01-PLAN.md — release.yml 全自动发版 workflow（subtree split → 推 GitHub 包仓库 → 打 tag → gh release create → verify tag/Packagist）（RELEASE-01）
- [x] 04-02-PLAN.md — 发版脚本三件套 scripts/{release-package,verify-package-install,release-rollback}.sh（含 Gitee 推送）（RELEASE-02）
- [x] 04-03-PLAN.md — 根 CI 补 composer audit + APP_KEY 改 secret 引用 + 测试断言 + AGENTS.md 发版段 + 包 README Codecov TODO 占位（RELEASE-03/04/05）

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 04-04-PLAN.md — RELEASE-06 干净环境 7 项 acceptance 人工验收（v0.5 出版闸门，autonomous: false）

---

### Phase 5: 演示站

**Goal**: demo.xitongapp.com 自动部署当前代码，每日凌晨重置数据，高危操作被屏蔽，README 加 demo 链接，让评估者无需本地安装即可体验后台全貌
**Depends on**: Phase 4（发布自动化，但可并行开发 — 演示站部署不依赖 release.yml 完成）
**Requirements**: DEMO-01, DEMO-02, DEMO-03, DEMO-04
**Work estimate**: 约 4-6h（部署 CI 1.5h + demo:reset 命令 1h + 高危屏蔽中间件 1.5h + README demo 链接 0.5h）

**Success Criteria**:

1. 访问 https://demo.xitongapp.com 能看到后台登录页，使用 `demo@example.com / demo123` 登录成功进入后台首页
2. 演示账号下尝试删除管理员或角色时，系统返回友好提示"演示环境屏蔽此操作"而不是实际执行删除
3. 每天凌晨 4 点 cron 执行 `php artisan demo:reset` 后，后台数据恢复到初始演示状态
4. `README.md`、`packages/filament-admin/README.md`、`wiki/index.md` 顶部均包含 demo 链接和演示账号说明

**Plans**: 4 plans
Plans:
**Wave 1**

- [x] 05-01-PLAN.md — DemoSeeder + demo:reset 命令（含生产护栏）+ cron 04:00（DEMO-02 / DEMO-03 演示账号）
- [x] 05-03-PLAN.md — deploy.sh 补前端构建 + 清理冲突文件 + Gitee/环境人工前置（DEMO-01，autonomous: false）
- [x] 05-04-PLAN.md — 三处 README/wiki 顶部加 demo 链接与演示账号（DEMO-04）✓

**Wave 2** *(blocked on 05-01 completion)*

- [x] 05-02-PLAN.md — Gate::before 演示拒绝分支（演示判定先于超管放行，[BLOCKING]）+ isDemoUser/isWriteAbility（DEMO-03 高危屏蔽）

---

### Phase 6: 插件市场 + 对外展示

**Goal**: 插件启停真实控制后台导航/Resource/Page，安装链路支持迁移+发布+种子且有进度反馈，失败可重试，有安全校验和依赖阻断；同步完成官网、Release Notes、Settings 补齐
**Depends on**: Phase 5（演示站上线后官网/市场才有对外入口），Phase 4（发布自动化）
**Requirements**: PLUGIN-01~08, FINAL-02~05
**Work estimate**: 约 26-33h（FINAL-01 已在 Phase 4 完成，本期移除）

> **代码现状（2026-06-11 核查）**
> - FINAL-01（CI audit 步骤）：✅ 已在 Phase 4 完成，`.github/workflows/ci.yml` 第 66-68 行，本期移除
> - FINAL-04（导出功能）：`AdminUserExporter`、`DepartmentExporter`、`LoginLogExporter` + 字段白名单已存在；缺权限点授权和 ActivityLogger 写入
> - PLUGIN-01~08：无任何实现（无 Plugin 模型、无 PluginResource、无 plugins 迁移）

**插件市场 (PLUGIN) — Success Criteria:**

1. 在后台启用一个已安装插件后，其声明的导航条目、Resource、Page、Widget 真实出现在后台左侧菜单中；禁用后立即消失
2. 方案型插件安装后点击"初始化"，自动执行迁移、资源发布、种子数据，初始化结果以日志形式展示
3. 初始化失败时保留错误日志，详情页出现"重试初始化"按钮；重试时跳过已成功的步骤
4. 安装/初始化过程中，后台页面以 Livewire 轮询或流式方式实时显示当前步骤和进度
5. 安装前校验插件包来源签名或 Composer 完整性；不通过则阻断安装并提示原因
6. 安装/卸载插件时，若存在依赖关系，给出阻断提示或风险警告
7. 官方市场浏览不将未安装条目写入 MySQL（使用 HTTP 缓存）；仅用户实际安装的插件写入本地数据库
8. UI 文案区分"浏览官方市场"、"扫描已安装插件"、"安装插件"

**对外展示 (FINAL) — Success Criteria:**

9. 官网 `filamentadmin.com`（或 `www.xitongapp.com`）部署基础页面（项目定位、功能清单、安装指引、演示站链接）（FINAL-05）
10. 根目录 `RELEASE_NOTES.md` 含 v0.5.0 发布说明（FINAL-02）
11. `GeneralSettings` 新增 `logo_url` 和 `contact_email` 字段（FINAL-03；当前仅有 `site_name`、`admin_title`、`icp_number`、`copyright`）
12. 三个现有 Exporter（AdminUser/Department/LoginLog）补充独立权限点授权（`->authorize()`）和导出操作写入 ActivityLogger；Menu/Media Resource 暂不添加导出（FINAL-04）

**Plans**: 4 plans
Plans:
**Wave 1**

- [x] 06-01-PLAN.md — 数据契约与测试地基：plugins 表迁移（演示项目）+ Plugin 模型/Factory + 6 权限点 Seeder 扩充 + 8 个 Wave 0 测试桩（PLUGIN-01/02/03/05/06/07）

**Wave 2** *(blocked on 06-01；02 与 03 文件无重叠可并行)*

- [x] 06-02-PLAN.md — 服务层：MarketplaceService（HTTP 缓存浏览不写库）+ PluginManager（启停/同步初始化/包名校验/依赖检查）+ plugin:scan 命令（PLUGIN-02/03/05/06/07）
- [x] 06-03-PLAN.md — 对外展示 FINAL：GeneralSettings 加 logo_url/contact_email + 三 Exporter 授权+审计 + RELEASE_NOTES.md + 官网 landing 占位页（FINAL-02/03/04/05）

**Wave 3** *(blocked on 06-01 + 06-02)*

- [x] 06-04-PLAN.md — UI 与生效层：PluginPolicy + PluginResource（启停/扫描）+ ViewPlugin（初始化+wire:poll进度+重试）+ MarketplacePage（浏览不写库+composer 复制命令）+ AdminPanelProvider 动态注册接入（PLUGIN-01/02/03/04/08）

---

### Phase 7: 质量基座

**Goal**: 先稳固后扩展 — 修复 7 个已知 Bug，补齐 4 项一期"已铺垫但未闭环"的能力（密码重置、2FA强制、数据权限、日志策略），让基础管理功能完全收敛
**Depends on**: Phase 1（Bug 和补缺项均基于一期代码）
**Requirements**: FIX-01~07, POLISH-01~04
**Work estimate**: 约 14-18h（Bug修复 8-10h + 密码重置 2h + 2FA强制 1.5h + 数据权限 2h + 日志策略 0.5h）

> **代码现状（2026-06-11 核查）**
> - FIX-02：UI 的 link_type Radio + 条件显示已有，但 `menus` 表无 `link_type` 列，Menu model 无该属性，实际未持久化
> - FIX-05：MenuResource 使用独立 MenuTree 页面，无 Filament 拖拽排序；DepartmentResource 有 `rememberReorderSnapshot`/`logReorderActivity`/`buildReorderSnapshot` 三个私有方法但未抽取为 Trait
> - FIX-07：`AdminUserPolicy::assignRole()` 已存在（第 27-30 行），Resource 第 103 行仍用内联 `can('assign_role_admin_user')` 未调用 Policy
> - POLISH-04：`LogSettings` 已有 `activity_log_retention_days` 和 `login_log_retention_days` 字段；两个命令使用硬编码 `--days` 默认值，未读取 Settings

**Bug 修复 (FIX-01~07)**:

1. `AdminNavigationBuilder` 正确处理 `parent_id = null` 的顶级菜单组（当前第 32 行硬编码 `where('parent_id', 0)`）；`Menu::defaultParentKey()` 返回 `null` 而非 `0`；无子菜单的顶级组显示为可点击导航项而不被跳过
2. `menus` 表新增 `link_type` 列（迁移）；`Menu` model 补充 `link_type` 属性和 cast；`MenuResource` 编辑表单的 Radio 字段去掉 `->dehydrated(false)` 改为正常持久化，`defaultValue` 改从数据库读取
3. `MenuResource` 批量启用/禁用 BulkAction 增加 `->visible(fn () => auth('admin')->user()?->can('update_menu'))` 权限检查
4. `LoginLogResource` 删除第 43-64 行冗余 `form()` 方法（`canCreate() = false` 保留）
5. `DepartmentResource` 的 `rememberReorderSnapshot`、`logReorderActivity`、`buildReorderSnapshot` 三个方法抽取为 `ReorderableWithLog` Trait；MenuResource 已使用独立 MenuTree 页面，不适用此 Trait
6. `DepartmentResource::getPages()` 补充 `'view' => Pages\ViewDepartment::route('/{record}')`，新建 `ViewDepartment` 页面类
7. `AdminUserResource` 第 103 行 `->visible()` 改为调用已有的 `AdminUserPolicy::assignRole()`，移除内联 `can('assign_role_admin_user')`

**一期补缺 (POLISH-01~04)**:

8. 登录页有"忘记密码"链接 → ForgotPassword 页面（输入邮箱→发送重置链接）→ ResetPassword 页面完成密码重置；流程走已配置的 `admin_users` broker（`config/auth.php` 已有）
9. `SecuritySettings.force_2fa = true` 时，未启用 2FA 的管理员登录后被拦截到 2FA 设置页，无法访问任何后台页面（当前 `force_2fa` 字段和 UI 已有，缺拦截 Middleware 或 Panel 认证钩子）
10. 普通管理员访问部门列表、菜单列表时，仅能看到自己权限范围内的数据；`DepartmentResource::getEloquentQuery()` 和 `MenuResource::getEloquentQuery()` 补充数据范围过滤；超管不受限制（AdminUserResource/LoginLogResource 已有类似实现可参考）
11. `CleanActivityLogs::handle()` 和 `CleanLoginLogs::handle()` 改从 `LogSettings` 读取保留天数（`$settings->activity_log_retention_days`、`$settings->login_log_retention_days`），`--days` 参数保留作为覆盖值

**Plans**: 6 plans
Plans:
**Wave 1**

- [x] 07-01-PLAN.md — FIX-01/04/07：顶级导航组处理 + 删 LoginLogResource 冗余 form() + 角色分配改调 AdminUserPolicy
- [x] 07-02-PLAN.md — FIX-02：菜单 link_type 落库（menus 加列 + Menu 属性/cast + MenuResource Radio 去 dehydrated）

**Wave 2** *(blocked on 07-02 — 共享 MenuResource.php)*

- [x] 07-03-PLAN.md — FIX-03/05/06：菜单批量启停加权限 + 抽 ReorderableWithLog Trait + DepartmentResource 补 ViewDepartment 页

**Wave 3** *(blocked on FIX 批完成 — D-10 先 FIX 后 POLISH)*

- [x] 07-04-PLAN.md — POLISH-04 日志清理读 LogSettings + POLISH-01 管理员密码重置闭环（admin_users broker）

**Wave 4**

- [x] 07-05-PLAN.md — POLISH-03：部门列表数据范围（本部门+子树，菜单不做范围过滤，per D-03）
- [x] 07-06-PLAN.md — POLISH-02：强制 2FA（超管也强制 per D-04，Filament panel 认证钩子拦截）

---

### Phase 8: 云存储插件

**Goal**: 开发阿里云 OSS 和腾讯云 COS 两个 Filament 插件，覆盖文件上传、预览、删除、磁盘配置，与项目现有媒体库（spatie/laravel-medialibrary）无缝集成
**Depends on**: Phase 1（基础架构），可与 Phase 5/6 并行
**Requirements**: CLOUD-01, CLOUD-02
**Work estimate**: 约 8-12h（OSS 4-6h + COS 4-6h）

**Success Criteria**:

1. 后台媒体管理页面上传文件时，可选择阿里云 OSS 作为存储磁盘，文件成功上传到 OSS Bucket，列表中可预览和下载，删除操作同步移除 OSS 上的文件
2. 后台媒体管理页面上传文件时，可选择腾讯云 COS 作为存储磁盘，文件成功上传到 COS Bucket，列表中可预览和下载，删除操作同步移除 COS 上的文件
3. 两个插件各自提供独立的配置页面（OSS: AccessKey/Secret/Bucket/Endpoint/Region；COS: SecretId/SecretKey/Bucket/Region），配置项写入 `config/filesystems.php` 的 `disks` 段
4. 上传安全：文件大小/MIME/扩展名校验，危险文件拦截，与现有 `UploadSettings` 配置联动

**Plans**: 4 plans
Plans:
**Wave 1**

- [x] 08-01-PLAN.md — 阿里云 OSS 插件包（composer 契约 + OssSettings 加密 + OssSettingsPage + Storage::extend('oss')）（CLOUD-01）
- [x] 08-02-PLAN.md — 腾讯云 COS 插件包（composer 契约 + CosSettings 加密 + CosSettingsPage + cos 磁盘注入）（CLOUD-02）

**Wave 2** *(blocked on 08-01 + 08-02)*

- [x] 08-03-PLAN.md — 上传安全校验 UploadValidator（扩展名黑名单 + finfo MIME + 大小，联动 UploadSettings）+ UploadSettingsPage 动态磁盘选项 + medialibrary 磁盘同步（CLOUD-01/02，type: tdd）

**Wave 3** *(blocked on 08-01 + 08-02 + 08-03)*

- [x] 08-04-PLAN.md — Monorepo 集成（根 composer.json repositories + config/settings.php 注册 + 集成测试 + plugin:scan 发现 + medialibrary 端到端断言）（CLOUD-01/02）

---

### Phase 9: 编辑器插件

**Goal**: 开发富文本编辑器和 Markdown 编辑器两个 Filament 表单组件插件，支持图片上传（与媒体库联动）、代码高亮、自定义工具栏
**Depends on**: Phase 1（基础架构），可与 Phase 5/6/8 并行
**Requirements**: EDITOR-01, EDITOR-02
**Work estimate**: 约 8-12h（富文本 4-6h + Markdown 4-6h）

**Success Criteria**:

1. Filament 表单中使用 `RichEditor::make('content')` 即可渲染富文本编辑器（TinyMCE 或 Tiptap），支持图片拖拽上传到媒体库、表格、链接、代码块
2. Filament 表单中使用 `MarkdownEditor::make('content')` 即可渲染 Markdown 编辑器，支持实时预览、工具栏（加粗/斜体/标题/列表/链接/图片/代码）、图片上传到媒体库
3. 两个编辑器均支持配置工具栏按钮、上传磁盘、文件大小限制，与现有 `UploadSettings` 联动
4. 编辑器输出的内容在详情页正确渲染（HTML 安全过滤 / Markdown 转 HTML）

**Plans**: TBD

---

### Phase 10: 官网插件

**Goal**: 开发通用企业官网 Filament 插件，覆盖页面管理（首页/关于/联系等自定义页面）、文章/产品发布、基础 SEO（TDK）、主题切换（深色/浅色/品牌色），作为插件市场的示范插件
**Depends on**: Phase 6（官网骨架 + 插件市场就绪后才能开发插件），可与 Phase 7/8/9 并行
**Requirements**: SITE-01, SITE-02, SITE-03, SITE-04
**Work estimate**: 约 10-15h（页面管理 3h + 文章 3h + SEO 2h + 主题 2h + 前端展示 4h）

**Success Criteria**:

1. 后台"官网管理"菜单下可创建/编辑/删除自定义页面（标题、slug、内容、SEO TDK），前端按 slug 路由展示页面内容
2. 文章管理支持分类、标签、发布时间、置顶，前端列表页 + 详情页模板完整
3. 每篇页面和文章可独立设置 SEO 标题/描述/关键词，前端自动注入 `<meta>` 标签
4. 系统配置中提供主题选项（主色、背景色、字体），前端自动应用；支持一套免费默认主题

**Plans**: TBD

---

### Phase 12: 发版与仓库整理

**Goal**: 修复包仓库内容错误（当前 package-github / package-gitee main 分支是整个 monorepo 而非 subtree split 后的纯包代码），重建正确的 v0.5.0 发版状态
**Depends on**: Phase 11（可单独提前执行）
**Requirements**: RELEASE-07
**Work estimate**: 约 1-2h

**Success Criteria**:

1. 执行 `git subtree split --prefix=packages/filament-admin` 生成纯包 commit，force push 到 `package-github` 和 `package-gitee` main 分支，两端 main 只含包代码
2. 删除两端错误的 `v0.5.0` tag，在 subtree split commit 上重新打 annotated tag `v0.5.0`，推送到两端包仓库
3. `gh release create v0.5.0 --repo john-captain/filament-admin` 创建 GitHub Release，release notes 从 `packages/filament-admin/CHANGELOG.md` 的 `[0.5.0]` 节提取
4. Gitee 包仓库 `v0.5.0` tag 推送完成，Packagist 自动同步（或手动触发）更新为 v0.5.0

**Plans**: TBD

---

### Phase 11: 代码整理收尾

**Goal**: 全面审查已开发功能，修复所有已知和潜在 Bug，统一代码风格，消除重复和冗余，确保测试全覆盖
**Depends on**: Phase 1~10（所有前序工作完成后执行）
**Requirements**: CLEANUP-01, CLEANUP-02, CLEANUP-03
**Work estimate**: 约 15-20h

**Success Criteria**:

1. 所有已知 Bug（FIX-01~07 + 开发过程中新发现的）全部修复，回归测试通过
2. 代码重复消除（Trait 抽取、Service 合并、冗余类删除），PHPStan Level 6 零错误，Pint 零警告
3. 测试覆盖率 ≥ 80%，关键路径（登录/权限/CRUD/插件启停）100% 覆盖；废弃代码和未使用依赖清理

**已登记待修（来自其它阶段评审）**:

- **CR-01（来自 Phase 05 代码评审，见 `.planning/phases/05-demo-site/05-REVIEW.md`）**：DEMO-03 演示写拦截可被 Filament 设置页（`SettingsPage::canEdit()` 硬编码 true）与 Profile 页（`EditProfile::save()` 直接 update，不走 Gate）绕过；且 `isDemoUser()` 仅凭可变的 email 判定，演示用户改邮箱即可整体失配。加固方向：用稳定标识（专门的 `demo` 角色或 DB 标志）识别演示用户 + 在 Profile/各 SettingsPage 的 `save()` 加演示守卫 + 补 Filament Page 绕过的回归测试。当前公网 demo 仅靠每日 04:00 重置兜底。

**Plans**: TBD

---

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. 包发布合规 | 8/8 | Complete    | 2026-06-10 |
| 2. 文档与品宣 | 3/3 | Complete    | 2026-06-10 |
| 3. 包功能补强 | 4/4 | Complete    | 2026-06-11 |
| 4. 发布自动化 | 3/4 | In Progress|  |
| 5. 演示站 | 4/4 | Complete    | 2026-06-11 |
| 6. 插件市场 + 对外展示 | 6/6 | Complete   | 2026-06-12 |
| 7. 质量基座 | 6/6 | Complete    | 2026-06-12 |
| 8. 云存储插件 | 4/4 | Complete   | 2026-06-12 |
| 9. 编辑器插件 | 0/? | Not started | - |
| 10. 官网插件 | 0/? | Not started | - |
| 11. 代码整理收尾 | 0/? | Not started | - |
| 12. 发版与仓库整理 | 0/? | Not started | - |

---

## Coverage

**v0.5 Requirements (主线):** 30 / 30 mapped ✓
**v0.6+ Requirements (Phase 5-11):** 39 / 39 mapped ✓
**Total:** 69 / 69 ✓
**Unmapped:** 0

| REQ-ID | Phase | 类别 |
|--------|-------|------|
| COMPLY-01 | Phase 1 | 包发布合规 |
| COMPLY-02 | Phase 1 | 包发布合规 |
| COMPLY-03 | Phase 1 | 包发布合规 |
| COMPLY-04 | Phase 1 | 包发布合规 |
| COMPLY-05 | Phase 1 | 包发布合规 |
| COMPLY-06 | Phase 1 | 包发布合规 |
| COMPLY-07 | Phase 1 | 包发布合规 |
| COMPLY-08 | Phase 1 | 包发布合规 |
| COMPLY-09 | Phase 1 | 包发布合规 |
| DOC-01 | Phase 2 | 文档与品宣 |
| DOC-02 | Phase 2 | 文档与品宣 |
| DOC-03 | Phase 2 | 文档与品宣 |
| DOC-04 | Phase 2 | 文档与品宣 |
| DOC-05 | Phase 2 | 文档与品宣 |
| DOC-06 | Phase 2 | 文档与品宣 |
| DOC-07 | Phase 2 | 文档与品宣 |
| DOC-08 | Phase 2 | 文档与品宣 |
| FEAT-01 | Phase 3 | 包功能补强 |
| FEAT-02 | Phase 3 | 包功能补强 |
| FEAT-03 | Phase 3 | 包功能补强 |
| RELEASE-01 | Phase 4 | 发布自动化 |
| RELEASE-02 | Phase 4 | 发布自动化 |
| RELEASE-03 | Phase 4 | 发布自动化 |
| RELEASE-04 | Phase 4 | 发布自动化 |
| RELEASE-05 | Phase 4 | 发布自动化 |
| RELEASE-06 | Phase 4 | 发布自动化（v0.5 出版闸门）|
| DEMO-01 | Phase 5 | 演示站 — 部署 |
| DEMO-02 | Phase 5 | 演示站 — 数据重置 |
| DEMO-03 | Phase 5 | 演示站 — 高危屏蔽 |
| DEMO-04 | Phase 5 | 演示站 — README链接 |
| FIX-01 | Phase 7 | 质量基座 — NavigationBuilder |
| FIX-02 | Phase 7 | 质量基座 — 菜单link_type |
| FIX-03 | Phase 7 | 质量基座 — 批量操作权限 |
| FIX-04 | Phase 7 | 质量基座 — LoginLog冗余 |
| FIX-05 | Phase 7 | 质量基座 — reorder抽取 |
| FIX-06 | Phase 7 | 质量基座 — Department缺View |
| FIX-07 | Phase 7 | 质量基座 — 角色Policy |
| POLISH-01 | Phase 7 | 质量基座 — 密码重置 |
| POLISH-02 | Phase 7 | 质量基座 — 2FA强制 |
| POLISH-03 | Phase 7 | 质量基座 — 数据权限 |
| POLISH-04 | Phase 7 | 质量基座 — 日志策略 |
| PLUGIN-01 | Phase 6 | 插件市场 — 启停控制 |
| PLUGIN-02 | Phase 6 | 插件市场 — 安装链路 |
| PLUGIN-03 | Phase 6 | 插件市场 — 失败重试 |
| PLUGIN-04 | Phase 6 | 插件市场 — 进度反馈 |
| PLUGIN-05 | Phase 6 | 插件市场 — 安全校验 |
| PLUGIN-06 | Phase 6 | 插件市场 — 依赖阻断 |
| PLUGIN-07 | Phase 6 | 插件市场 — 数据边界 |
| PLUGIN-08 | Phase 6 | 插件市场 — 文案 |
| FINAL-01 | Phase 4 | 对外展示 — CI audit（已完成，Phase 4 ci.yml 中实现）|
| FINAL-02 | Phase 6 | 对外展示 — Release Notes |
| FINAL-03 | Phase 6 | 对外展示 — Settings缺口 |
| FINAL-04 | Phase 6 | 对外展示 — 导出增强 |
| FINAL-05 | Phase 6 | 对外展示 — 官网 |
| CLOUD-01 | Phase 8 | 云存储 — OSS |
| CLOUD-02 | Phase 8 | 云存储 — COS |
| EDITOR-01 | Phase 9 | 编辑器 — 富文本 |
| EDITOR-02 | Phase 9 | 编辑器 — Markdown |
| SITE-01 | Phase 10 | 官网插件 — 页面管理 |
| SITE-02 | Phase 10 | 官网插件 — 文章 |
| SITE-03 | Phase 10 | 官网插件 — SEO |
| SITE-04 | Phase 10 | 官网插件 — 主题 |
| CLEANUP-01 | Phase 11 | 收尾 — Bug全修复 |
| CLEANUP-02 | Phase 11 | 收尾 — 代码统一 |
| CLEANUP-03 | Phase 11 | 收尾 — 测试覆盖 |

---

*Created: 2026-06-09 by gsd-roadmapper*
*Last updated: 2026-06-12 — Phase 08（云存储插件）完成，CLOUD-01/02 全部交付；新增 packages/filament-admin-oss、packages/filament-admin-cos；UploadValidator 三重安全校验；275 tests passing*

## Traceability

Phase 映射（由 ROADMAP.md 阶段细化，此处仅 milestone 视图）。

| Requirement | Phase | Status |
|---|---|---|
| COMPLY-01 | Phase 1 | Complete |
| COMPLY-02 | Phase 1 | Complete |
| COMPLY-03 | Phase 1 | Complete |
| COMPLY-04 | Phase 1 | Complete |
| COMPLY-05 | Phase 1 | Complete |
| COMPLY-06 | Phase 1 | Complete |
| COMPLY-07 | Phase 1 | Complete |
| COMPLY-08 | Phase 1 | Complete |
| COMPLY-09 | Phase 1 | Complete |
| DOC-01 | Phase 2 | Complete |
| DOC-02 | Phase 2 | Complete |
| DOC-03 | Phase 2 | Complete |
| DOC-04 | Phase 2 | Complete |
| DOC-05 | Phase 2 | Complete |
| DOC-06 | Phase 2 | Complete |
| DOC-07 | Phase 2 | Complete |
| DOC-08 | Phase 2 | Complete |
| FEAT-01 | Phase 3 | Complete |
| FEAT-02 | Phase 3 | Complete |
| FEAT-03 | Phase 3 | Complete |
| RELEASE-01 | Phase 4 | Complete |
| RELEASE-02 | Phase 4 | Complete |
| RELEASE-03 | Phase 4 | Complete |
| RELEASE-04 | Phase 4 | Complete |
| RELEASE-05 | Phase 4 | Complete |
| RELEASE-06 | Phase 4 | Complete |
| FIX-01 | Phase 5 | Complete |
| FIX-02 | Phase 5 | Complete |
| FIX-03 | Phase 5 | Complete |
| FIX-04 | Phase 5 | Complete |
| FIX-05 | Phase 5 | Complete |
| FIX-06 | Phase 5 | Complete |
| FIX-07 | Phase 5 | Complete |
| POLISH-01 | Phase 5 | Complete |
| POLISH-02 | Phase 5 | Complete |
| POLISH-03 | Phase 5 | Complete |
| POLISH-04 | Phase 5 | Complete |
| PLUGIN-01 | Phase 6 | Complete |
| PLUGIN-02 | Phase 6 | Complete |
| PLUGIN-03 | Phase 6 | Complete |
| PLUGIN-04 | Phase 6 | Complete |
| PLUGIN-05 | Phase 6 | Complete |
| PLUGIN-06 | Phase 6 | Complete |
| PLUGIN-07 | Phase 6 | Complete |
| PLUGIN-08 | Phase 6 | Complete |
| DEMO-01 | Phase 5 | Complete |
| DEMO-02 | Phase 5 | Complete |
| DEMO-03 | Phase 5 | Complete |
| DEMO-04 | Phase 5 | Complete |
| PLUGIN-01 | Phase 6 | Complete |
| PLUGIN-02 | Phase 6 | Complete |
| PLUGIN-03 | Phase 6 | Complete |
| PLUGIN-04 | Phase 6 | Complete |
| PLUGIN-05 | Phase 6 | Complete |
| PLUGIN-06 | Phase 6 | Complete |
| PLUGIN-07 | Phase 6 | Complete |
| PLUGIN-08 | Phase 6 | Complete |
| FINAL-01 | Phase 6 | Complete |
| FINAL-02 | Phase 6 | Complete |
| FINAL-03 | Phase 6 | Complete |
| FINAL-04 | Phase 6 | Complete |
| FINAL-05 | Phase 6 | Complete |
| FIX-01 | Phase 7 | Complete |
| FIX-02 | Phase 7 | Complete |
| FIX-03 | Phase 7 | Complete |
| FIX-04 | Phase 7 | Complete |
| FIX-05 | Phase 7 | Complete |
| FIX-06 | Phase 7 | Complete |
| FIX-07 | Phase 7 | Complete |
| POLISH-01 | Phase 7 | Complete |
| POLISH-02 | Phase 7 | Complete |
| POLISH-03 | Phase 7 | Complete |
| POLISH-04 | Phase 7 | Complete |
| CLOUD-01 | Phase 8 | Complete |
| CLOUD-02 | Phase 8 | Complete |
| EDITOR-01 | Phase 9 | Pending |
| EDITOR-02 | Phase 9 | Pending |
| SITE-01 | Phase 10 | Pending |
| SITE-02 | Phase 10 | Pending |
| SITE-03 | Phase 10 | Pending |
| SITE-04 | Phase 10 | Pending |
| CLEANUP-01 | Phase 11 | Pending |
| CLEANUP-02 | Phase 11 | Pending |
| CLEANUP-03 | Phase 11 | Pending |

**Coverage:**

- v0.5 requirements: **30 total**（COMPLY 9 + DOC 8 + FEAT 3 + RELEASE 6 + DEMO 4）
- v0.6+ requirements: **24 total**（POLISH 4 + PLUGIN 8 + FIX 7 + FINAL 5）
- Mapped to phases: **54 / 54** ✓
- Unmapped: 0

---

## Status Values

- **Pending**: Not started
- **In Progress**: 所属 phase 处于 active 状态
- **Complete**: 已 ship + 通过验证（Verifier agent + 用户手测）
- **Blocked**: Waiting on external factor

---

*Requirements defined: 2026-06-09*
*Last updated: 2026-06-09 after `/gsd-new-project` initialization*
