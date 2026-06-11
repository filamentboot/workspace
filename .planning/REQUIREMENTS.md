# Requirements: FilamentAdmin v0.5

**Defined:** 2026-06-09
**Milestone:** v0.5 — 主包"全部完成"形态（让 `composer require laravelstack/filament-admin` 真正开箱可用、可扩展、可稳定发版）
**Core Value:** 别人执行 `composer require laravelstack/filament-admin` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态符合 Laravel 开源市场规范

**REQ-ID 类别:**

- `COMPLY-XX` — 包发布合规（Composer/PSR/Filament Plugin 规范性）
- `DOC-XX` — 文档与品宣
- `FEAT-XX` — 包功能补强（按 GAP-ANALYSIS 决策点选入）
- `RELEASE-XX` — 发布自动化
- `DEMO-XX` — 演示站（v0.5.1，不阻塞 v0.5 主线）

**项目结构模式:** Horizontal Layers — v0.5 不是新功能开发型 milestone，按工具链/质量/文档分 phase 而非按用户故事切分。

---

## v1 Requirements (v0.5 范围)

共 25 项。每项映射到一个 phase。

### 包发布合规（Phase 1）

> 当前主包"已发包但未对外可用"，本组阻塞 v0.5 发布。

- [x] **COMPLY-01**: 主包 `FilamentAdminServiceProvider` 提供 5 个 `publishes()` 出口（config / migrations / views / lang / stubs），用户执行 `php artisan vendor:publish --tag=filament-admin-{config,migrations,views,lang,stubs}` 必须能落地相应资源到用户项目
- [x] **COMPLY-02**: `PublishCommand`（`php artisan filament-admin:publish`）真实实现 stub 复制能力，支持 `--model=X`、`--resource=Y`、`--all` 参数，每条命令完成后写出"已生成 N 个文件到 path/to/file.php"清单
- [x] **COMPLY-03**: `packages/filament-admin/composer.json` 补齐字段：`extra.branch-alias.dev-main=0.5.x-dev`、`require-dev` 加 `larastan/larastan` + `laravel/pint`、`scripts` 段（test / test-coverage / phpstan / pint / pint:test）、`config.allow-plugins.pestphp/pest-plugin=true`、`config.sort-packages=true`、`suggest` 段（ext-redis / laravel/pulse 等）、`support.docs` 链 wiki、`authors[0].email` 和 `role`、`keywords` 扩展（filament-plugin / permission / audit-log 等）
- [x] **COMPLY-04**: `packages/filament-admin/` 新增 `phpstan.neon` 和 `pint.json`（从根目录复制并调整 paths 为 `src/`），与根目录配置同等严格度（phpstan level 6+）
- [x] **COMPLY-05**: `packages/filament-admin/.github/workflows/ci.yml` 升级到与根 CI 同等门槛：跑 phpstan / pint --test / composer audit / PHP matrix（8.3 + 8.4）；phpstan / pint:test 失败即 CI fail（PHP 8.4 允许 continue-on-error，per D-17）；composer audit 失败为 warning 不阻塞 CI（per D-18，与 RELEASE-03 一致）
- [x] **COMPLY-06**: 删除根目录孤儿 `/src/`（与包内 `/packages/filament-admin/src/` 已分叉 5 文件且不被根 autoload），并补 `.gitignore` 或在 CONTRIBUTING 标注"只在 `packages/filament-admin/src/` 工作"
- [x] **COMPLY-07**: 根目录补 `LICENSE` 文件（复制 `packages/filament-admin/LICENSE` 同 MIT 文本），让 GitHub 仓库主页 "License" 标签显示 MIT 而不是 "No license"
- [x] **COMPLY-08**: git tag 规范化 — 历史中文后缀 tag（v0.2.0-权限体系 / v0.5.0-API规范 等）保留为历史但不再用；未来发版严格 `vX.Y.Z`；在 `CONTRIBUTING.md` 新增 SemVer 规范小节
- [x] **COMPLY-09**: 验证 `security@xitongapp.com`、`conduct@xitongapp.com` 邮箱接收实测可用；如不可用则在 SECURITY.md / CODE_OF_CONDUCT.md 替换为可达邮箱

### 文档与品宣（Phase 2）

> 装下来能跑、跑得通文档、装完之后愿意 Star。

