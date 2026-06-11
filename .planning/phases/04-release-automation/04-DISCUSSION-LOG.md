# Phase 4: 发布自动化 - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-11
**Phase:** 04-发布自动化
**Areas discussed:** release.yml subtree split 方式、Gitee 推送认证方案、APP_KEY 替换策略、Codecov 账号与配置

---

## release.yml subtree split 方式

| 选项 | 描述 | 选中 |
|------|------|------|
| 方案 A：原生 git subtree split | 直接自动化 PRD 07 手动步骤，无需三方 action，需 fetch-depth: 0 | ✓ |
| 方案 B：symplify/monorepo-split-github-action | 封装好、配置简洁，但引入三方 action 依赖 | |

**User's choice:** 方案 A（原生 git subtree split）

| 选项 | 描述 | 选中 |
|------|------|------|
| 方案 C：force push 到 main | 开源包界标准做法，dev-main 有效 | ✓ |
| 方案 B：推到专用 split 分支 | 不强制重写 main，但 dev-main 失效 | |

**User's choice:** 方案 C（force push 到 main）

| 选项 | 描述 | 选中 |
|------|------|------|
| awk 提取 + --notes-file | 从 CHANGELOG 提取对应版本节点，无额外依赖 | ✓ |
| GitHub 自动生成 release notes | 零配置，但内容是 PR 列表而非人工写的 CHANGELOG | |

**User's choice:** awk 提取 + --notes-file

| 选项 | 描述 | 选中 |
|------|------|------|
| PAT（PACKAGE_GITHUB_TOKEN） | Fine-grained PAT，配置最简单 | ✓ |
| SSH Deploy Key | 最安全，但配置步骤多 | |

**User's choice:** PAT（PACKAGE_GITHUB_TOKEN）

---

## Gitee 推送认证方案

| 选项 | 描述 | 选中 |
|------|------|------|
| Gitee PAT（PACKAGE_GITEE_TOKEN） | HTTPS 认证，与 GitHub PAT 对称 | |
| SSH Deploy Key 注入 | 不与 Gitee 账号绑定，但配置步骤多 | |
| release.yml 只推 GitHub，Gitee 手动同步 | CI 零配置，Gitee 放入 release 脚本 | ✓ |

**User's choice:** 尽量简单少配置 → release.yml 只推 GitHub，Gitee 推送放入 `release-package.sh` 脚本，AGENTS.md 中写明这一点。

**Notes:** 用户明确要求在 AGENTS.md 中写明 Gitee 同步是人工步骤。

---

## APP_KEY 替换策略

| 选项 | 描述 | 选中 |
|------|------|------|
| 方案 A：删 env APP_KEY，保留 key:generate | 零附加配置，CI 动态生成合法 key | |
| 方案 B：secrets.CI_APP_KEY | 配置一个 secret，key 固定 | ✓ |

**User's choice:** 方案 B（secrets.CI_APP_KEY）

| 选项 | 描述 | 选中 |
|------|------|------|
| 不改包 CI | 包测试不依赖 APP_KEY，无需添加 | ✓ |
| 包 CI 也配同一个 secret | 对称处理 | |

**User's choice:** 不改包 CI，当前测试不依赖 APP_KEY

---

## Codecov 账号与配置

| 选项 | 描述 | 选中 |
|------|------|------|
| 已有账号 / 去注册 | 配置 ci.yml + pcov + 徽章 | |
| 跳过 Codecov，暂不上报 | RELEASE-05 延迟 | ✓ |

**User's choice:** 完全跳过，README Codecov 徽章标 TODO

---

## Claude's Discretion

- `release-rollback.sh` 的具体 flag 和提示文案
- `verify-package-install.sh` 的隔离目录策略
- release.yml 中 Packagist 验证步骤的 retry/sleep 策略（交 planner 研究）
- `scripts/` 脚本头部标准（`set -euo pipefail`）

## Deferred Ideas

- **RELEASE-05 Codecov**：用户决定本期跳过，注册账号后单独接入
- **根 CI PHP 8.4 matrix 扩展**：不在本期范围
- **Gitee Pipelines 自动化**：PROJECT.md 提到但本期聚焦 GitHub Actions
