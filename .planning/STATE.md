---
gsd_state_version: 1.0
milestone: v0.5
milestone_name: milestone
status: executing
stopped_at: Phase 08 规划完成（4 plans，Wave 1-3），ready to execute
last_updated: "2026-06-12T09:24:13.797Z"
progress:
  total_phases: 12
  completed_phases: 7
  total_plans: 39
  completed_plans: 35
  percent: 58
---

# Project State: FilamentAdmin v0.5

**Milestone:** v0.5 — 让主包"全部完成"形态
**Project:** laravelstack/filament-admin
**Last updated:** 2026-06-12

---

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-12)

**Core value:** 别人执行 `composer require laravelstack/filament-admin` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态符合 Laravel 开源市场规范

**Current focus:** Phase 08 — cloud-storage-plugins

---

## Current Position

Phase: 08 (cloud-storage-plugins) — EXECUTING
Plan: 1 of 4
Status: Executing Phase 08

```
current_phase:   8
current_plan:    0
current_status:  planned
last_updated:    2026-06-12
stopped_at:      Phase 07 全部 6 Plan 完成（FIX-01~07 + POLISH-01~04，202 tests passed，UAT 3/3 通过）
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
Phase 08 [          ]   0%  云存储插件（已规划，4 plans，Wave 1-3）
Phase 09 [          ]   0%  编辑器插件（待规划）
Phase 10 [          ]   0%  官网插件（待规划）
Phase 11 [          ]   0%  代码整理收尾（待规划）
Phase 12 [          ]   0%  发版与仓库整理（待规划）

Overall  [#######   ]  58%  (7/12 phases, 35/35 plans)
```

---

## Phase Summary

| Phase | Name | REQs | Work estimate | Status |
|-------|------|------|---------------|--------|
| 1 | 包发布合规 | COMPLY-01~09 (9) | 10-15h | Completed |
| 2 | 文档与品宣 | DOC-01~08 (8) | ~12h | Not started |
| 3 | 包功能补强 | FEAT-01~03 (3) | ~14h | Not started |
| 4 | 发布自动化 | RELEASE-01~06 (6) | ~13-14h | Not started |
| 5 | 演示站 (v0.5.1) | DEMO-01~04 (4) | 4-6h | Not started |

**Total work estimate:** 约 53-61h（按 4h/周 ≈ 13-15 周）

---

## Performance Metrics

```
phases_complete:    1 / 5
plans_complete:     6 / 6  (Phase 1)
requirements_done:  0 / 30  (COMPLY-01~09 工程落地，测试/文档验收后更新)
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

### Critical Facts

- 主包当前状态：ServiceProvider 5 个 `publishes()` 已注册，PublishCommand 真实实现，EnsureTwoFactorEnabled 中间件已注册
- Phase 07 已通过 UAT（3/3 passed，202 tests）
- 已知旁置 bug：parent_id=0 根菜单通过 Filament 表单保存报 relationship() validation error，推迟 Phase 11 修复
- 工具链：PHP 8.3+, Laravel 13.x, Filament 5.x
- CI：包 CI 用 `packages/filament-admin/.github/workflows/ci.yml`，根 CI 用根目录 workflows
- 默认账号：`admin@example.com / password`（SuperAdminSeeder 创建）

### Active Blockers

无

### Recent Decisions（Phase 07）

- EnsureTwoFactorEnabled 中间件走 Filament panel authMiddleware（非 route group），白名单放行登出/2FA设置/个人资料
- force_2fa=true 时超管未开 2FA 同样被拦（D-04 不豁免超管）
- DepartmentResource.php namespace 修复：从 App\Enums\AdminUserStatus → FilamentAdmin\Enums\AdminUserStatus
- ReorderableWithLog Trait 抽取：避免部门/菜单 reorder 日志逻辑重复

---

## Session Continuity

Last session: 2026-06-12
Stopped at: Phase 08 规划完成（4 plans，Wave 1-3），ready to execute
Resume file: None

**未解决问题:**

- 登录页截图（art/login.png）待用户后补，README 已留显式 TODO 占位
- parent_id=0 根菜单表单保存 bug → Phase 11

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