- [x] **DOC-01**: `packages/filament-admin/README.md` 完整重写（当前 16 行占位符 → ~120 行）：第一屏 Hero（项目定位 + 1 张登录页/首页截图 GIF）、5 个 Badges（Packagist version / downloads / PHP / License / Tests CI）、5 行 Quick Start（含 vendor:publish + migrate + seed + AdminPanelProvider 引导 + 默认账号）、实现能力清单（抄全量功能清单一期已完成项）、与 FastAdmin/kaido-kit 差异化定位段
- [x] **DOC-02**: `.env.example` 默认值修正 — `APP_LOCALE=zh_CN`（当前 en）、`REDIS_PASSWORD=` 留空（当前占位符 `your-redis-password`）、`DB_PORT=3380` 后面加注释 `# Docker 默认 3380，标准 MySQL 改 3306`、`REDIS_DB=15` 加注释、文件末尾追加 FilamentAdmin 包级配置占位（`SUPER_ADMIN_ROLE=super_admin` / `LOG_RETENTION_DAYS=90`）
- [x] **DOC-03**: `wiki/installation.md` 完整化 — 顶部 Prerequisites 表（PHP 8.3+ / MySQL 8.0 / Redis 7.x / Node 20+ / Composer 2.x + 必需扩展 pdo_mysql/mbstring/bcmath/gd/redis/fileinfo/openssl）、中部 Quick Start 5 行可复制代码块、"默认账号"独立小节（`admin@example.com / password` + 首次改密提示）、完整 `AdminPanelProvider` 示例代码（用户 `composer require` 后必读）
- [x] **DOC-04**: `CHANGELOG.md`（根 + `packages/filament-admin/`）重写为 Keep-a-Changelog 1.1.0 格式 — 标准分组 Added / Changed / Deprecated / Removed / Fixed / Security；包含 `[Unreleased]` 段；历史 v0.4.0 / v0.4.1 内容补齐
- [x] **DOC-05**: 新增 `UPGRADING.md`（根目录） — v0.4 → v0.5 升级路径，列 breaking changes（vendor:publish 新增 5 tag 用户需重新 publish）、新增 PublishCommand 用法、配置文件变化、composer 约束建议
- [x] **DOC-06**: 根 `README.md` 改写 — 加 1 张登录页截图 + 1 张后台首页截图、Star CTA 一句、替换"插件市场能力不属于当前主包发布对象"等内部话术为对外友好的"未来路线图"段
- [x] **DOC-07**: `docs/` 与 `wiki/` 残留旧坐标 `filament-admin/filament-admin` 批量替换为 `laravelstack/filament-admin`（涉及 `docs/prd/01-项目规范与目录结构.md`、`docs/prd/全量功能清单与实现状态.md`、wiki 多处）
- [x] **DOC-08**: `CONTRIBUTING.md` 加端口说明 — 明确"本地 3380（Docker 端口映射）/ CI 3306（GitHub Actions services 默认 MySQL 端口）"差异，避免新人按文档配本地后困惑 CI 配置不同

### 包功能补强（Phase 3）

> kaido-kit 已有、低成本高曝光 + PRD 已规划但未做。

- [x] **FEAT-01**: User Impersonation 集成 — 引入 Spatie laravel-impersonate 或 stechstudio/filament-impersonate；超管在 AdminUserResource 列表页有"模拟登录"按钮，点击后切换会话身份，顶栏显示"正在模拟 username（结束模拟）"，结束后回到超管会话；操作写入 activity log
- [x] **FEAT-02**: Scramble API 自动文档 — 接入 dedoc/scramble；零配置 + 已有 Sanctum API 路由（admin/api/v1）自动生成 OpenAPI 3.0 文档；访问 `/docs/api` 可看；`composer.json` 加依赖 + `scribe.php` 或 `scramble.php` 配置
- [x] **FEAT-03**: CRUD 生成器 — `php artisan make:filament-admin-model X` / `make:filament-admin-resource X` / `make:filament-admin-migration X` / `make:filament-admin-test X` 四个 artisan 命令；从 `packages/filament-admin/stubs/{Model,Resource,Migration,FeatureTest}.stub` 渲染（含命名空间替换、字段占位）；输出 4 个文件到用户项目对应位置；每个命令有 PHPUnit 覆盖

### 发布自动化（Phase 4）

> 让下次发版从"9 条手工命令"变成"打 tag 就完事"。

