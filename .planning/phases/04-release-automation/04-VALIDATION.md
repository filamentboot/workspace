---
phase: 04
slug: release-automation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-11
---

# Phase 04 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5.x（根目录）|
| **Config file** | `phpunit.xml`（根目录）|
| **Quick run command** | `composer test` |
| **Full suite command** | `composer test` |
| **Estimated runtime** | ~30 秒 |

---

## Sampling Rate

- **After every task commit:** Run `composer test`
- **After every plan wave:** Run `composer test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 60 秒

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 04-??-01 | release.yml 创建 | 1 | RELEASE-01 | T-04-01 | release.yml 不含硬编码 secret | manual (CI) | `gh run list --workflow=release.yml` | ❌ Wave 0 创建 | ⬜ pending |
| 04-??-02 | CI APP_KEY 替换 | 1 | RELEASE-04 | T-04-02 | APP_KEY 行不含 base64:AAAA 硬编码 | unit | `composer test tests/Unit/Release/` | ❌ Wave 0 扩展 | ⬜ pending |
| 04-??-03 | 根 CI audit 补全 | 1 | RELEASE-03 | — | N/A | CI 自验证 | 推 PR 后 Actions summary 可见 | ✅ ci.yml 已有骨架 | ⬜ pending |
| 04-??-04 | release-package.sh | 2 | RELEASE-02 | T-04-03 | 参数强制校验 set -euo pipefail | manual (dry-run) | `bash scripts/release-package.sh --dry-run vX.Y.Z` | ❌ Wave 0 创建 | ⬜ pending |
| 04-??-05 | verify-package-install.sh | 2 | RELEASE-02 | — | N/A | manual | `bash scripts/verify-package-install.sh vX.Y.Z` | ❌ Wave 0 创建 | ⬜ pending |
| 04-??-06 | release-rollback.sh | 2 | RELEASE-02 | — | N/A | manual | `bash scripts/release-rollback.sh --dry-run vX.Y.Z` | ❌ Wave 0 创建 | ⬜ pending |
| 04-??-07 | RELEASE-06 acceptance | 3 | RELEASE-06 | — | N/A | manual-only | 人工执行 7 项 acceptance | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `.github/workflows/release.yml` — RELEASE-01 主要产物（新建）
- [ ] `scripts/release-package.sh` — RELEASE-02 脚本骨架（新建）
- [ ] `scripts/verify-package-install.sh` — RELEASE-02 脚本骨架（新建）
- [ ] `scripts/release-rollback.sh` — RELEASE-02 脚本骨架（新建）
- [ ] `tests/Unit/Release/DemoAppPackageBridgeTest.php` — 扩展 RELEASE-04 文件内容断言（可扩展现有测试）

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| push v0.5.0 tag 后包仓库出现同名 tag + GitHub Release 创建 | RELEASE-01 | CI 触发，需真实 push tag | `gh api repos/john-captain/filament-admin/git/refs/tags/vX.Y.Z` |
| release-package.sh 按 PRD 07 §2.1-2.7 全流程 | RELEASE-02 | Gitee 推送需本地 SSH，不在 CI | `bash scripts/release-package.sh --dry-run vX.Y.Z` |
| RELEASE-06 acceptance（7 项全通过）| RELEASE-06 | 需要干净 Laravel 13 环境 | 按 wiki/installation.md + REQUIREMENTS.md 说明人工执行 |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
