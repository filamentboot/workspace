---
phase: 04-release-automation
plan: "03"
subsystem: ci-cd
tags: [github-actions, security-audit, app-key-secret, agents-md, codecov-placeholder]
dependency_graph:
  requires:
    - phase: 04-release-automation/04-01
      provides: release-workflow
    - phase: 04-release-automation/04-02
      provides: release-scripts-trio
  provides:
    - root-ci-security-hardened
    - app-key-non-hardcoded
    - release-flow-documented
  affects:
    - .github/workflows/ci.yml
    - tests/Unit/Release/DemoAppPackageBridgeTest.php
    - AGENTS.md
    - packages/filament-admin/README.md
tech-stack:
  added: []
  patterns:
    - tdd-file-assertion（PHPUnit 断言 YAML 文件内容，非硬编码校验）
    - continue-on-error-warning-step（audit step 警告不阻塞 CI）
    - gitignored-tracked-file-force-add（/AGENTS.md 被 gitignore 排除但为已跟踪文件，git add -f 处理）

key-files:
  created: []
  modified:
    - .github/workflows/ci.yml
    - tests/Unit/Release/DemoAppPackageBridgeTest.php
    - AGENTS.md
    - packages/filament-admin/README.md

key-decisions:
  - "D-44 落地：根 CI APP_KEY env 行从 base64:AAAA 硬编码替换为 ${{ secrets.CI_APP_KEY }}"
  - "D-45 遵守：包 CI 未修改，包测试不依赖 APP_KEY"
  - "RELEASE-03 落地：根 CI pint:test 后追加 composer audit --abandoned=report（continue-on-error: true）"
  - "D-43 落地：AGENTS.md 发版流程段明确 Gitee 同步为人工步骤，列出三脚本与两个 GitHub Secrets"
  - "D-46 落地：包 README Codecov 徽章位置插入 TODO HTML 注释占位，RELEASE-05 本期跳过"
  - "AGENTS.md 被根 .gitignore 排除（/AGENTS.md 规则），但为已跟踪文件，用 git add -f 强制暂存"

patterns-established:
  - "TDD 文件断言模式：PHPUnit 纯 TestCase 读取 YAML 文件内容，assertStringNotContainsString + assertStringContainsString，无需 Laravel 环境"
  - "CI secret 引用：env 块中敏感值统一用 ${{ secrets.XXX }} 形式，绝不硬编码占位符"

requirements-completed: [RELEASE-03, RELEASE-04, RELEASE-05]

duration: 182s
completed: "2026-06-11"
---

# Phase 04 Plan 03: 根 CI 安全加固 + 发版文档补全 Summary

**根 CI APP_KEY 从硬编码占位符改为 `${{ secrets.CI_APP_KEY }}` secret 引用，补 `composer audit` 警告步骤，PHPUnit 断言覆盖两项变更，AGENTS.md 新增发版流程段（Gitee 人工同步），包 README 插入 Codecov TODO 占位。**

---

## Performance

- **Duration:** 182s（约 3 分钟）
- **Started:** 2026-06-11T02:16:55Z
- **Completed:** 2026-06-11T02:20:00Z（估算）
- **Tasks:** 2
- **Files modified:** 4

---

## Accomplishments

- RELEASE-04（D-44）：消除根 CI 的 `base64:AAAA` 硬编码 APP_KEY 安全隐患，改为 `${{ secrets.CI_APP_KEY }}` secret 引用；PHPUnit 断言强制保证
- RELEASE-03：根 CI 补 `composer audit --abandoned=report`（continue-on-error: true），供应链安全审计结果在 CI Actions summary 可见
- D-43：AGENTS.md 新增「发版流程（Release）」段，明确 Gitee 同步为人工步骤、三脚本用途、release.yml 自动触发链路、两个 GitHub Secrets
- RELEASE-05（D-46）：包 README Codecov 徽章位置加 TODO HTML 注释占位，原 5 个徽章保留

---

## Task Commits

TDD 任务含两次提交（RED → GREEN）：

1. **Task 1 RED: 新增两个失败测试** - `8fa4324` (test)
2. **Task 1 GREEN: 根 CI 安全加固实现** - `4e5061e` (feat)
3. **Task 2: AGENTS.md 发版流程段 + README Codecov 占位** - `c5f06af` (docs)

**Plan metadata:** 待提交 (docs: complete plan)

_注：Task 1 为 TDD 任务，RED commit（8fa4324）先于 GREEN commit（4e5061e）_

---

## Files Created/Modified

