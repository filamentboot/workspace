# Phase 11: 官网插件实战 + 晴空上线 - Context

**Gathered:** 2026-06-15
**Status:** Ready for planning

<domain>
## Phase Boundary

以"晴空智能家"（湖北晴空妙享科技有限公司，qkznj.com）真实项目为蓝本，完成以下四件事：

1. **filament-admin:install 安装命令** — 新增到主包，全自动完成新用户接入（Provider + publish + migrate + seed SuperAdmin + 插件市场提示）
2. **filament-admin-site 发布到 Packagist** — subtree split site 包 → GitHub 独立仓库 → Packagist 收录，完成插件分发链路第一次完整验证
3. **晴空独立项目** — 全新 Laravel 项目（独立目录 + 独立 git 仓库），走完整 `composer require → plugin:scan → 后台管理 → 前台展示` 安装流程；SiteDemoSeeder 更新为晴空真实内容（文案真实 + 图片从 Next.js 工程导出上传）
4. **SSH 部署上线** — qkznj.com 部署到同台服务器（118.25.27.49），新建独立目录，nginx server block + acme.sh SSL，替换现有 Next.js 站

**不在本 Phase 范围：**
- decoration 主题大幅视觉重设计（Phase 10 骨架已足够展示级，Phase 11 仅做必要品牌色调适配）
- CI/CD 自动部署（纯手动 SSH 一次性部署即可）
- 插件市场 `plugin:install` 命令（v1.0.0 范围）
- 其他包（OSS/COS/编辑器）发布到 Packagist（Phase 22 统一整理）

</domain>

<decisions>
## Implementation Decisions

### filament-admin:install 命令

- **D-11-01:** 命令位置：`packages/filament-admin/src/Commands/InstallCommand.php`，注册为 `filament-admin:install`，成果回合进主包。
- **D-11-02:** 命令执行步骤（全自动，对标 laravel-admin `admin:install`）：
  1. 生成 `app/Providers/Filament/AdminPanelProvider.php`（stub，注册 FilamentAdminPlugin）
  2. 运行 `vendor:publish --provider=FilamentAdminServiceProvider --tag=filament-admin-config`
  3. 运行 `vendor:publish --provider=FilamentAdminServiceProvider --tag=filament-admin-migrations`
  4. 运行 `vendor:publish --provider=FilamentAdminServiceProvider --tag=filament-admin-lang`
  5. 运行 `migrate`
  6. 运行 `db:seed --class=FilamentAdmin\\Database\\Seeders\\SuperAdminSeeder`
  7. 输出安装报告 + 结尾提示："安装完成，访问 /admin 使用 admin@example.com/password 登录。如需安装插件，运行 `composer require laravelstack/filament-admin-xxx` 后执行 `php artisan plugin:scan`"
- **D-11-03:** 命令幂等性：检测 AdminPanelProvider 是否已存在，已存在时询问是否覆盖（不强制覆盖）。

### filament-admin-site 发布到 Packagist

- **D-11-04:** 发布路径：`packages/filament-admin-site/` → subtree split → `github.com/john-captain/filament-admin-site`（独立 GitHub 仓库）→ Packagist 添加仓库 `laravelstack/filament-admin-site`。
- **D-11-05:** 包版本：发布 `v0.10.0`（对应 Phase 10 实现）。
- **D-11-06:** 发布完成后验证：在 `/tmp/packagist-verify` 干净 Laravel 13 项目跑 `composer require laravelstack/filament-admin-site`，确认可装。

### 晴空独立项目安装流程

- **D-11-07:** 独立项目目录：服务器新建 `/var/www/qkznj/`（或类似独立目录），不在 monorepo 内，独立 git 仓库。
- **D-11-08:** 安装链路（验证端到端可复现）：
  ```
  laravel new qkznj
  composer require laravelstack/filament-admin laravelstack/filament-admin-site
  php artisan filament-admin:install
  php artisan plugin:scan
  # 后台启用 filament-admin-site 插件
  php artisan db:seed --class=SiteDemoSeeder
  ```
