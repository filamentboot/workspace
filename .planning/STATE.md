---
gsd_state_version: 1.0
milestone: v0.5
milestone_name: milestone
current_phase: 13
current_phase_name: filamentboot
status: awaiting-user
stopped_at: v0.5.3 已发布——7 个包（主包+cos/oss+rich/markdown/wang editor+site）全部上线 GitHub+Packagist；Phase 21 仅执行安全子集，其余技术债见 BACKLOG
last_updated: "2026-06-24T00:00:00.000Z"
progress:
  total_phases: 7
  completed_phases: 5
  total_plans: 29
  completed_plans: 29
  percent: 71
---

# Project State: FilamentAdmin v0.5

**Milestone:** v0.5 — 让主包"全部完成"形态
**Project:** laravelstack/filament-admin
**Last updated:** 2026-06-12

---

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-12)

**Core value:** 别人执行 `composer require laravelstack/filament-admin` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态符合 Laravel 开源市场规范

**Current focus:** Phase 13 代码层改名+基础设施已完成并验证(350/350)；待用户外部操作(Packagist/仓库/部署/服务器token)及 Make* 命令改名(13-03已推迟)

---

## Current Position

Phase: 13 (filamentboot) — EXECUTING
Plan: 6 of 6
Status: awaiting-user

```
current_phase:   8
current_plan:    4
current_status:  completed
last_updated:    2026-06-12
stopped_at:      Phase 08 全部 4 Plan 完成（CLOUD-01/02 + UploadValidator + monorepo 集成，275 tests passed，UAT 13/13 通过）
```

**Progress bar:**

```
Phase 01 [##########] 100%  包发布合规（6/6）✓
Phase 02 [##########] 100%  文档与品宣（3/3）✓
Phase 03 [##########] 100%  包功能补强（4/4）✓
Phase 04 [##########] 100%  发布自动化（4/4）✓
Phase 05 [##########] 100%  演示站（4/4）✓
Phase 06 [##########] 100%  插件市场启动（4/4）✓
Phase 07 [##########] 100%  质量基座（6/6）✓
Phase 08 [##########] 100%  云存储插件（4/4）✓
Phase 09 [##########] 100%  编辑器插件（4/4）✓
Phase 10 [          ]   0%  官网插件（待规划）
Phase 11 [          ]   0%  调试官网插件（待规划）
Phase 13 [          ]   0%  改名基础设施（待规划）
Phase 21 [##########] 100%  发版前安全加固（收窄）——3 条安全洞修复，随 v0.5.3 发布 ✓
Phase 22 [          ]   0%  发版与仓库整理（待规划）

Overall  [#######    ]  56%  (9/16 phases)
```

---

## v0.5.3 发布记录（2026-06-24）

首次以 `filamentboot/*` 坐标对外发布。**7 个包全部上线 GitHub + Packagist，统一 v0.5.3，均带 README/LICENSE(MIT)：**
`filamentboot/filamentboot`、`-cos`、`-oss`、`-rich-editor`、`-markdown-editor`、`-wang-editor`、`-site`。

- 含发版前 3 条安全加固（$fillable 白名单 / BulkAction 后端鉴权 / 登录失败锁定）。
- 旧包 `laravelstack/filament-admin` 已从 Packagist 删除。
- 因 CI `PACKAGE_GITHUB_TOKEN` 无跨仓库写权限，本次用本地 `git subtree split` 手动发布；**下次发版需先修该 token 或继续手动推**。
- 待办（非紧急，见 `.planning/BACKLOG.md`）：4 个新包的自动更新 webhook 确认、主包 PHPStan L6 / 80% 覆盖率 / CI 覆盖率门禁、性能(N+1/递归/索引)、`.worktrees/` 清理、3 条安全修复的真实 DB 冒烟验证。

---

## Phase Summary