- `.github/workflows/ci.yml` — APP_KEY env 改 secret 引用 + 追加 audit step（continue-on-error: true）
- `tests/Unit/Release/DemoAppPackageBridgeTest.php` — 新增两个断言方法（test_root_ci_app_key_is_not_hardcoded、test_root_ci_has_security_audit_step）
- `AGENTS.md` — 新增「## 发版流程（Release）」段（含 Gitee 人工同步说明 + 三脚本用途 + 两个 Secrets）
- `packages/filament-admin/README.md` — Tests 徽章后插入 Codecov TODO HTML 注释占位

---

## Decisions Made

- D-44：根 CI APP_KEY env 行替换为 `${{ secrets.CI_APP_KEY }}`，保留 `php artisan key:generate` 步骤（写入 .env，流程正确）
- D-45 遵守：包 CI 未修改，包测试不依赖 APP_KEY
- AGENTS.md 被根 `.gitignore` 的 `/AGENTS.md` 规则排除，但为已跟踪历史文件，使用 `git add -f` 强制暂存（属实 gitignore 配置与跟踪文件冲突的已知情况，-f 是正确处理方式）

---

## Deviations from Plan

无技术偏差。一个操作细节：

**AGENTS.md gitignore 规则冲突**
- **发现于：** Task 2 提交阶段
- **情况：** 根 `.gitignore` 含 `/AGENTS.md` 规则，导致 `git add AGENTS.md` 提示"ignored by .gitignore"
- **处理：** AGENTS.md 为已跟踪历史文件，使用 `git add -f AGENTS.md` 强制暂存并提交（-f 对已跟踪文件是正确做法，不违反安全规范）
- **分类：** 操作注意事项，非功能偏差

---

## Issues Encountered

无。所有测试在 RED 阶段按预期失败，在 GREEN 阶段按预期通过。

---

## User Setup Required

在 CI 真实触发前，需在 GitHub 主仓库配置：

| Secret | 说明 | 配置位置 |
|--------|------|---------|
| `CI_APP_KEY` | 合法的 base64 Laravel APP_KEY（`php artisan key:generate --show` 生成） | 主仓库 GitHub Settings → Secrets and variables → Actions → New repository secret |

注：`PACKAGE_GITHUB_TOKEN` 由 04-01 计划的 user_setup 负责，此处不重复。

---

## Threat Surface Scan

本计划消除了安全隐患，未引入新的威胁面：

| 威胁处置 | 状态 |
|---------|------|
| T-04-02：ci.yml APP_KEY 硬编码 → `${{ secrets.CI_APP_KEY }}`，PHPUnit 断言强制 | 已缓解 |
| T-04-AUD：composer audit 补全，供应链 CVE 可见性（warning-only） | 已缓解 |
| T-04-DOC：AGENTS.md 发版约定记录，Gitee 人工步骤明确 | 已缓解 |

---

## Known Stubs

无。本计划无 UI 渲染或数据绑定场景。

---

## Next Phase Readiness

- Phase 04 前三个计划（04-01、04-02、04-03）全部完成
- 剩余：04-04（RELEASE-06，v0.5 出版前手动接收测试，人工任务）
- CI 根目录安全配置就绪，等待用户配置 `CI_APP_KEY` secret 后 CI 可正常运行

---

## Self-Check

```
[x] .github/workflows/ci.yml 不含 base64:AAAA（grep 验证为 0）
[x] .github/workflows/ci.yml 含 secrets.CI_APP_KEY
[x] .github/workflows/ci.yml 含 composer audit --abandoned=report
[x] .github/workflows/ci.yml 含 continue-on-error: true（audit step）
[x] .github/workflows/ci.yml 含 php artisan key:generate（未误删）
[x] .github/workflows/ci.yml 为合法 YAML（python3 yaml.safe_load 通过）
[x] 包 CI 未修改（D-45）
[x] DemoAppPackageBridgeTest 4 个测试全部 GREEN（含 2 个新方法）
[x] AGENTS.md 含 ## 发版流程（Release）段
[x] AGENTS.md 含 release-package.sh
[x] AGENTS.md 含 Gitee 同步是人工步骤
[x] AGENTS.md 含 PACKAGE_GITHUB_TOKEN 与 CI_APP_KEY
[x] 包 README 含 Codecov + TODO HTML 注释
[x] 包 README 保留 5 个 img.shields.io 徽章
[x] commit 8fa4324 存在（RED test）
[x] commit 4e5061e 存在（GREEN feat）
[x] commit c5f06af 存在（docs）
```

## Self-Check: PASSED

---

*Phase: 04-release-automation*
*Completed: 2026-06-11*