- **D-11-09:** filament-admin 版本：使用 Packagist 已有的最新稳定版。filament-admin-site 使用 D-11-05 发布的 v0.10.0。

### SiteDemoSeeder 真实内容

- **D-11-10:** 文案迁移：SiteDemoSeeder 更新为晴空真实数据——公司名/电话/地址/业务描述（智能家居系统设计安装）、真实案例名称和描述、真实智能方案和产品信息。数据由用户提供（从现有 Next.js 站提取）。
- **D-11-11:** 图片迁移：从现有 Next.js 工程 `public/` 目录导出图片，上传到服务器 `storage/app/public/site/`，Media Library 引用本地路径。Seeder 中使用 `addMediaFromDisk()` 或预先放置到 storage 后引用。
- **D-11-12:** Seeder 成果：更新后的 SiteDemoSeeder 合并回 `packages/filament-admin-site/database/seeders/SiteDemoSeeder.php`，其他用户拿到时换图换文即可直接投入使用。

### 晴空 qkznj.com 部署

- **D-11-13:** 服务器：同台（118.25.27.49），新建独立目录（`/var/www/qkznj/` 或 `/home/deploy/qkznj/`）。
- **D-11-14:** 域名：`https://www.qkznj.com/`（`www` 和 apex 均做 SSL）。
- **D-11-15:** 部署方式：纯手动 SSH——git clone 晴空仓库 → composer install --no-dev → .env 配置 → php artisan migrate --seed → nginx 新建 server block → acme.sh 申请 SSL。
- **D-11-16:** nginx 配置：新建 `/etc/nginx/sites-available/qkznj.conf`，与 demo.xitongapp.com 并存。PHP 版本：php8.4-fpm（与 demo 站一致）。
- **D-11-17:** 旧 Next.js 站处理：旧路由 301 重定向到新 Laravel 路由（nginx rewrite 规则），域名从 Next.js 指向新服务器后即切换。

### Claude's Discretion
- decoration 主题的具体配色微调（深色背景值、科技蓝/青渐变具体色值）
- Playwright 测试是在独立晴空项目内跑还是 monorepo 演示项目内跑（建议独立项目内，更贴近真实场景）
- filament-admin-site GitHub 仓库的具体名称和 org（建议 `john-captain/filament-admin-site`，与主包同 org）
- SiteDemoSeeder 中案例/方案/产品数量（建议 3-5 个 case、2-3 个 solution、3-5 个 product）

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 10 官网插件（直接前置）
- `.planning/phases/10-official-website-plugin/10-CONTEXT.md` — Phase 10 所有已锁定设计决策（包结构/内容类型/路由/主题/设置）
- `.planning/phases/10-official-website-plugin/10-VERIFICATION.md` — Phase 10 验证报告，13/13 通过，了解当前交付状态
- `packages/filament-admin-site/src/SiteServiceProvider.php` — 插件启停机制，installCommand 需了解注册流程
- `packages/filament-admin-site/database/seeders/SiteDemoSeeder.php` — 当前 Seeder 实现，Phase 11 更新此文件

### 主包现有命令（install 命令参考）
- `packages/filament-admin/src/Commands/PublishCommand.php` — 现有 publish 命令实现，install 命令参考结构
- `packages/filament-admin/src/FilamentAdminServiceProvider.php` — publishes() 出口定义，install 命令需调用对应 tag
- `packages/filament-admin/src/Database/Seeders/SuperAdminSeeder.php` — install 命令末尾要调用

### 插件市场契约（Phase 6）
- `.planning/phases/06-plugin-marketplace-launch/06-CONTEXT.md` — extra.filament-admin 契约字段，plugin:scan 发现机制
- `packages/filament-admin-site/composer.json` — site 包 extra.filament-admin 契约，发布前核验字段完整性

### 发布流程参考（Phase 4）
- `.planning/phases/04-release-automation/04-CONTEXT.md` — subtree split + GitHub + Packagist 发布流程，site 包发布沿用此模式
- `packages/filament-admin-oss/composer.json` — 已发布插件包的 composer.json 模板（subtree split 过的包格式）