- [ ] **RELEASE-01**: `.github/workflows/release.yml` — 触发器 `push tag v*`；步骤：checkout(fetch-depth: 0) → 跑 `git subtree split --prefix=packages/filament-admin` → 推到 package GitHub + Gitee 仓库（用 deploy key secrets）→ 在 split commit 上打 tag → `gh release create` 用 CHANGELOG 提取本版本说明 → 在临时容器跑 `composer require laravelstack/filament-admin:vX.Y.Z` 验证
- [ ] **RELEASE-02**: `scripts/release-package.sh` + `verify-package-install.sh` + `release-rollback.sh` 三件套 — 接受 `vX.Y.Z` 参数；按 PRD 07-发布链路完善 2.1-2.7 顺序执行；失败 `set -e`；rollback 脚本能删本地 + GitHub + Gitee 上的 tag、强制重置 split 分支
- [ ] **RELEASE-03**: 根 CI `.github/workflows/ci.yml` 在 `composer test` 之后增加 `composer audit --abandoned=report --no-interaction` 步骤；失败为 warning 而非 fail（避免上游 CVE 阻塞主线但保持可见）
- [ ] **RELEASE-04**: 根 CI 与包 CI 的固定 `APP_KEY: base64:AAAA...` 占位符改用 `${{ secrets.CI_APP_KEY }}` 或 `php artisan key:generate --show` 动态生成；README/CONTRIBUTING 注明"CI APP_KEY 仅用于测试，禁止用于真实环境"
- [ ] **RELEASE-05**: Codecov 覆盖率上报 — CI 增加 `coverage: pcov`、改 `vendor/bin/pest --coverage --coverage-clover=coverage.xml`、上传 `codecov/codecov-action@v4`；README 加 Codecov 徽章；为 PRD 自定的 "测试覆盖率 > 80%" 提供测量手段
- [ ] **RELEASE-06**: v0.5 出版前手动接收测试（干净环境）

  **核心约束**：**不动用户工作区任何目录**，测试在 `/tmp/v0.5-acceptance` 隔离环境进行。**不通过不能打 v0.5.0 正式 tag**。

  **执行步骤**：

  1. `composer create-project laravel/laravel /tmp/v0.5-acceptance && cd /tmp/v0.5-acceptance`
  2. `composer require laravelstack/filament-admin:^0.5`（或 dev-main / v0.5.0-rc.1）
  3. 严格按 `wiki/installation.md` 一步步走 — 不偷懒、不跳步、不凭记忆，**完全模拟"新用户第一次接触"路径**
  4. 在执行过程中，wiki 每一行不清楚的指令、每一个不合理的默认值、每一处实际行为与文档不符的地方，**全部写到 acceptance log**

  **7 项 acceptance 验证（全部通过才算 RELEASE-06 完成）**：

  1. `php artisan vendor:publish --tag=filament-admin-config / migrations / views / lang / stubs` 5 个 tag 各自能落地对应资源到用户项目（验 COMPLY-01）
  2. `php artisan migrate` 跑通；`php artisan db:seed --class="FilamentAdmin\\Database\\Seeders\\SuperAdminSeeder"` 创建出默认管理员
  3. `admin@example.com / password` 能登录后台首页（路径 `/admin`，需要按 wiki 引导先配 AdminPanelProvider）
  4. 在 AdminUserResource 列表页点"模拟登录"按钮，能切换身份且顶栏显示"结束模拟"链接（验 FEAT-01）
  5. 访问 `/docs/api` 返回 Scramble 生成的 OpenAPI 3.0 文档界面（验 FEAT-02）
  6. `php artisan make:filament-admin-resource Product` 在用户项目正确位置生成 Resource 文件（验 FEAT-03）
  7. `php artisan filament-admin:publish --model=Product --all` 输出 Model + Resource + Migration + Test 四件套到用户项目对应目录（验 COMPLY-02）

  **产出**：`/tmp/v0.5-acceptance-log.md` — 全过程记录 + 每个步骤的卡点。

  **触发条件**：Phase 4 RELEASE-01~05 全部完成 + `release.yml` 已 dry-run 通过 + scripts 三件套手测通过后执行。若有 blocker，回相应 phase 修复后**重新整个 acceptance 流程**（不只是单点验证），因为修一个 bug 可能引入另一个。

  **工时**：1-2h（不含卡点修复）

  **将来扩展**：v1.5 开发官网时，建议新建一个独立的 brownfield 环境（如 `johncaptain/filament-admin-website` 独立仓库）作为二次 acceptance 环境，覆盖"已有 Laravel 项目升级到 v0.x 主包"的真实路径。本轮 v0.5 暂不要求。

---

## v0.5.1 Requirements (不阻塞 v0.5 主线，但属于本 milestone)

### 演示站（Phase 5 = v0.5.1）

