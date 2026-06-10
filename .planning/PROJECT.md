# FilamentAdmin

## What This Is

FilamentAdmin 是对标 FastAdmin / laravel-admin 的 Laravel 13 + Filament 5 后台基础平台，以 Composer 包 `laravelstack/filament-admin` 形态发布。独立开发者、外包公司和企业 IT 通过 `composer require` 即可拿到一套含认证、权限、菜单、操作日志、部门数据权限的后台底座，在上面直接构建业务模块而无需重建基础设施。

直接对标：**`siubie/kaido-kit`** (383★ Filament 3.x starter kit，国外同路线对手)、FastAdmin（ThinkPHP，国内同路线但技术栈老）。

## Core Value

**别人执行 `composer require laravelstack/filament-admin` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态完全符合 Laravel 开源市场规范。**

如果其他一切都失败，这一句必须为真。当前 v0.4.x 是"装得上但无法 publish 任何资源、PublishCommand 是空壳"——属于"已发包但未对外可用"，**v0.5 的核心使命就是修复这个**。

## Requirements

### Validated

<!-- 已交付能力。来源：docs/prd/全量功能清单与实现状态.md（79 项已完成 + 4 项已铺垫 / 177 项总） -->

**v0.1.0-alpha — 一期后台基础管理完整包（47/57 已完成）**

- ✓ 管理员登录（username / email）+ 登录限流 + 登录日志 — v0.1.0-alpha
- ✓ 双因素认证（TOTP + Recovery Code，基于 stephenjude/filament-two-factor-authentication）— v0.1.0-alpha
- ✓ 管理员 CRUD + 软删除 + 回收站 + 个人资料 — v0.1.0-alpha
- ✓ 角色权限（Spatie Permission + bezhansalleh/filament-shield 4.x）— v0.1.0-alpha
- ✓ 菜单管理（树形 + 拖拽排序 + 权限绑定 + 插件菜单接入）— v0.1.0-alpha
- ✓ 操作日志（spatie/laravel-activitylog）+ 自建登录日志双轨 — v0.1.0-alpha
- ✓ 部门管理（部门树）+ 数据权限（Resource scopeQuery 注入，超管/本部门/本人）— v0.1.0-alpha

**v0.2.0-beta / v0.3.0-beta — 二期+三期 插件市场基础与远程索引（25/40 已完成 + 3 铺垫）**

- ✓ 插件市场架构（独立 Composer 基础包定位，已剥离 PluginPlatform 出主包）— v0.2.0-beta
- ✓ 插件列表与状态、本地启停（基本能力）— v0.2.0-beta
- ✓ 远程索引读取雏形（2/5 已完成）— v0.3.0-beta
- ⚠ 二期未完成 10 项：依赖冲突检测、卸载清理、动态 SP 注册等高/极高复杂度模块 — **明确推到 v1.0.0 milestone**
- ⚠ 三期未完成 2 项：远程市场完整链路、版本兼容矩阵 — **明确推到 v1.0.0 milestone**

**v0.4.0 / v0.4.1 — 四期项目上线打通（4/11 已完成）**

- ✓ GitHub 包仓库已发布 `github.com/john-captain/filament-admin` — v0.4.0
- ✓ Gitee 包仓库已同步 `gitee.com/johncaptain/filament-admin` — v0.4.0
- ✓ Packagist 已收录 `laravelstack/filament-admin` — v0.4.0
- ✓ `composer require laravelstack/filament-admin` 已在干净 Laravel 13 项目验证通过 — v0.4.1
- ✓ 收口主包 Composer 坐标、剥离 PluginPlatform、拆出 preview 演示项目 — v0.4.1

### Active

<!-- v0.5 milestone 范围。这些是 hypothesis，shipped 后才进 Validated。 -->

**v0.5 milestone — 让主包"全部完成"**

按 phase 分组（具体 REQ-ID 映射在 REQUIREMENTS.md，phase 顺序在 ROADMAP.md）：

**Phase 1 — 包发布合规** ✓ 完成（2026-06-10，Validated in Phase 1）
- [x] 主包 ServiceProvider 提供 5 个 `publishes()` 出口（config / migrations / views / lang / stubs），让用户 `vendor:publish` 可用 — COMPLY-01
- [x] `PublishCommand` 真实实现 stub 复制能力（`--model=X --resource=Y --all`），兑现文档承诺（D1 选 A）— COMPLY-02
- [x] `packages/filament-admin/composer.json` 补字段：`branch-alias` / dev-tools (larastan + pint) / `scripts` / `config.allow-plugins` / `suggest` / `support.docs` / `authors.email` / 扩展 `keywords` — COMPLY-03
- [x] 包目录加 `phpstan.neon` + `pint.json`；包 CI 跑 phpstan / pint / `composer audit` + PHP matrix — COMPLY-04 / COMPLY-05
- [x] 删除根目录孤儿 `/src/`（与包内已分叉，5 文件不同）— COMPLY-06
- [x] 根目录补 `LICENSE` 文件（GitHub 主页才能显示 MIT）— COMPLY-07
- [x] git tag 规范化：未来发版严格 `vX.Y.Z` 不带中文后缀；CONTRIBUTING 加 SemVer 规范 — COMPLY-08