| Phase | Name | REQs | Work estimate | Status |
|-------|------|------|---------------|--------|
| 1 | 包发布合规 | COMPLY-01~09 (9) | 10-15h | Completed |
| 2 | 文档与品宣 | DOC-01~08 (8) | ~12h | Completed |
| 3 | 包功能补强 | FEAT-01~03 (3) | ~14h | Completed |
| 4 | 发布自动化 | RELEASE-01~06 (6) | ~13-14h | Completed |
| 5 | 演示站 | DEMO-01~04 (4) | 4-6h | Completed |
| 6 | 插件市场 + 对外展示 | PLUGIN-01~08, FINAL-02~05 | ~26-33h | Completed |
| 7 | 质量基座 | FIX-01~07, POLISH-01~04 | ~14-18h | Completed |
| 8 | 云存储插件 | CLOUD-01~02 | ~8-12h | Completed |
| 9 | 编辑器插件 | EDITOR-01~02 | ~8-12h | Completed |
| 10 | 官网插件 | SITE-01~04 | ~10-15h | Not started |
| 11 | 调试官网插件 | SITE-DEBUG-01 | TBD | Not started |
| 12 | 插件市场重构 | MKTPLACE-01~09, DOC-09~11 | ~30-40h | Not started |
| 13 | 改名基础设施 | — | ~8-12h | Not started |
| 21 | 代码整理收尾 | CLEANUP-01~03 | ~15-20h | Not started |
| 22 | 发版与仓库整理 | RELEASE-07 | ~1-2h | Not started |

---

## Performance Metrics

```
phases_complete:    8 / 12
plans_complete:     39 / 39
requirements_done:  COMPLY-01~09, DOC-01~08, FEAT-01~03, RELEASE-01~06, DEMO-01~04,
                    PLUGIN-01~08, FINAL-02~05, FIX-01~07, POLISH-01~04, CLOUD-01~02
test_count:         287 / 287 passing（Phase 09 新增 12 tests：8 集成 + 4 wang-editor Feature）
```

---

## Accumulated Context

### Key Decisions (已锁定)

| 决策 | 结论 |
|------|------|
| D1 PublishCommand | 真实实现（选 A，6h），非删命令改文档 |
| D2 业务测试迁移 | 不迁移（选 C），Feature 依赖 PanelProvider 重构成本高 |
| D3 i18n | 推迟到 v1.0（v0.5 连骨架都不做） |
| D4 demo 站 | 不阻塞 v0.5 主线（选 B），单独作为 Phase 5 = v0.5.1 |
| DC1 Social Login | 推迟 v1.0+ |
| DC2 Impersonation | 进 v0.5 Phase 3 |
| DC3 Scramble API | 进 v0.5 Phase 3 |
| DC4 Docker/Sail | 推迟 v1.0+ |
| DC5 CRUD 生成器 | 进 v0.5 Phase 3 |
| Phase 01-package-release-compliance P01 | 5min | 2 tasks | 5 files |
| Phase 01-package-release-compliance P05 | 15min | 3 tasks | 5 files |
| Phase 01-package-release-compliance P06 | 5min | 2 tasks | 1 file |
| Phase 02-documentation-branding P03 | 224 | 4 tasks | 5 files |
| Phase 03-package-feature-enhancement P01 | 25min | 2 tasks | 2 files |
| Phase 03-package-feature-enhancement P03 | 45min | 2 tasks | 8 files |
| Phase 03-package-feature-enhancement P04 | 20min | 2 tasks | 3 files |
| Phase 03-package-feature-enhancement P02 | 40 | 2 tasks | 11 files |
| Phase 05-demo-site P01 | 9 | 2 tasks | 9 files |
| Phase 05-demo-site P04 | 41s | 1 task | 3 files |
| Phase 05-demo-site P02 | 10min | 1 tasks | 2 files |
| Phase 06-plugin-marketplace-launch P01 | 25 | 3 tasks | 12 files |
| Phase 06 P02 | 30 | 2 tasks | 8 files |
| Phase 06 P03 | 25min | 3 tasks | 12 files |
| Phase 06 P04 | 12min | 3 tasks | 10 files |
| Phase 12-filament P00 | 321 | 2 tasks | 8 files |
| Phase 12-filament P02 | 408 | 3 tasks | 7 files |
| Phase 12-filament P03 | 452 | 2 tasks | 4 files |
| Phase 12-filament P05 | 420s | 2 tasks | 9 files |
| Phase 12-filament P04 | 268 | 3 tasks | 3 files |
| Phase 12-filament P04 | 268 | 4 tasks | 3 files |
| Phase 12-filament P06 | 278 | 2 tasks | 3 files |
| Phase 12-filament P07 | 653 | 3 tasks | 4 files |
| Phase 12-filament P08 | 20min | 3 tasks | 3 files |
| Phase 13 P03 | 601 | 3 tasks | 18 files |
| Phase 13 P04 | 4min | 3 tasks | 7 files |
| Phase 13 P05 | 6min | 3 tasks | 3 files |

### Critical Facts

