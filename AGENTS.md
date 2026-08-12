# AGENTS.md — FilamentAdmin

> ⚠️ **本文件大部分内容已过期**（仍停留在 `app/` Skeleton、`FilamentAdmin\` 命名空间的早期状态）。`packages/` 曾冻结过一段时间，2026-08-12（八期批次 2）刚从 `filamentboot-web` 仓库（`~/src/personal/filamentboot-web`）整目录回流覆盖，当前代码的真实架构、命名空间、约束请以 `CLAUDE.md` 顶部的冻结/回流说明和 `packages/` 下的实际代码为准，不要依赖本文件下面的架构方向描述。

Laravel 13 + Filament 5 后台基础平台。主路线详见 `docs/dev/项目开发规划.md`，历史详细计划见 `docs/superpowers/specs/2026-05-28-filament-admin-v1-development-plan.md`。

## 架构方向（重要，优先阅读）

**目标架构：Library 包（`composer require` 模式）**，与 `laravel-admin`、`filament` 本身一致。完整规范见 `docs/prd/01-项目规范与目录结构.md`。

- **目标命名空间**：`FilamentAdmin\`，源码放在 `src/` 下
- **目标安装方式**：`composer require filament-admin/filament-admin`
- **目标注册方式**：用户在 `AdminPanelProvider` 中 `->plugins([FilamentAdminPlugin::make()])`
- **目标扩展方式**：`php artisan filament-admin:publish --model=Xxx` 生成继承 stub

**当前代码状态**：代码仍在 `app/`（Skeleton 结构），PRD 文档（`docs/prd/`）写完后再启动重构。**在重构完成前，不要在 `app/` 下继续新建属于 Library 包核心的类**（如新 Model、新 Resource、新 Service）；若确需开发，按 PRD 规范在 `src/` 下新建。

## 环境关键差异（容易踩坑）

- **MySQL 端口 3380**（Docker，非默认 3306）；Redis 6379 密码 `123456` DB 15
- **测试库分离**：`phpunit.xml` 硬编码连接 `filamentadmin_test`，需手动创建：`mysql -uroot -p123456 -h127.0.0.1 -P3380 -e "CREATE DATABASE filamentadmin_test"`
- **本地域名**：`http://filamentadmin.local`（Nginx，PHP-FPM 8.3），不是 `artisan serve` 默认端口
- composer install 前先 `unset HTTP_PROXY HTTPS_PROXY http_proxy https_proxy`（系统代理常超时）

## 认证体系（与 Laravel 默认不同）

- 后台用 `admin` guard + `admin_users` 表，**不是** 默认的 `web`/`users`
- `AdminUser` 模型在 `app/Models/AdminUser.php`，已含 `SoftDeletes` + `TwoFactorAuthenticatable` + `HasRoles` + `FilamentUser`
- 自定义登录页 `app/Filament/Pages/Auth/Login.php` 支持 username **或** email 登录
- 2FA 用 `stephenjude/filament-two-factor-authentication`（已在 AdminPanelProvider 注册）
- 创建/操作管理员相关代码时，guard 必须显式传 `'admin'`（如 `actingAs($user, 'admin')`、`Role::create(['guard_name' => 'admin'])`）
- 权限基础已接入 `spatie/laravel-permission` + `bezhansalleh/filament-shield`；超级管理员通过 `Gate::before()` 绕过权限检查，角色/权限必须使用 `admin` guard。
- 操作日志当前技术路线固定为 `spatie/laravel-activitylog` 4.x + `alizharb/filament-activity-log` 1.3；当前 PHP 8.3 环境**不要**切到 Activitylog 5.x 方案。

## Filament 5 / Laravel 13 API 陷阱

- Filament 5 表单用 `use Filament\Schemas\Schema;` + `public static function form(Schema $schema): Schema` + `->components([...])`。**不要**用 Filament 3.x 旧 API `Forms\Form` + `->schema([])`。参考 `app/Filament/Pages/Auth/Login.php`
- Laravel 11+ 已移除框架 `Illuminate\Foundation\Support\Providers\AuthServiceProvider` 基类。注册 Policy 必须 `extends Illuminate\Support\ServiceProvider`，在 `boot()` 里手动 `Gate::policy()`
- Provider 注册在 `bootstrap/providers.php`（非 `config/app.php`）
- 异常处理在 `bootstrap/app.php` 的 `->withExceptions()` 块（非旧 `app/Exceptions/Handler.php`）
- 控制台调度在 `routes/console.php` 的 `Schedule::` facade（非旧 `app/Console/Kernel.php`）

## 发版流程（Release）

**Gitee 同步是人工步骤**，`release.yml` 只自动推 GitHub 包仓库，不自动推 Gitee。

每次发版前须手动执行：

```bash
# 本地完整发版（含 Gitee 推送）
scripts/release-package.sh vX.Y.Z

# 验证安装可用性
scripts/verify-package-install.sh vX.Y.Z

# 发版出错时回滚
scripts/release-rollback.sh vX.Y.Z
```

