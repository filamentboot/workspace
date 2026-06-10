# Roadmap: FilamentAdmin v0.5

**Milestone:** v0.5 — 让主包"全部完成"形态
**Core Value:** 别人执行 `composer require laravelstack/filament-admin` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态符合 Laravel 开源市场规范
**Created:** 2026-06-09
**Granularity:** Fine
**Parallelization:** 串行（单人维护）

---

## Phases

- [x] **Phase 1: 包发布合规** — 修复 ServiceProvider `publishes()` 缺失 + PublishCommand 真实实现 + Composer 规范字段 + CI 门槛，让主包真正符合 Laravel 开源包标准
- [ ] **Phase 2: 文档与品宣** — README 重写 / wiki 完整化 / CHANGELOG 规范 / UPGRADING，让别人装下来看得懂、用得了、愿意 Star
- [ ] **Phase 3: 包功能补强** — Impersonation + Scramble API 文档 + CRUD 生成器，补齐 kaido-kit 已有的核心差异化功能
- [ ] **Phase 4: 发布自动化** — release.yml + 发版脚本三件套 + Codecov，让下次发版从 9 条手工命令变为打 tag 就完事
- [ ] **Phase 5: 演示站 (v0.5.1)** — demo.xitongapp.com 部署 + 数据重置 cron + 高危操作屏蔽，不阻塞 v0.5 主线

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
- [ ] 02-03-PLAN.md — 根仓库文档（DOC-03 wiki/installation.md 完整化 / DOC-06 根 README 改写含登录页截图 TODO 占位 / DOC-04 根 CHANGELOG / DOC-05 根 UPGRADING / DOC-08 CONTRIBUTING CI 端口）

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

**Plans**: TBD

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

**Plans**: TBD

---

### Phase 5: 演示站 (v0.5.1)

**Goal**: demo.xitongapp.com 自动部署当前代码，每日凌晨重置数据，高危操作被屏蔽，README 加 demo 链接，让评估者无需本地安装即可体验后台全貌
**Depends on**: Phase 4（不阻塞 v0.5 发版，可与 Phase 4 并行或 v0.5 发版后单独推进）
**Requirements**: DEMO-01, DEMO-02, DEMO-03, DEMO-04
**Work estimate**: 约 4-6h（部署 CI 1.5h + demo:reset 命令 1h + 高危屏蔽中间件 1.5h + README demo 链接 0.5h）

**Success Criteria**（以下全部为 TRUE 才算 Phase 5 完成）:

1. 访问 https://demo.xitongapp.com 能看到后台登录页，使用 `demo@example.com / demo123` 登录成功进入后台首页（服务器 118.25.27.49 已部署最新代码）
2. 在演示账号下尝试删除管理员或角色时，系统返回友好提示"演示环境屏蔽此操作"而不是实际执行删除；受屏蔽操作范围覆盖 DEMO-03 中列出的全部敏感操作
3. 每天凌晨 4 点 cron 执行 `php artisan demo:reset` 后，后台数据恢复到初始演示状态（仅保留 demo 账号 + 角色权限 + 菜单，业务数据清空重置）
4. `README.md`、`packages/filament-admin/README.md`、`wiki/index.md` 顶部显眼位置均包含 demo 链接和 `demo@example.com / demo123` 演示账号说明

---

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. 包发布合规 | 8/8 | Complete    | 2026-06-10 |
| 2. 文档与品宣 | 1/3 | Executing   | - |
| 3. 包功能补强 | 0/? | Not started | - |
| 4. 发布自动化 | 0/? | Not started | - |
| 5. 演示站 (v0.5.1) | 0/? | Not started | - |

---

## Coverage

**v1 Requirements (v0.5 主线):** 26 / 26 mapped ✓
**v0.5.1 Requirements (DEMO):** 4 / 4 mapped ✓
**Total:** 30 / 30 ✓
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
| DEMO-01 | Phase 5 (v0.5.1) | 演示站 |
| DEMO-02 | Phase 5 (v0.5.1) | 演示站 |
| DEMO-03 | Phase 5 (v0.5.1) | 演示站 |
| DEMO-04 | Phase 5 (v0.5.1) | 演示站 |

---

*Created: 2026-06-09 by gsd-roadmapper*
*Last updated: 2026-06-10*