- 主包当前状态：ServiceProvider 5 个 `publishes()` 已注册，PublishCommand 真实实现，EnsureTwoFactorEnabled 中间件已注册
- Phase 07 已通过 UAT（3/3 passed，202 tests）
- Phase 08 已通过 UAT（13/13 must-haves passed，275 tests）
- Phase 09 完成（4/4 plans，287 tests passing）
- 新增包：`packages/filament-admin-oss/`（阿里云 OSS）、`packages/filament-admin-cos/`（腾讯云 COS）
- 新增编辑器包：`packages/filament-admin-rich-editor/`、`packages/filament-admin-markdown-editor/`、`packages/filament-admin-wang-editor/`
- UploadValidator 三重安全校验（扩展名黑名单 + finfo MIME + 大小），与 UploadSettings 联动
- config/purifier.php 含 richeditor 白名单段，解决富文本样式丢失（Pitfall 4）
- ~~已知旁置 bug：parent_id=0 根菜单通过 Filament 表单保存报 relationship() validation error~~ → 2026-06-23 Playwright 走查确认已不复现，根菜单可正常保存（parent=0，Created）
- 工具链：PHP 8.3+, Laravel 13.x, Filament 5.x
- CI：包 CI 用 `packages/filament-admin/.github/workflows/ci.yml`，根 CI 用根目录 workflows
- 默认账号：`admin@example.com / password`（SuperAdminSeeder 创建）

### Active Blockers

无

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260623-kg4 | 清理 Phase 13 改名残留（功能 bug + 品牌字符串 + 全量当前用户文档 + 功能性 settings 默认值/e2e 失效命名空间）；含 plugin:scan 命令名修正 | 2026-06-23 | c236da9 / d481069 / a04a3c3 / b09272d / 8ad1c28 / fa2060c | [260623-kg4-phase13-rename-cleanup](./quick/260623-kg4-phase13-rename-cleanup/) |
| 260624-d80 | 发版前安全加固：$guarded→$fillable 白名单（L-03）+ 菜单 BulkAction 后端鉴权（L-02）+ 登录失败达阈值锁定 Locked（L-04）。分支 security-hardening | 2026-06-24 | ce2bc55 / 66c7e63 / 3704c97 / 0eba312 | [260624-d80-guarded-bulkaction](./quick/260624-d80-guarded-bulkaction/) |

### Recent Decisions（Phase 08）

- filament-admin-oss / filament-admin-cos 作为独立 Composer 包，monorepo 内通过 path repositories 引入
- OssSettings / CosSettings 使用 Spatie laravel-settings 加密存储 AccessKey/SecretKey
- UploadValidator 抽取为主包共享服务，通过 FilamentAdminServiceProvider 绑定到容器
- medialibrary disk_name 与插件 is_enabled 状态同步，禁用插件时自动回落 local 磁盘
- plugin:scan 命令可发现 OSS/COS 插件并写入 plugins 表

---

## Session Continuity

Last session: 2026-06-23T10:58:52.351Z
Stopped at: Phase 21 context gathered
Resume file: .planning/phases/21-code-cleanup/21-CONTEXT.md

**未解决问题:**

- 登录页截图（art/login.png）待用户后补，README 已留显式 TODO 占位

**已解决（2026-06-23）:**

- `Class "FilamentAdmin\Models\AdminUser" not found`（登录即报错）：Phase 13 改名遗漏运行时状态。修复 = ①迁移数据库多态列旧类名（`model_has_roles.model_type`、`activity_log.causer_type/subject_type`、`media.model_type` 共 184 行 `FilamentAdmin\Models\*` / `App\Models\AdminUser` → `Filamentboot\Models\*`）②`composer dump-autoload -o` 重建 classmap ③`optimize:clear`。仅改数据/vendor，无源码改动。
- parent_id=0 根菜单表单保存 bug → 经 Playwright 走查确认已不复现，从 Phase 21 待办移除。
- 全站 Playwright 走查（16 列表/设置页 + 8 create/edit 表单）零异常。

---

*State initialized: 2026-06-09 by gsd-roadmapper*

## Decisions

