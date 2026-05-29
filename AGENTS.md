# AGENTS.md — FilamentAdmin

Laravel 13 + Filament 5 后台基础平台。第一版开发中，分 8 个功能域顺序推进，详见 `docs/superpowers/specs/2026-05-28-filament-admin-v1-development-plan.md`。

## 环境关键差异（容易踩坑）

- **MySQL 端口 3380**（Docker，非默认 3306）；Redis 6379 密码 `123456` DB 15
- **测试库分离**：`phpunit.xml` 硬编码连接 `filamentadmin_test`，需手动创建：`mysql -uroot -p123456 -h127.0.0.1 -P3380 -e "CREATE DATABASE filamentadmin_test"`
- **本地域名**：`http://filamentadmin.local`（Nginx，PHP-FPM 8.3），不是 `artisan serve` 默认端口
- composer install 前先 `unset HTTP_PROXY HTTPS_PROXY http_proxy https_proxy`（系统代理常超时）

## 认证体系（与 Laravel 默认不同）

- 后台用 `admin` guard + `admin_users` 表，**不是** 默认的 `web`/`users`
- `AdminUser` 模型在 `app/Models/AdminUser.php`，已含 `SoftDeletes` + `TwoFactorAuthenticatable` + `FilamentUser`
- 自定义登录页 `app/Filament/Pages/Auth/Login.php` 支持 username **或** email 登录
- 2FA 用 `stephenjude/filament-two-factor-authentication`（已在 AdminPanelProvider 注册）
- 创建/操作管理员相关代码时，guard 必须显式传 `'admin'`（如 `actingAs($user, 'admin')`、`Role::create(['guard_name' => 'admin'])`）

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
- 阶段完成打 Tag：`v0.X.0-<功能名>`（中文），第一版终点 `v1.0.0`

## 目录指引

- `app/Filament/` 已存在，目前只有 `Pages/Auth/Login.php`，Resources 目录待建
- `app/Listeners/LogAdminLogin.php` 通过 Laravel 自动发现注册，**不要**在 AppServiceProvider 再手动 `Event::listen`
- `docs/superpowers/` 是 superpowers skill 的产出目录（specs + plans + 后续 reviews）
- `doc/`（单数）是早期需求文档（`需求.md`/`需求2.md`），与 `docs/`（复数）不同，**不要混用**
- `database/migrations/0001_01_01_*_create_users_table.php` 是 Laravel 默认 users 表，**保留但不使用**（项目用 admin_users）

## 不要做

- 不要把 PHPStan level 写进命令行（`phpstan.neon` 已配 level 6，重复指定会冲突）
- 不要在生产 PHP 文件里加 emoji（CLAUDE.md/AGENTS.md 全局约定）
- 不要在没读 `docs/superpowers/specs/2026-05-28-filament-admin-v1-development-plan.md` 前修改架构相关代码——它定义了 8 个功能域的边界
- 不要为每个 Resource 自写 RoleResource——后续要集成的 `bezhansalleh/filament-shield` 4.x 自带 RoleResource
