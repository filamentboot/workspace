# Phase 11: 官网插件实战 + 晴空上线 - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-15
**Phase:** 11-official-website-debugging-launch
**Areas discussed:** install 命令范围, 独立项目依赖来源, 真实内容迁移, 部署服务器与方式

---

## install 命令范围

| Option | Description | Selected |
|--------|-------------|----------|
| 最小化：AdminPanelProvider stub 生成 | 只生成 Provider，其他步骤手动 | |
| 中等：生成 Provider + 运行 vendor:publish | Provider + 发布 config/migrations/lang，不跑 migrate/seed | |
| 全自动：wizard 模式（交互式多步骤） | 交互式逐步确认，功能最完整但工时最多 | |
| **全包：Provider + publish + migrate + seed** | 对标 laravel-admin admin:install，全自动无交互 | ✓ |

**用户问题:** "按照 laravel-admin 这个开源项目的习惯，install 应该要做到哪一层？"
**最终决策:** 全自动（Provider + vendor:publish + migrate + seed SuperAdmin），完成后打印提示：安装插件用 `composer require` + `plugin:scan`，不自动跑 plugin:scan。

---

## 独立项目依赖来源

| Option | Description | Selected |
|--------|-------------|----------|
| Packagist 正式发布后安装 | Phase 11 内先发布 site 包，独立项目 composer require | ✓ |
| VCS repo（GitHub/Gitee 地址） | 不发 Packagist，用 vcs repository | |
| path repo（monorepo 本地路径） | 开发用，部署最不优雅 | |

**用户问题:** "按照我插件市场之前的设置和规划，我这个 filament-admin-site 应该放在哪里？怎么装？"
**讨论过程:** 用户确认当前插件市场链路（plugin:scan）只支持发现已安装包，不支持自动 `composer require`。安装链路仍需手动 `composer require`。
**最终决策:** Phase 11 内先发布 filament-admin-site 到 Packagist（subtree split → GitHub 独立仓库 → Packagist），晴空独立项目用标准 `composer require laravelstack/filament-admin-site` 安装，完整验证插件分发链路。

---

## 真实内容迁移

| Option | Description | Selected |
|--------|-------------|----------|
| 更新文案为晴空真实文案，图片保留 Unsplash 占位 | 最小迁移成本 | |
| **全部真实内容（从 Next.js 站导出内容写入 Seeder）** | 文案 + 图片全部真实，工时最多 | ✓ |
| 不运行 Seeder，全部内容由用户后台手动录入 | 最简单，但前台暂时为空 | |

**图片来源跟进:**
- 用户选择从 Next.js 工程目录导出图片并上传到服务器（而非引用 Next.js 站现有 URL 或用占位图）

**最终决策:** SiteDemoSeeder 更新为晴空真实数据（公司信息/案例/方案/产品真实文案），图片从 Next.js `public/` 导出，上传到服务器 storage，Media Library 引用本地。Seeder 成果合并回 filament-admin-site 包。

---

## 部署服务器与方式

| Option | Description | Selected |
|--------|-------------|----------|
| **同台服务器（118.25.27.49），新建目录** | demo 站同机，nginx 新建 server block | ✓ |
| 新服务器（晴空自己的服务器） | 独立服务器，需用户提供 IP + SSH 凭证 | |

**部署方式:**

| Option | Description | Selected |
|--------|-------------|----------|
| **纯手动 SSH（Phase 11 一次性部署）** | SSH 登录 → git clone → composer install → nginx 配置 | ✓ |
| Gitee CI/CD 自动部署 | 参考 demo 站 Pipelines 配置，持续部署 | |

**用户补充:** "域名用 https://www.qkznj.com/，你新建个目录放"。
**最终决策:** 同台 118.25.27.49，新建 `/var/www/qkznj/`（或类似），域名 `www.qkznj.com`，纯手动 SSH 部署，nginx + acme.sh SSL，与 demo 站并存。

---

## Claude's Discretion

- decoration 主题品牌色具体值（深色背景、科技蓝渐变）
- Playwright 测试在独立晴空项目内跑还是 monorepo（建议独立项目）
- filament-admin-site GitHub 仓库具体名称（建议 `john-captain/filament-admin-site`）
- SiteDemoSeeder 中案例/方案/产品数量（建议 3-5/2-3/3-5 个）

## Deferred Ideas

- CI/CD 自动部署晴空项目（Phase 22 或后续）
- 其他插件包（OSS/COS/编辑器）发布 Packagist（Phase 22）
- plugin:install 命令（v1.0.0）
- decoration 主题完整视觉重设计（v1.x）