**Phase 2 — 文档与品宣**
- [ ] 包 README 重写：Hero + Badges + Quick Start 5 行 + 默认管理员账号 + 截图 + 实现能力清单
- [ ] `.env.example` 默认值修正（`APP_LOCALE=zh_CN` / `REDIS_PASSWORD` 留空 / `DB_PORT` 加注释 / 追加 `SUPER_ADMIN_ROLE` 等包级配置占位）
- [ ] `wiki/installation.md` 加 Prerequisites（PHP/MySQL/Redis/Node 版本 + 必需扩展）+ Quick Start + 默认账号 + 完整 `AdminPanelProvider` 示例
- [ ] `CHANGELOG.md` 重写 Keep-a-Changelog 1.1.0 格式（Added / Changed / Deprecated / Removed / Fixed / Security）
- [ ] 新增 `UPGRADING.md`（v0.4 → v0.5 升级路径）
- [ ] 根 `README.md` 加截图 + Star CTA + 替换内部话术

**Phase 3 — 包功能补强**
- [ ] User Impersonation（基于 Spatie laravel-impersonate，超管临时登录别人账号排错）— DC2
- [ ] Scramble API 自动文档（接入 `dedoc/scramble`，零配置生成 OpenAPI）— DC3
- [ ] CRUD 生成器完整版（`make:filament-admin-{model,resource,migration,test}` 四件套 + stub 渲染 + 测试）— DC5

**Phase 4 — 发布自动化**
- [ ] `.github/workflows/release.yml`（tag push 触发 → subtree split → 推 github + gitee 包仓库 → 创建 GitHub Release → 验证 Packagist 可装）
- [ ] `scripts/release-package.sh` + `verify-package-install.sh` + `release-rollback.sh`
- [ ] 根 CI 增加 `composer audit --abandoned=report`
- [ ] CI 用 secret 替代固定 `APP_KEY` 占位
- [ ] 启用 Codecov 覆盖率上报 + README 徽章
- [ ] **v0.5 出版前手动接收测试** — 在 `/tmp/v0.5-acceptance` 干净 Laravel 13 环境模拟新用户从 Packagist 拉项目，按 `wiki/installation.md` 走通安装/登录/publish 5 tag/Impersonation/Scramble/CRUD 生成器/`filament-admin:publish` 全流程，写 acceptance log；不通过不能打 v0.5.0 正式 tag。（将来 v1.5 开发官网时建议新建独立 brownfield 环境作二次 acceptance，本轮不要求）

**Phase 5 (v0.5.1) — 演示站（D4 选 B：不阻塞 v0.5 主线）**
- [ ] demo.xitongapp.com 部署当前代码（118.25.27.49 服务器，走 Gitee CI 自动部署）
- [ ] `demo:reset` 命令 + cron（每天凌晨重置数据）
- [ ] 演示账号 + 高危操作屏蔽中间件
- [ ] README 加 demo 链接

### Out of Scope

<!-- 明确不在 v0.5 范围。reason 必须有，防止反复争论。 -->

**推到下一个 milestone（v1.0.0 / v1.5）**

- 插件市场完整链路（依赖冲突检测、卸载清理、动态 SP 注册、远程市场完整流程、版本兼容矩阵）— v1.0.0 单独 milestone，用户明确"先不管"
- 官网建设（含插件市场前端）— v1.5 单独 milestone（闭源仓库另起）
- Plugin 类暴露用户钩子 `->permissions()` / `->resources()` — v1.0

**明确推到 v1.x**

- 完整 i18n（所有硬编码中文 `__()` 包装 + 语言切换）— D3 选 B，v0.5 连骨架都不做
- Social Login (Google / GitHub OAuth) — DC1 推迟，国内场景需求弱
- Docker / Laravel Sail 支持 — DC4 推迟，用户本地已有 docker 环境
- 主包 `tests/` 业务测试全量迁移 — D2 选 C，Feature 依赖 PanelProvider，重构成 testbench 成本太高