CI（`release.yml`）在 push `v*` tag 时自动触发：subtree split → 推 GitHub 包仓库 → 打包仓库 tag → 创建 GitHub Release → 验证 tag 存在 + Packagist 同步（warning-only）。

**所需 GitHub Secrets（一次性配置，参见 GitHub Settings → Secrets and variables → Actions）：**
- `PACKAGE_GITHUB_TOKEN`：Fine-grained PAT，scope `john-captain/filament-admin` `contents: write`
- `CI_APP_KEY`：合法的 Laravel APP_KEY（`php artisan key:generate --show` 生成，填入 secret 值）

## 常用命令（项目特定）

```bash
composer dev          # 一键起 serve + queue + pail + vite（concurrently）
composer test         # 先 config:clear 再跑 Pest（清缓存是 test 脚本一部分）
composer phpstan      # vendor/bin/phpstan analyse（用 phpstan.neon 配置，level 6，勿加 --level）
composer pint         # 自动格式化
composer pint:test    # 仅检查不修改
php artisan test tests/Feature/Xxx.php   # 单文件
php artisan test --filter='测试名称'      # Pest 测试名是中文，filter 时注意
```

## 测试约定

- Pest 4，`tests/Pest.php` **已在 Feature 和 Unit 两个套件上自动 apply `RefreshDatabase`**——写 Unit 测试时也会清库
- Pest 测试用中文 `it()` 描述（如 `it('超级管理员绕过所有权限检查', ...)`）
- 全局 helper `assertDatabaseHasInOrder()` 已在 `tests/Pest.php` 定义
- `BCRYPT_ROUNDS=4`、`CACHE_STORE=array`、`SESSION_DRIVER=array`、`QUEUE_CONNECTION=sync`（来自 `phpunit.xml`，与本地 `.env` 不同）
- 集成 Spatie Permission 后，测试需要 `app(PermissionRegistrar::class)->forgetCachedPermissions()` 避免跨测试缓存污染

## 代码风格（Pint 自定义规则，非纯 laravel preset）

- `=>` 单空格最小对齐、`=` 对齐（多行赋值时手动对齐）
- 数组末尾逗号必加、其他元素不加
- `return` 前必须空行
- 强制 PHPDoc 中文注释
- 命名禁止拼音
- commit message 用中文

## 目录指引

- `wiki/` 对外官方文档目录（安装、使用、配置、API 参考），面向包的使用者，可同步到 GitHub Wiki 或文档站；写作规范见 `wiki/AGENTS.md`
- `docs/dev/` 开发内部笔记、规划、梳理、验收清单（原 `doc/` 内容已迁移至此）
- `docs/prd/` 产品需求文档（内部，定义功能规范）
- `docs/superpowers/` superpowers skill 的产出目录（specs + plans + reviews），内部使用
- `app/` 是当前 Skeleton 遗留代码位置，重构完成前只维护不扩展；重构目标是将核心代码迁移到 `src/`（命名空间 `FilamentAdmin\`）
- `src/` 是 Library 包目标源码目录，重构启动后所有新代码在此新建，规范见 `docs/prd/01-项目规范与目录结构.md`
- `app/Filament/` 当前存有 `Pages/Auth/Login.php` 和各 Resource；Shield 自带 RoleResource 由包提供，不在本项目手写
- `app/Listeners/LogAdminLogin.php` 通过 Laravel 自动发现注册，**不要**在 AppServiceProvider 再手动 `Event::listen`
- `packages/plugin-platform/` 是插件市场基础包目录；除本目录的联调桥接外，不要再按"宿主内大功能工作区"理解它
- `database/migrations/0001_01_01_*_create_users_table.php` 是 Laravel 默认 users 表，**保留但不使用**（项目用 admin_users）

## 不要做

- 不要把 PHPStan level 写进命令行（`phpstan.neon` 已配 level 6，重复指定会冲突）
- 不要在生产 PHP 文件里加 emoji（CLAUDE.md/AGENTS.md 全局约定）
- 不要在没读 `docs/prd/01-项目规范与目录结构.md`、`wiki/guide/overview.md`、`docs/dev/一期开发后的梳理.md`、`docs/dev/项目开发规划.md` 前修改架构相关代码——它们分别定义包规范、公开说明、当前状态、开发路线
- 不要在 `app/` 下继续新建属于 Library 包核心的类（Model、Resource、Service）；重构完成前只维护现有代码，新增功能按 PRD 规范在 `src/` 下开发
- 不要把菜单、系统配置、操作日志、媒体库、API、插件中心等规划能力写成已完成，除非对应代码、迁移、测试和文档都已落地
- 不要在写文档或改 README 时虚标完成度——必须先核对代码和测试，再标注 `已完成` / `已铺垫` / `待开发`，宁可保守
- 不要再把一个功能块拆成长期半成品；如果某次只完成底层铺垫，必须在文档里标注为 `已铺垫`，不能标注为 `已完成`
- 不要为每个 Resource 自写 RoleResource——使用 `bezhansalleh/filament-shield` 4.x 自带 RoleResource