- [x] **DEMO-01**: demo.xitongapp.com 部署当前代码到 118.25.27.49 服务器；Gitee CI tag 触发 → SSH 拉新代码 → `composer install --no-dev` → `npm ci && npm run build` → `php artisan migrate --force` → reload PHP-FPM
- [x] **DEMO-02**: `php artisan demo:reset` 命令 — 清空业务数据表（保留 admin_users 仅 demo 账号 + 角色权限 + 菜单）+ 重新跑 demo seeder；cron `0 4 * * *`（每天凌晨 4 点）调用
- [x] **DEMO-03**: 演示账号 (`demo@example.com / demo123`) + 高危操作屏蔽中间件 — 拦截 `Admin::create/destroy`、`Role::create/destroy`、`Permission::create`、`Department::destroy`、`Setting::update` 等敏感操作，返回友好提示"演示环境屏蔽此操作"
- [x] **DEMO-04**: `README.md`、`packages/filament-admin/README.md`、`wiki/index.md` 加 demo 链接 + 默认演示账号说明（顶部显眼位置）

---

## v2 Requirements (推到 v0.6+ milestone)

### v0.6 系统配置 / 媒体库 / 导出 / API 规范（26 项 0 完成，独立 milestone）

详见 `docs/prd/全量功能清单与实现状态.md`：

- **六期 系统配置**（7 项） — Spatie Settings + 强类型配置类 + 配置页 + 配置缓存
- **七期 媒体库**（7 项） — Spatie MediaLibrary 集成 + 上传 + 处理 + 云存储扩展点
- **八期 基础导出**（5 项） — Excel/CSV 导出引擎 + 队列支持 + 模板配置
- **九期 API 基础规范**（7 项） — 统一响应格式 + 错误码 + 鉴权中间件 + 路由分组 + 文档（在 Scramble 基础上完善）

### v1.0.0 插件市场（待 v0.5 完成后独立 milestone）

详见 `docs/prd/06-插件市场.md`：依赖冲突检测、卸载清理、动态 SP 注册、远程市场完整链路、版本兼容矩阵。

### v1.0+ 推迟的功能补强

- **AUTH-V2-01**: Social Login (Google / GitHub OAuth via Socialite) — DC1 推迟
- **DEV-V2-01**: Docker / Laravel Sail 完整支持 — DC4 推迟
- **TEST-V2-01**: 主包 tests/ Feature 测试全量迁移（含 PanelProvider testbench 重构） — D2 推迟
- **I18N-V2-01**: 完整 i18n（所有硬编码中文 `__()` 包装 + 语言切换 UI） — D3 推迟
- **PLUGIN-V2-01**: Plugin 类暴露 `->permissions()` / `->resources()` 用户钩子 — v1.0
- **DEV-V2-02**: wiki 重组 + 完整 "Add Product Resource" tutorial — W2 推迟

### v1.5 官网

详见 PROJECT.md "Context" 章："插件市场 + 官网"合并为单个闭源仓库；本轮完全不动。

---

## Out of Scope (永不做或战略路线不同)

| 项目 | 不做的原因 |
|---|---|
| metadata-driven runtime engine（lyre/filament-admin 路线） | 不是同一战略；我们走"开箱即用" path，不走"运行时自动生成 Resource" path |
| 多包生态拆分（admin / permission / user-core 多个独立包，red-jasmine 路线） | 单人维护，2-3 包（核心 + 插件市场）足够；多包等于多份 CI / docs / release |
| Resend 邮件集成 | Laravel 默认 mail driver + SMTP 已够用，不增加 Resend 依赖 |
| `.github/FUNDING.yml` / GitHub Sponsors | 单人项目暂不商业化 |
| Horizon / Pulse / Sentry 监控埋点 | 包不是 SaaS，监控由集成方负责 |
| 付费插件 / 商业化付费功能 | 远期，v2.0+ 再考虑 |
| 异步安装队列 | 第一版同步安装，简单可靠（PRD 00 自定） |
| 全局回收站 UI | v0.1 已有软删除能力，统一 UI 推迟 |

---

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
| RELEASE-01 | Phase 4 | Pending |
| RELEASE-02 | Phase 4 | Pending |
| RELEASE-03 | Phase 4 | Pending |
| RELEASE-04 | Phase 4 | Pending |
| RELEASE-05 | Phase 4 | Pending |
| RELEASE-06 | Phase 4 | Pending |
| DEMO-01 | Phase 5 (v0.5.1) | Complete |
| DEMO-02 | Phase 5 (v0.5.1) | Complete |
| DEMO-03 | Phase 5 (v0.5.1) | Complete |
| DEMO-04 | Phase 5 (v0.5.1) | Complete |

**Coverage:**

- v1 requirements (v0.5): **26 total**（COMPLY 9 + DOC 8 + FEAT 3 + RELEASE 6）
- v0.5.1 requirements (DEMO): **4 total**
- Mapped to phases: **30 / 30** ✓
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