**永不做（不是同一战略路线）**

- metadata-driven runtime engine（lyre/filament-admin 路线，运行时自动生成 Resource）— 战略路线不同
- 多包生态拆分成 admin / permission / user-core 多包（red-jasmine 路线）— 单人维护负担，2-3 包足够
- Resend 邮件集成 — Laravel 默认 mail driver 已足够
- `.github/FUNDING.yml` / GitHub Sponsors — 单人项目暂不需要
- 异步安装队列 / Horizon / Pulse / 监控埋点 — 包不是 SaaS，监控由集成方负责
- 付费插件 / 商业化 — 远期，v2.0+
- 全局回收站 UI — v0.1 已有软删除能力，UI 推迟 v2.0+

**全量功能清单 v0.6 milestone 范围（已敲定推到 v0.6 独立 milestone）**

`docs/prd/全量功能清单与实现状态.md` 显示以下 4 个功能域当前 **0 项已完成 / 100% 待开发**，整体作为 **v0.6 milestone** 主轴在 v1.0.0 插件市场之前补齐：
- 六期 系统配置（0/7，工时 ≈ 12-16h）
- 七期 媒体库（0/7，工时 ≈ 14-20h）— PRD 00-总览第 5.2 表把它列为已 require 的依赖，但实际未集成
- 八期 基础导出（0/5，工时 ≈ 10-14h）
- 九期 API 基础规范（0/7，工时 ≈ 12-18h）

v0.6 milestone 总工时估算 ≈ 48-68h。

注：十期 CRUD 开发规范（0/6）中的 CRUD 生成器（DC5）已纳入 v0.5；十期剩余 5 项作为 v0.6 配套。

## Context

**物理形态：**
- 主包 `packages/filament-admin/` — Composer name `laravelstack/filament-admin`，type `library`，subtree split 推到 `github:john-captain/filament-admin` + `gitee:johncaptain/filament-admin` → Packagist
- 演示项目（当前工作树根目录）— Composer name `filament-admin/preview`，type `project`，推到 `gitee:johncaptain/filament-admin-preview`
- 演示站 demo.xitongapp.com 服务器 118.25.27.49（自动部署流程已配，但当前代码未部署）
- wiki/ 项目内文档（不发布独立站，跟代码一起 clone）

**战略对标：**
- `siubie/kaido-kit` (Filament 3.x starter kit, 383★) — 最直接对标，路线一致
- FastAdmin (ThinkPHP) — 国内同路线，技术栈对照
- laravel-admin — Filament 时代之前的 Laravel 后台
- 若依 (Java) — 重量级对照

**市场目标（来源 PRD 00-总览第 6 节）：**
- v1.0 发布后 90 天 GitHub Stars 500+
- v1.0 发布后 90 天 Packagist 下载 1000+
- 测试覆盖率 > 80%（核心功能域）
- 安装失败率 < 5%

**核心差异化：**
- 数据权限（部门级 + 本人 + 全部）+ Resource scopeQuery 注入（siubie/kaido-kit、lyre 都没有）
- 操作日志（ActivityLog Diff 审计）+ 登录日志双轨（kaido-kit 没有）
- 中文 PRD + 中文 wiki + Gitee 主战场（国内市场无直接替代）
- 计划中的插件市场（v1.0.0 milestone 主战场，对手都没做）
- 计划中的官网（v1.5）

**研究材料（写完后立即可用）：**
- `.planning/research/PROJECT-AUDIT.md` — 28 项发布合规缺口分析（864 行）
- `.planning/research/COMPETITORS.md` — 4 个原列竞品 + 2 个扩展候选对比
- `.planning/research/GAP-ANALYSIS.md` — v0.5 合成清单 + 9 个已拍板决策点

**代码地图（codebase brownfield 已映射）：**
- `.planning/codebase/` — 7 份（ARCHITECTURE / CONCERNS / CONVENTIONS / INTEGRATIONS / STACK / STRUCTURE / TESTING）
- `docs/prd/` — 8 份 PRD (00-07) + 全量功能清单 + 竞品分析草稿
- `wiki/` — 4 份（installation / index / guide×6 / reference×3）

## Constraints