- [Phase 01-package-release-compliance]: phpstan paths 包内化为 [src, tests]，适配包仓库结构
- [Phase 01-package-release-compliance]: pint.json 镜像同步策略：与根目录 diff 为空，防止格式风格漂移
- [Phase 01-package-release-compliance]: 测试骨架使用 markTestIncomplete 中文占位，Plan 03/04 实现完成后 in-place 替换为真实断言
- [Phase 01-package-release-compliance P05]: COMPLY-09 邮箱验证结果 KEEP_BOTH — security@xitongapp.com 与 conduct@xitongapp.com 均可达
- [Phase 01-package-release-compliance P05]: 根 /src/ 孤儿以包内 packages/filament-admin/src/ 为准直接删除，不回灌内容
- [Phase 01-package-release-compliance P05]: CONTRIBUTING SemVer 规范确立 vX.Y.Z 字面格式，历史中文后缀 tag 保留不删
- [Phase 01-package-release-compliance P02]: orchestra/testbench 约束升级 ^10.0 → ^11.0 以兼容 Laravel 13（预存在 bug，Rule 1 修复）
- [Phase 01-package-release-compliance P02]: PackageMetadataTest 模式确立——纯 PHPUnit TestCase 文件断言，每个 COMPLY-0x 字段对应独立方法
- [Phase 01-package-release-compliance P03]: lang tag 使用两条精确子目录 publishes（en/zh_CN），避免误发 2FA vendored 翻译（Pitfall 4）
- [Phase 01-package-release-compliance P03]: publishesMigrations() 与 loadMigrationsFrom() 并存——前者供用户显式复制，后者保障零配置体验
- [Phase 01-package-release-compliance P03]: ServiceProvider::pathsToPublish() + getPackageProviders() 确立为 publish 注册测试标准模式
- [Phase 01-package-release-compliance P04]: skip 文件时命令返回 SUCCESS（D-02 语义：跳过非失败，与 vendor:publish 行为对齐）
- [Phase 01-package-release-compliance P04]: Page 模板内嵌于 PublishCommand 类（HEREDOC），不新建 stubs/Page.stub，保持 stubs 目录纯净
- [Phase 01-package-release-compliance P04]: testbench 隔离用 getApplicationBasePath() + 手动创建 skeleton 目录，tearDown 用原生 PHP 删除目录
- [Phase 01-package-release-compliance P06]: CI 矩阵 fail-fast: false 确保一个 PHP 版本失败不取消另一版本
- [Phase 01-package-release-compliance P06]: pint:test 不设 continue-on-error（格式与 PHP 版本无关，8.3/8.4 均强制通过）
- [Phase 01-package-release-compliance P06]: COMPLY-05 措辞从"每条失败即 CI 失败"改为区分 phpstan/pint fail 与 audit warning
- [Phase 02-documentation-branding P01]: sed 替换严格限定 5 文件白名单，docs/superpowers/ 保持历史原貌（T-02-02）
- [Phase 02-documentation-branding P01]: art/dashboard.png 保持静态 PNG，随 subtree split 进包仓库（D-21 锁定方案）
- [Phase 02-documentation-branding P03]: wiki/installation.md 为详细安装唯一权威源（D-24），包 README 链接指向此，不在包内复制
- [Phase 02-documentation-branding P03]: 根 CHANGELOG 从 v0.4.0 起，不含 v1.0.0 条目（D-26 / Pitfall 3）
- [Phase 02-documentation-branding P03]: DOC-06 登录页截图 TODO 占位留显式注释，避免 2 张截图要求被静默遗漏
- [Phase ?]: D-28: StubGenerator 抽取为独立服务，参数化所有来自命令选项的值，IO 输出留在命令层
- [Phase ?]: D-31 确认：stechstudio/filament-impersonate v5.5.0 兼容 Filament 5 + admin guard，横幅 zh_CN 覆盖为锁定文案
- [Phase ?]: ImpersonationListener 测试使用纯 PHPUnit TestCase，避免 Orchestra Testbench + FilamentImpersonateServiceProvider 污染全局 Model::booted 状态
- [Phase 03-package-feature-enhancement P04]: D-33：Scramble 仅装演示项目根 require-dev，不进主包，主包不强绑文档工具依赖
- [Phase 03-package-feature-enhancement P04]: D-34：Scramble 与 knuckleswtf/scribe 共存，互不移除，实时 OpenAPI 与静态 HTML 文档互补
- [Phase 03-package-feature-enhancement P04]: D-35：RestrictedDocsAccess 中间件默认启用，生产环境 /docs/api 不可访问
- [Phase 03-package-feature-enhancement P04]: Pitfall 4 缓解：api_path=api/v1 + routes() 回调双保险，精确过滤文档化范围
- [Phase ?]: D-28 单一来源：buildListPageContent/buildCreatePageContent/buildEditPageContent 迁入 StubGenerator，PublishCommand 和 MakeFilamentAdminResourceCommand 均委托调用
- [Phase 04-release-automation P03]: D-44 落地：根 CI APP_KEY env 行从 base64:AAAA 硬编码替换为 ${{ secrets.CI_APP_KEY }}，保留 key:generate 步骤
- [Phase 04-release-automation P03]: D-45 遵守：包 CI 未修改，包测试不依赖 APP_KEY
- [Phase 04-release-automation P03]: RELEASE-03 落地：根 CI 补 composer audit --abandoned=report（continue-on-error: true，仅警告不阻塞）
- [Phase 04-release-automation P03]: D-43 落地：AGENTS.md 发版流程段明确 Gitee 同步为人工步骤，含三脚本用途与两 GitHub Secrets
- [Phase 04-release-automation P03]: D-46 落地：包 README Codecov TODO HTML 注释占位，RELEASE-05 本期跳过
- [Phase ?]: 角色决策锁定：demo 账号挂 super_admin（展示全貌），不新建独立 demo 角色；写操作屏蔽由 Plan 05-02 Gate::before 负责
- [Phase ?]: 重置策略方案 A：migrate:fresh --seed --force（最简幂等），弃用方案 B 选择性 truncate
- [Phase ?]: DemoReset 命令在护栏通过后临时 config(['app.demo'=>true]) 确保 DatabaseSeeder 播种 DemoSeeder
- [Phase ?]: 合并为单一 Gate::before 回调（演示拒绝 + 超管放行），消除多回调注册顺序不确定性（DEMO-03）
- [Phase ?]: JSON 列改用 nullable()：MySQL 8.0 不支持 json->default('[]') 字面量，改为 ->nullable()，模型 casts 透明处理 null→[]
- [Phase ?]: Wave 0 测试桩不含 PLUGIN-04/08：其自动化断言由 Plan 04 grep 覆盖，不创建悬空测试文件
- [Phase ?]: runMigrate/runPublish/runSeeder 拆为受保护方法：允许 partialMock 隔离 Artisan 调用，确保初始化失败路径 init_log 非空可断言
- [Phase ?]: routes/console.php 用 Artisan::registerCommand 注册 ScanPlugins：Laravel 13 无 Kernel.php，app/Console/Commands/ 不自动发现
- [Phase ?]: landing.blade.php 使用 CDN Tailwind：最小占位页与构建工具解耦
- [Phase ?]: ExporterAuthorizationTest 用 Gate::check 直接断言授权行为
- [Phase ?]: ExportAction after() 回调用 activity('admin') 链式调用，withProperties 替代 performedOn
- [Phase 06-plugin-marketplace-launch P04]: Filament 5 Page::$view 为实例属性（非 static），子类用 protected string $view 声明
- [Phase 06-plugin-marketplace-launch P04]: AdminPanelProvider 通过 ->tap() + 私有方法 registerEnabledPlugins 动态注册插件（Cache 30s + try/catch Throwable）
- [Phase 06-plugin-marketplace-launch P04]: ReflectionMethod::setAccessible 直接测试私有方法三条分支（命中/过滤/异常）
- [Phase ?]: Wave 0 markTestIncomplete pattern: use $this->markTestIncomplete() in Pest closures
- [Phase ?]: T-12-02-01: validatePackageName strict regex before any Process spawn
- [Phase ?]: buildComposerProcess: array command only, COMPOSER_HOME isolated to tmpdir/PID
- [Phase ?]: postInstall idempotent: migrate failure caught+logged (Pitfall 6), seeders catch per-class, dump-autoload fallback
- [Phase ?]: uninstall: disable()-first before composer remove to prevent class-not-found on next request (Pitfall 5)
- [Phase ?]: DOC-09 uses laravelstack/filament-admin-oss as the worked sample — real post_install block from 12-05 compliance
- [Phase ?]: README 插件生态 section appended above 许可证, listing 6 first-party plugins + links to plugin-development.md and plugin-usage.md
- [Phase ?]: 12-08: ->viteTheme() custom theme chosen over blade rewrite — lower regression risk, canonical Filament v5 mechanism
- [Phase ?]: splitsh-lite v1.0.1 matrix rewrite of release.yml
- [Phase ?]: Gitee mirroring via GitHub Actions
- [Phase ?]: SECRETS-CHECKLIST.md produced for user
- [Phase Phase 13 P05]: D-12: local remotes reconfigured to filamentboot/workspace; D-21/D-25: Task 3 GATED on Packagist
