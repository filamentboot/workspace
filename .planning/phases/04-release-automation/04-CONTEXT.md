# Phase 4: 发布自动化 - Context

**Gathered:** 2026-06-11
**Status:** Ready for planning

<domain>
## Phase Boundary

将 PRD 07 手动发版流程（9 条命令）自动化为"push tag → 全流程自动完成"：

1. **`release.yml`（RELEASE-01）** — push `v*` tag 触发：`git subtree split` → 推 GitHub 包仓库 → 打包仓库 tag → 从 CHANGELOG 提取版本说明创建 GitHub Release。**Gitee 推送不在 CI 中，放入 release 脚本。**
2. **发版脚本三件套（RELEASE-02）** — `scripts/release-package.sh` + `verify-package-install.sh` + `release-rollback.sh`，按 PRD 07 §2.1-2.7 顺序实现，含 Gitee 推送步骤。
3. **根 CI 安全审计（RELEASE-03）** — 补 `composer audit --abandoned=report`（包 CI 已有）。
4. **根 CI APP_KEY 清理（RELEASE-04）** — 硬编码占位符改为 `${{ secrets.CI_APP_KEY }}`，包 CI 不变。
5. **Codecov（RELEASE-05）** — 本期**跳过**，README 徽章标 TODO。
6. **v0.5 出版前手动接收测试（RELEASE-06）** — Human task，RELEASE-01~02 完成 + dry-run 通过后执行。

**对应 REQ-ID：** RELEASE-01、RELEASE-02、RELEASE-03、RELEASE-04（RELEASE-05 跳过，RELEASE-06 人工）

</domain>

<decisions>
## Implementation Decisions

### release.yml 技术方案（RELEASE-01）

- **D-36：`git subtree split` 原生命令。** 直接自动化 PRD 07 §2.3 手动步骤，无需引入三方 action。CI 步骤：`actions/checkout@v4` 配 `fetch-depth: 0`（必须，否则 split 会遗漏早期提交） → `git subtree split --prefix=packages/filament-admin -b tmp/split-vX.Y.Z` → 推送到 `package-github`。

- **D-37：Force push 到包仓库 main 分支。** `git push package-github tmp/split-vX.Y.Z:main --force`。开源包界标准做法（Spatie / Laravel 过包同策略），包仓库 main 全由 CI 自动维护，`--force` 是计划内行为，`dev-main` 地址与 `branch-alias` 有效。

- **D-38：用 `awk` 提取 CHANGELOG 对应版本节点 + `--notes-file`。**
  ```bash
  awk '/^## \[/{if(found)exit; found=1; next} found{print}' \
    packages/filament-admin/CHANGELOG.md > /tmp/release-notes.md
  gh release create "$TAG" --repo john-captain/filament-admin \
    --title "$TAG" --notes-file /tmp/release-notes.md
  ```
  无额外依赖，CHANGELOG 是 Keep-a-Changelog 1.1.0 格式（Phase 2 已完成），结构稳定。

- **D-39：GitHub 包仓库认证用 Fine-grained PAT。** Secret 命名 `PACKAGE_GITHUB_TOKEN`，scope 限 `john-captain/filament-admin` 的 `contents: write`。release.yml 里用 HTTPS URL 注入认证：
  ```bash
  git remote set-url package-github \
    https://x-access-token:${{ secrets.PACKAGE_GITHUB_TOKEN }}@github.com/john-captain/filament-admin.git
  ```

- **D-40：release.yml 的 Packagist 验证步骤。** RELEASE-01 要求在临时容器跑 `composer require laravelstack/filament-admin:vX.Y.Z` 验证安装。该步骤可能需等 Packagist 同步（几分钟延迟），planner 需决定是否加 retry/sleep 或改为验证包仓库 tag 存在即可。**交 planner 研究**。

### Gitee 推送策略（RELEASE-02 + AGENTS.md）

- **D-41：release.yml 只推 GitHub 包仓库，Gitee 不在 CI 中自动推。** 理由：最简配置，避免 Gitee PAT 增加额外 secret。Gitee 同步放入 `release-package.sh` 脚本，人工触发。

- **D-42：`release-package.sh` 含 Gitee 推送步骤。** 脚本按 PRD 07 §2.4-2.5 顺序：push GitHub 包仓库 main → push Gitee 包仓库 main → 在两个远端打 tag。使用现有 `package-gitee` remote（SSH 已配置）。

- **D-43：AGENTS.md 中明确 Gitee 同步是人工步骤。** 写明"每次发版需手动执行 `scripts/release-package.sh vX.Y.Z`，其中包含 Gitee 推送。release.yml 不自动推 Gitee。"

### APP_KEY 替换（RELEASE-04）

- **D-44：根 CI 用 `${{ secrets.CI_APP_KEY }}` 替换 `APP_KEY: base64:AAAA...` 硬编码占位符。** 保留 `php artisan key:generate` 步骤不变（用于写入 `.env`，流程正确）。只替换 `env:` 块里的 `APP_KEY` 行。

- **D-45：包 CI 不修改。** 包测试（当前 2 个元数据测试）不依赖 APP_KEY，无需添加。RELEASE-04 要求仅适用于根 CI。