### 部署参考（Phase 5）
- `.planning/phases/05-demo-site/05-CONTEXT.md` — demo.xitongapp.com 部署决策（同台服务器，acme.sh SSL，nginx 配置）
- `memory/demo-site-deployment.md` — 部署拓扑记录（nginx 配置位置、php-fpm 版本、acme.sh 路径）

### 需求权威来源
- `.planning/ROADMAP.md` §Phase 11 — SITE-DEBUG-01, SITE-THEME-01, SITE-DEPLOY-01 + Success Criteria
- `.planning/REQUIREMENTS.md` §Phase 11 — 需求正式定义

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `packages/filament-admin/src/Commands/PublishCommand.php`：命令骨架（HasArguments / HasOptions / handle()），InstallCommand 直接参考结构
- `packages/filament-admin-site/database/seeders/SiteDemoSeeder.php`：现有 Seeder，Phase 11 in-place 更新（替换 Unsplash 图片 + 虚构数据为真实晴空数据）
- `packages/filament-admin-oss/`：已完成 subtree split 的包，site 包发布走相同流程
- `resources/css/themes/decoration.css`：decoration 主题 CSS（`@custom-variant dark`），品牌色调微调改这里的 CSS 变量

### Established Patterns
- **Phase 4 subtree split 流程**：`git subtree split --prefix=packages/filament-admin-site -b release/site` → force push 到独立 GitHub 仓库 → Packagist webhook
- **Phase 5 nginx 部署模式**：acme.sh + php8.4-fpm，参考 memory/demo-site-deployment.md 的配置路径
- **plugin:scan 触发时机**：`composer require` 后手动 `php artisan plugin:scan`，install 命令结尾仅打印提示不自动 scan

### Integration Points
- `packages/filament-admin/src/FilamentAdminServiceProvider.php`：注册 InstallCommand → `$this->commands([InstallCommand::class])`
- `packages/filament-admin/stubs/AdminPanelProvider.stub`：install 命令生成 AdminPanelProvider 的模板，需新建此 stub
- `.github/workflows/` 或 `packages/filament-admin-site/.github/workflows/ci.yml`：site 包 GitHub 仓库的 CI 配置需复制/新建

</code_context>

<specifics>
## Specific Ideas

- **晴空品牌色**（Phase 10 CONTEXT 已定）：深色背景 `#0a0e1a`，主色科技蓝 `#00d4ff`，点缀青绿渐变。decoration.css 中更新对应 CSS 变量值。
- **install 命令成功输出格式**（参考 laravel-admin）：绿色 ✓ 标记每个步骤，最后输出访问 URL 和默认账号。
- **Next.js 图片导出**：从 Next.js 工程 `public/images/` 或 `public/assets/` 批量 `scp` 到服务器，按内容类型放入 `storage/app/public/site/cases/`、`site/solutions/` 等子目录。
- **qkznj.com 域名当前指向**：需确认域名 DNS 当前指向哪个 IP，切换时机由用户决定（新站本地验证通过后再改 DNS）。

</specifics>

<deferred>
## Deferred Ideas

- **CI/CD 自动部署晴空项目**（Phase 22 或后续）：Phase 11 纯手动 SSH，未来可参考 demo 站 Gitee Pipelines 配置自动化。
- **decoration 主题完整视觉重设计**（v1.x）：Phase 11 仅做品牌色调适配；全新视觉稿设计推迟。
- **其他插件包（OSS/COS/编辑器）发布 Packagist**（Phase 22）：Phase 11 只发 site 包，其余统一在 Phase 22 整理。
- **plugin:install 命令**（v1.0.0）：通过后台市场一键安装插件，当前版本不做。
- **晴空官网 CI/CD**（未来自行决定）：纯 SSH 部署满足当前需求。

</deferred>

---

*Phase: 11-official-website-debugging-launch*
*Context gathered: 2026-06-15*