- **Tech stack**: PHP 8.3+, Laravel 13.x (^13.8), Filament 5.x (^5.0) — 锁定主版本，patch/minor 自由升级；Filament 5 API 变更风险中等，缓解：避免实验性 API，参考官方示例
- **Team**: 单人（晚上 + 周末推进，约 4h/周）
- **Timeline**: 无硬期限，质量优先；v0.5 工时估算 ≈ 46h，按 4h/周 推 12-15 周
- **Compatibility**: 主包 `composer require` 必须在干净 Laravel 13 项目可装；包目录 subtree split 后独立仓库 CI 仍必须全绿
- **Market priority**: 国内主战场 Gitee（CI/CD 优先 Gitee Pipelines），海外副线 GitHub
- **Test strategy**: PHPUnit Feature 跑 CI；Playwright + 手测由用户本人不定时做，不在 CI 跑（确认）
- **License**: MIT（不变）
- **Security**: 不收集用户数据；安全报告通道 `security@xitongapp.com`（D-W4 待验证邮箱接收）
- **Default credentials**: SuperAdminSeeder 创建 `admin@example.com / password`，README + wiki 必须明示并提示首次登录改密
- **Test pyramid**: Unit 20% → Feature 50% → Integration 25% → E2E 5%（来源 PRD 00 第 5.1 表）

## Key Decisions

<!-- 决策按签字时间倒序，最新的在最上。新决策来时追加，不覆盖。 -->

| 决策 | 理由 | 状态 |
|---|---|---|
| **本轮 milestone = v0.5（主包"全部完成"形态）；插件市场 v1.0.0 / 官网 v1.5 各自独立 milestone，本轮不动** | 用户原话："插件市场先不管"+ "v0.5 我希望 filament-admin 全部完成" | ✓ Locked |
| **PRD 版本号语义沿用不变**（v0.5.0 = 插件市场，v1.0.0 = 正式发布）；用户口里的 v0.5/v1.0/v1.5 是"阶段里程碑"概念，不替换 PRD 版本号 | 避免 Packagist 历史 tag 与新语义冲突 | ✓ Locked |
| **D1 PublishCommand 真实实现**（6h，非"删命令改文档"的省力路径） | 用户偏好"承诺兑现"；文档已对外承诺该命令可用 | ✓ Locked |
| **D2 主包业务测试不迁移**（保持现状，CI 仍只跑 2 个元数据测试） | Feature 测试依赖 PanelProvider，重构成 testbench 用法成本高于 v0.5 收益 | ✓ Locked |
| **D3 i18n 推到 v1.0**（v0.5 连骨架都不做） | v0.5 全力做发布合规和文档；i18n 完整版工作量大，统一在 v1.x 做 | ✓ Locked |
| **D4 demo 站不阻塞 v0.5**（Phase 5 标 v0.5.1，主线推进不等 demo） | 部署涉及高危屏蔽中间件 + 数据重置 cron，工作量风险较大，主线不被拖累 | ✓ Locked |
| **DC1 Social Login 推迟到 v1.0+** | 国内场景需求弱（用户偏向账号密码 + 2FA） | ✓ Locked |
| **DC2 User Impersonation 进 v0.5** | 后台基础能力，超管排错刚需，2-3h 工作量低 | ✓ Locked |
| **DC3 Scramble API 文档进 v0.5** | 1-2h 接入 OpenAPI 自动文档，性价比极高；超 PRD 原 v2.0+ 计划提前做 | ✓ Locked |
| **DC4 Docker / Sail 推迟到 v1.0+** | 用户本地已有 Docker 环境，外部评估者优先级低于其他功能 | ✓ Locked |
| **DC5 CRUD 生成器完整版进 v0.5** | PRD 十期"CRUD 开发规范"已规划但 0 完成；kaido-kit 已有；属于"开发管理"核心 | ✓ Locked |
| **永不做：metadata-driven runtime engine** | 战略路线与 lyre/filament-admin 不同 | ✓ Locked |
| **永不做：多包生态拆分**（不学 red-jasmine 把 admin/permission/user-core 拆 N 包） | 单人维护负担；保持 2-3 包（核心 + 插件市场）足够 | ✓ Locked |
| **永不做：Resend 邮件集成** | Laravel 默认 mail driver 已足够 | ✓ Locked |
| **Fine 粒度，严格串行**（v0.5 内 phase 顺序固定） | 用户单人 + 不限时 + 质量优先 | ✓ Locked |
| **Gitee Pipelines 主线、GitHub Actions 副线** | 用户原话："国内 Gitee 网络更好就走 Gitee 了" | ✓ Locked |
| **六/七/八/九期（系统配置/媒体库/导出/API 规范，4 期 26 项 0 完成）不进 v0.5，整体推到 v0.6 独立 milestone** | v0.5 只做发布合规+品宣+功能补强+发布自动化（46h）；六-九期作为 v0.6 主轴，在 v1.0.0 插件市场之前补齐 | ✓ Locked |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-06-09 after `/gsd-new-project` initialization (v0.5 milestone scoped)*
