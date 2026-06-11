---
gsd_state_version: 1.0
milestone: v0.5
milestone_name: milestone
status: Ready to plan
last_updated: "2026-06-11T01:09:44.775Z"
progress:
  total_phases: 5
  completed_phases: 3
  total_plans: 15
  completed_plans: 15
  percent: 60
---

# Project State: FilamentAdmin v0.5

**Milestone:** v0.5 — 让主包"全部完成"形态
**Project:** laravelstack/filament-admin
**Last updated:** 2026-06-10

---

## Project Reference

**Core value:** 别人执行 `composer require laravelstack/filament-admin` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态符合 Laravel 开源市场规范

**Current focus:** Phase 03 — package-feature-enhancement

---

## Current Position

Phase: 4
Plan: Not started

```
current_phase:   3
current_plan:    4
current_status:  completed
last_updated:    2026-06-10
resume_file:     .planning/phases/03-package-feature-enhancement/03-04-SUMMARY.md
stopped_at:      Plan 03-04 完成：dedoc/scramble 引入演示项目 + config/scramble.php api/v1 路由过滤 + RestrictedDocsAccess + /docs/api 人工验证通过（FEAT-02）
```

**Progress bar:**

```
Phase 1 [##########] 100%  包发布合规（完成）
Phase 2 [##########] 100%  文档与品宣（全部完成，3/3）
Phase 3 [######    ]  75%  包功能补强
Phase 4 [          ]   0%  发布自动化
Phase 5 [          ]   0%  演示站 (v0.5.1)

Overall [#####     ]  43%  (2/5 phases + 3/4 plans in Phase 3)
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

### Critical Facts

- 主包当前状态：ServiceProvider **5 个 `publishes()` 已注册（Plan 01-03 完成）**，PublishCommand **真实实现（Plan 01-04 完成）**
- Phase 1 是阻塞项：未完成前不能发 v0.5
- Phase 5 (演示站) 不阻塞 v0.5 主线，可在 Phase 4 完成后 / v0.5.0 发版后独立推进
- 串行执行：Phase 1 → 2 → 3 → 4 → 5（不并行）
- 工具链：PHP 8.3+, Laravel 13.x, Filament 5.x
- CI：包 CI 用 `packages/filament-admin/.github/workflows/ci.yml`，根 CI 用根目录 workflows
- 默认账号：`admin@example.com / password`（SuperAdminSeeder 创建）

### Active Blockers

无（Phase 1 待开始）

### Todos

- [ ] 开始 Phase 1：先写 `/gsd-plan-phase 1` 生成执行计划
- [ ] Phase 1 完成后验证 5 个 vendor:publish tag 全部可用

---

## Session Continuity

**上次工作:** 2026-06-10 — Plan 03-04 完成：dedoc/scramble 引入演示项目根 require-dev（D-33）、config/scramble.php api_path=api/v1 + RestrictedDocsAccess（D-35）、AppServiceProvider 路由过滤回调（Pitfall 4）、/docs/api 人工验证通过（FEAT-02）

**下次启动时:** 执行 Phase 3 Plan 03-02（CRUD 生成器四命令，Wave 2：make:filament-admin-{model,resource,migration,test}）

**未解决问题:**

- 登录页截图（art/login.png）待用户后补，README 已留显式 TODO 占位

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
