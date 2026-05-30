# AGENTS.md — FilamentAdmin

Laravel 13 + Filament 5 后台基础平台。当前按功能块分期交付，做成一块、发布一块；主路线详见 `doc/项目开发规划.md`，历史详细计划见 `docs/superpowers/specs/2026-05-28-filament-admin-v1-development-plan.md`。

## 公开说明与真实状态

- 面向发布上线的项目说明文档是 `docs/guide/overview.md`。它传达的核心原则是：**目标、规划、已完成状态必须分开写**，不要把后续阶段能力提前描述成已落地。
- 当前开发后梳理和差距分析文档是 `doc/一期开发后的梳理.md`，用于记录当前代码状态、竞品差距、插件规划和后续阶段分析。
- 后续开发执行规划文档是 `doc/项目开发规划.md`，用于约束功能块分期顺序、子功能范围和完成标准；以后按“做一个功能块，就把该功能块完整收口并阶段发布”的方式推进。
- 截至 2026-05-29，当前已落地能力集中在：后台登录（username/email）、2FA 基础接入、登录日志、管理员模型层、Spatie Permission + Shield 权限基础、`AdminUser` 软删除。
- 当前仍未落地：管理员 Resource UI、登录日志 Resource UI、菜单管理、系统配置、操作日志、媒体库、Sanctum/API、插件中心、基础导出、个人资料页、仪表盘统计卡片、多语言预留实现。
- 写文档或改 README 时，必须先核对代码和测试，再标注 `已完成` / `已铺垫` / `待开发`。宁可保守，也不要虚标完成度。

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

## 开发流程约定

- 当前分支 `feature/phase-1-authentication`（功能分支模式）
- TDD：写失败测试 → 实现 → 测试通过 → commit。参考 `docs/superpowers/plans/` 已有计划文件结构
- 规格/计划/设计文档全部进 `docs/superpowers/specs/` 和 `docs/superpowers/plans/`，命名 `YYYY-MM-DD-<topic>.md`
- 每个功能块完成后可打阶段 Tag：`v0.X.0-<功能名>`（中文）；是否定义稳定大版本号后续再定。
- 涉及对外项目说明、项目范围和代码基座价值主张时，优先对齐 `docs/guide/overview.md`；涉及当前代码状态和竞品差距时，对齐 `doc/一期开发后的梳理.md`；涉及实施顺序、功能块边界和验收标准时，优先对齐 `doc/项目开发规划.md`；涉及更细任务拆解时，再参考 `docs/superpowers/specs/2026-05-28-filament-admin-v1-development-plan.md`。

## 目录指引

- `app/Filament/` 已存在，目前只有 `Pages/Auth/Login.php`，项目自有 Resources 目录待建；Shield 自带 RoleResource 由包提供，不在本项目手写
- `app/Listeners/LogAdminLogin.php` 通过 Laravel 自动发现注册，**不要**在 AppServiceProvider 再手动 `Event::listen`
- `docs/superpowers/` 是 superpowers skill 的产出目录（specs + plans + 后续 reviews）
- `docs/guide/overview.md` 是面向公开发布的项目概览；`doc/`（单数）保留开发后梳理、项目开发规划、调研和业务设想；`docs/`（复数）承载公开文档、实施规格、计划、功能文档和开发文档，**不要混用产出位置**
- `database/migrations/0001_01_01_*_create_users_table.php` 是 Laravel 默认 users 表，**保留但不使用**（项目用 admin_users）

## 不要做

- 不要把 PHPStan level 写进命令行（`phpstan.neon` 已配 level 6，重复指定会冲突）
- 不要在生产 PHP 文件里加 emoji（CLAUDE.md/AGENTS.md 全局约定）
- 不要在没读 `docs/guide/overview.md`、`doc/一期开发后的梳理.md`、`doc/项目开发规划.md` 和 `docs/superpowers/specs/2026-05-28-filament-admin-v1-development-plan.md` 前修改架构相关代码——它们分别定义公开说明、当前状态、开发路线和历史阶段边界
- 不要把菜单、系统配置、操作日志、媒体库、API、插件中心等规划能力写成已完成，除非对应代码、迁移、测试和文档都已落地
- 不要再把一个功能块拆成长期半成品；如果某次只完成底层铺垫，必须在文档里标注为 `已铺垫`，不能标注为 `已完成`
- 不要为每个 Resource 自写 RoleResource——使用 `bezhansalleh/filament-shield` 4.x 自带 RoleResource