- **所需 Secret 汇总：** 需在主仓库 GitHub Settings → Secrets 中配置：
  - `PACKAGE_GITHUB_TOKEN` — Fine-grained PAT，scope `john-captain/filament-admin` `contents: write`
  - `CI_APP_KEY` — 合法的 base64 Laravel APP_KEY（`php artisan key:generate --show` 生成一个）

### Codecov（RELEASE-05 跳过）

- **D-46：RELEASE-05 本期完全跳过。** CI 不改覆盖率配置，README 中 Codecov 徽章位置添加 TODO 占位注释。将来注册 Codecov 账号后单独接入。

### Claude's Discretion

- **`release-rollback.sh` 实现细节：** 按 PRD 07 §4 描述，删除本地 + GitHub + Gitee 上的同名 tag，可选强制重置 split 分支。具体 flag 和提示文案由 planner/executor 决定。
- **`verify-package-install.sh` 实现：** 在隔离目录（`/tmp/verify-vX.Y.Z`）创建临时 Laravel 项目 + `composer require laravelstack/filament-admin:vX.Y.Z`，验证 `package:discover` 成功。
- **`release.yml` tag 推送到包仓库：** tag 打在 split commit 上（`git tag -a $TAG $SPLIT_SHA -m "$TAG 发布"`），再 `git push package-github $TAG`。
- **`scripts/` 目录 + `set -e`：** 所有脚本头部 `#!/usr/bin/env bash` + `set -euo pipefail`，失败立即退出（RELEASE-02 要求）。

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### 发版流程规范（直接实现依据）
- `docs/prd/07-发布链路完善.md` §2 — **手动发版 2.1-2.7 步骤，脚本三件套的直接实现依据**；§3.2 列出未完成自动化清单；§4 列出脚本建议
- `docs/prd/05-发布链路.md` — 发版链路基础规范（PRD 05）

### 需求与范围
- `.planning/REQUIREMENTS.md` §「发布自动化（Phase 4）」 — RELEASE-01~06 逐条详细描述，含 RELEASE-06 的 7 项 acceptance 验证清单
- `.planning/ROADMAP.md` §「Phase 4: 发布自动化」 — Goal / Success Criteria 6 条 / Work estimate（约 13-14h）
- `.planning/PROJECT.md` §Key Decisions — 市场优先级（国内主战场 Gitee / 海外副线 GitHub）

### 现有 CI 文件（修改对象）
- `.github/workflows/ci.yml` — 根 CI，需补 `composer audit` 步骤 + 替换 APP_KEY 硬编码
- `packages/filament-admin/.github/workflows/ci.yml` — 包 CI，本期不改，仅供参考（已有 `composer audit`）

### 代码风格与包结构
- `.planning/codebase/CONVENTIONS.md` — 编码约定
- `packages/filament-admin/composer.json` — 包元数据，scripts 段（`composer test` 命令），`branch-alias.dev-main`

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`git remote package-github` / `package-gitee`**：已在本地 `.git/config` 配置 SSH URL，release 脚本可直接 `git push package-github` / `git push package-gitee`，无需重新配置远端。
- **`packages/filament-admin/CHANGELOG.md`**：Keep-a-Changelog 1.1.0 格式（Phase 2 已完成），`## [vX.Y.Z] - date` 节点格式稳定，`awk` 提取可靠。
- **根 CI 现有 `php artisan key:generate` 步骤**：保留不变，仅删除冗余的 `APP_KEY` 硬编码行。

### Established Patterns
- **`set -euo pipefail`**：项目所有 shell 脚本应遵循此标准（与 RELEASE-02 "失败以非 0 退出码终止"一致）。
- **包 CI PHP matrix（8.3 / 8.4）**：包 CI 已有，根 CI 若后续扩展可参照同结构。
- **`composer audit --abandoned=report --no-interaction`**：包 CI 已有此步骤，根 CI 直接复制追加。

### Integration Points
- **subtree split 与包仓库 main 分支**：`git subtree split` 产出的分支历史与包仓库现有 main 不连续，必须 `--force` 推送（计划内行为）。
- **Packagist 自动更新**：Packagist 通过 GitHub webhook 监听 `john-captain/filament-admin` 的 tag push 事件，无需额外触发。延迟约 1-5 分钟。

</code_context>

<specifics>
## Specific Ideas

- **AGENTS.md 中明确 Gitee 同步为人工步骤：** 用户明确要求在 AGENTS.md 中写明每次发版需手动执行 `scripts/release-package.sh vX.Y.Z` 以完成 Gitee 同步，release.yml 不自动推 Gitee。
- **Gitee 推送用现有 SSH remote：** 本地已配 `package-gitee` SSH，脚本直接用，CI 里不需要额外的 Gitee 认证配置。

</specifics>

<deferred>
## Deferred Ideas

- **RELEASE-05 Codecov 覆盖率上报**：本期完全跳过（用户明确决策）。将来注册 Codecov 账号后单独接入，README 徽章占位 TODO。
- **根 CI PHP 8.4 matrix**：包 CI 已有 8.4 matrix，根 CI 本期不扩展（不在 RELEASE 需求范围内）。
- **Gitee CI（Gitee Pipelines）**：PROJECT.md 提到"国内主战场 Gitee，CI/CD 优先 Gitee Pipelines"，但本期仅聚焦 GitHub Actions release.yml，Gitee Pipelines 自动化推迟到后续 milestone。

</deferred>

---

*Phase: 4-发布自动化*
*Context gathered: 2026-06-11*
