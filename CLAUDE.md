<!-- GSD:project-start source:PROJECT.md -->

## Project

**Filamentboot**

Filamentboot 是对标 FastAdmin / laravel-admin 的 Laravel 13 + Filament 5 后台基础平台，以 Composer 包 `filamentboot/filamentboot` 形态发布。独立开发者、外包公司和企业 IT 通过 `composer require` 即可拿到一套含认证、权限、菜单、操作日志、部门数据权限的后台底座，在上面直接构建业务模块而无需重建基础设施。

直接对标：**`siubie/kaido-kit`** (383★ Filament 3.x starter kit，国外同路线对手)、FastAdmin（ThinkPHP，国内同路线但技术栈老）。

**Core Value:** **别人执行 `composer require filamentboot/filamentboot` 后能开箱运行、能扩展定制、能稳定升级，且包发布形态完全符合 Laravel 开源市场规范。**

如果其他一切都失败，这一句必须为真。当前 v0.4.x 是"装得上但无法 publish 任何资源、PublishCommand 是空壳"——属于"已发包但未对外可用"，**v0.5 的核心使命就是修复这个**。

### Constraints

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

<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->

## Technology Stack

## 概览

- **演示项目（根目录）**：Composer 名 `filament-admin/preview`，类型 `project`，作为本地开发与功能演示的 Laravel 13 应用。
- **主包（`packages/filamentboot/`）**：Composer 名 `filamentboot/filamentboot`，类型 `library`，是真正发布给下游使用的 Filament 5 + Laravel 13 后台基础包。

## 语言与运行时

| 项 | 版本 / 取值 | 来源 |
|----|------------|------|
| PHP | `^8.3` | `composer.json:19`、`packages/filamentboot/composer.json:19` |
| Laravel Framework | `^13.8` | `composer.json:26` |
| Filament | `^5.0` | `composer.json:23` |
| Node | 通过 `npm` 运行 Vite，未在 `package.json` 锁定具体版本 | `package.json` |
| 数据库默认值 | `mysql`，`127.0.0.1:3380`，库名 `filamentadmin` | `.env.example:20-25` |
| 缓存 / 会话 / 队列 | `redis`（`SESSION_DRIVER`、`CACHE_STORE`、`QUEUE_CONNECTION`） | `.env.example:27-37` |
| Redis 客户端 | `phpredis`，DB index 15，端口 6379 | `.env.example:40-44` |

## 框架与核心运行库

| 包 | 版本约束 | 用途 | 文件 |
|----|---------|------|------|
| `laravel/framework` | `^13.8` | 应用框架核心 | `composer.json:26` |
| `filament/filament` | `^5.0` | 后台 Resource / Panel 框架 | `composer.json:23` |
| `laravel/sanctum` | `^4.0` | API Token 与 SPA 认证 | `composer.json:27`、`config/sanctum.php` |
| `laravel/tinker` | `^3.0` | REPL（仅演示项目） | `composer.json:28` |

## Filament 与第三方插件

| 包 | 版本约束 | 作用 |
|----|---------|------|
| `bezhansalleh/filament-shield` | `^4.0` | RBAC 权限管理（与 `spatie/laravel-permission` 联动） |
| `alizharb/filament-activity-log` | `^1.3` | 操作日志展示 |
| `filament/spatie-laravel-media-library-plugin` | `^5.6` | 媒体库表单/表格集成 |
| `filament/spatie-laravel-settings-plugin` | `^5.6` | 设置项表单集成 |
| `solution-forest/filament-tree` | `^4.0` | 树形资源（部门、菜单等场景） |
| `stephenjude/filament-two-factor-authentication` | `^4.1` | Filament 双因素认证 |

## Spatie 生态

| 包 | 版本约束 | 作用 |
|----|---------|------|
| `spatie/laravel-permission` | `^6.0` | 角色 / 权限模型，`config/permission.php` |
| `spatie/laravel-activitylog` | `^4.12` | 操作审计（通过 `ActivityLogObserver` 全局接入） |
| `spatie/laravel-medialibrary` | `^11.23` | 媒体附件 |
| `spatie/laravel-settings` | `^3.9` | 应用 / 包级设置持久化 |

## 仅演示项目的开发工具（`require-dev`）

| 包 | 版本约束 | 用途 |
|----|---------|------|
| `pestphp/pest` | `^4.7` | 主测试框架 |
| `pestphp/pest-plugin-laravel` | `^4.1` | Pest 的 Laravel 适配 |
| `phpunit/phpunit` | `^12.5.12` | 作为 Pest 底座的 PHPUnit |
| `mockery/mockery` | `^1.6` | 测试替身 |
| `fakerphp/faker` | `^1.23` | 工厂随机数据 |
| `larastan/larastan` | `^3.0` | 静态分析 |
| `laravel/pint` | `^1.27` | 代码格式化（PSR-12 风格） |
| `laravel/pail` | `^1.2.5` | 日志尾随 |
| `laravel/pao` | `^1.0.6` | Laravel 周边工具 |
| `knuckleswtf/scribe` | `^5.10` | API 文档生成（产物在 `.scribe/`） |
| `nunomaduro/collision` | `^8.6` | 美化 CLI 错误输出 |

## 主包的开发依赖（`packages/filamentboot/composer.json`）

| 包 | 版本约束 | 用途 |
|----|---------|------|
| `orchestra/testbench` | `^10.0` | 包测试容器 |
| `pestphp/pest` | `^4.7` | Pest 测试 |
| `phpunit/phpunit` | `^12.5.12` | 底座 |
| `mockery/mockery` | `^1.6` | Mock |

## 前端构建链

| 项 | 取值 | 文件 |
|----|------|------|
| 构建器 | Vite `^8.0.0` | `package.json:14` |
| 样式 | Tailwind CSS `^4.0.0` + `@tailwindcss/vite` `^4.0.0` | `package.json:10,13` |
| Laravel 集成 | `laravel-vite-plugin` `^3.1` | `package.json:12` |
| 并发开发脚本 | `concurrently` `^9.0.1`（被 `composer dev` 引用） | `package.json:11`、`composer.json:80-81` |
| 模块类型 | `"type": "module"` | `package.json:4` |
| 脚本 | `npm run dev` / `npm run build` | `package.json:6-8` |

## Composer 关键配置

- `repositories[0]`：本地路径 `packages/filamentboot`，`symlink: true` — 演示项目即时拿到主包改动。
- `autoload.psr-4`：`App\\` → `app/`，`Database\\Factories\\` → `database/factories/`，`Database\\Seeders\\` → `database/seeders/`。
- `autoload-dev.psr-4`：`Tests\\` → `tests/`。
- `scripts.setup`：拷贝 `.env`、`key:generate`、`migrate --force`、`npm install`、`npm run build`。
- `scripts.dev`：`concurrently` 并行启动 `serve / queue:listen / pail / vite`。
- `scripts.test`：先 `config:clear` 再 `artisan test`。
- `scripts.phpstan` / `scripts.pint` / `scripts.pint:test`：静态分析与格式化。
- `post-autoload-dump`：执行 `package:discover` 与 `filament:upgrade`。
- `config.optimize-autoloader: true`、`sort-packages: true`、允许 `pestphp/pest-plugin` 与 `php-http/discovery` 插件。
- `autoload.psr-4`：`Filamentboot\\` → `src/`，工厂与种子置于 `Filamentboot\\Database\\Factories\\` / `Seeders\\`（与根项目命名空间互不冲突）。
- `autoload-dev.psr-4`：`Filamentboot\\Tests\\` → `tests/`。
- `extra.laravel.providers`：`Filamentboot\\FilamentbootServiceProvider`（自动包发现）。

## 关键配置文件清单（`config/`）

- `app.php`、`auth.php`、`session.php`、`cache.php`、`database.php`、`logging.php`、`mail.php`、`queue.php`、`filesystems.php`、`services.php`、`sanctum.php` — Laravel 通用配置。
- `filamentboot.php` — 主包暴露的核心配置（`super_admin_role`、`log_retention_days`），通过 `SUPER_ADMIN_ROLE`、`LOG_RETENTION_DAYS` 环境变量覆盖。
- `filament-shield.php` — Shield 权限注册策略。
- `filament-activity-log.php` — 活动日志展示选项。
- `permission.php` — Spatie 权限缓存与表名。
- `activitylog.php` — Spatie 审计日志。
- `media-library.php` / `settings.php` — Spatie 媒体与设置。
- `scribe.php` — API 文档生成器配置（中文标题、`base_url` 取自 `app.url`）。
- `official-market.php`、`plugin-platform.php` — 项目自有的市场 / 插件平台配置（占位/前期布点）。

## 基础设施默认值（`.env.example`）

- `APP_NAME=Filamentboot`，`APP_LOCALE=en`，`APP_FALLBACK_LOCALE=en`。
- `DB_CONNECTION=mysql`，`DB_HOST=127.0.0.1`，`DB_PORT=3380`，`DB_DATABASE=filamentadmin`。
- `SESSION_DRIVER=redis`，`SESSION_LIFETIME=120`。
- `CACHE_STORE=redis`，`QUEUE_CONNECTION=redis`。
- `REDIS_CLIENT=phpredis`，`REDIS_PORT=6379`，`REDIS_DB=15`，`REDIS_PASSWORD=your-redis-password`（占位）。
- `MAIL_MAILER=log`（默认仅写日志，便于本地开发）。
- `FILESYSTEM_DISK=local`；S3 凭证字段已预留但未填值。
- `BCRYPT_ROUNDS=12`、`BROADCAST_CONNECTION=log`。

## 总结

<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->

## Conventions

## 命名规范

### 文件

- **PHP 类文件**：PascalCase（大驼峰），如 `LoginRequest.php`、`ApiException.php`
- **PHP 模型文件**：PascalCase，单数形式，如 `AdminUser.php`、`Department.php`
- **测试文件**：`*Test.php` 或 `*ApiTest.php`，如 `AdminUserTest.php`、`AdminAuthApiTest.php`
- **迁移文件**：`create_table_names_table.php`（Laravel 标准）

### 函数和方法

- **方法名**：camelCase（小驼峰），如 `getCurrentUser()`、`getDescendantIds()`
- **查询方法**：使用名词形式，如 `loginLogs()`、`getDescendantIds()`、`wouldCreateCycle()`
- **布尔方法**：使用 `is*`、`has*`、`can*` 前缀，如 `isForceDeleting()`、`canAccessPanel()`
- **静态工厂**：`factory()` 用于 Eloquent 模型工厂，如 `AdminUser::factory()->create()`

### 变量

- **一般变量**：camelCase，如 `$adminUser`、`$childrenIds`、$descendantIds`
- **集合变量**：复数形式，如 `$descendantIds`、`$parentIds`
- **常量**：UPPER_SNAKE_CASE，如 `UPDATED_AT`

### 类型和枚举

- **Enum 名称**：PascalCase，如 `ApiErrorCode`、`AdminUserStatus`
- **Enum 值**：UPPER_SNAKE_CASE（PHP 枚举约定），如 `SERVER_ERROR`、`VALIDATION_FAILED`
- **异常类**：PascalCase，以 `Exception` 后缀结尾，如 `ApiException`、`ValidationException`

## 代码风格

### 格式化工具

- **配置文件**：`pint.json`
- **预设**：`laravel`（遵循 PSR-12 标准）
- **运行命令**：`composer pint`
- **检查（不修改）**：`composer pint:test`

### 缩进和空格

- **缩进**：4 个空格（`.editorconfig` 配置）
- **行尾**：LF（Unix 换行符）
- **文件末尾**：必须有空行（`insert_final_newline = true`）
- **尾部空白**：自动删除（`trim_trailing_whitespace = true`）

### 关键格式规则

- **多行数组**：最后一个元素后需要逗号（`trailing_comma_in_multiline`）
- **二元运算符**：`=>` 对齐单空格，`=` 对齐（`binary_operator_spaces`）
- **return 语句**：return 前留一空行（`blank_line_before_statement`）
- **未使用导入**：自动移除（`no_unused_imports`）
- **PHPDoc**：标量类型要有定义（`phpdoc_scalar`）

### 编辑器配置

- 所有文件：UTF-8 编码，4 空格缩进，LF 换行
- YAML 文件：2 空格缩进
- Docker Compose 文件：4 空格缩进

## 导入组织

### 顺序

### 路径别名

## 错误处理

### 优先使用异常（Exception-First）

- 位置：`app/Enums/ApiErrorCode.php`
- 分段：1xxx（通用错误）、2xxx（认证与授权错误）
- 包含方法：`httpStatus()`、`defaultMessage()`

### 异常类

- `App\Exceptions\ApiException`：业务异常，被全局异常处理捕获，返回标准 API 格式
- 位置：`app/Exceptions/ApiException.php`

## 日志

### 框架

### 使用模式

- 活动日志：通过 Spatie Activity Log 包记录模型变更
- 登录日志：通过事件监听器和观察器自动记录
- 活动日志观察器：`packages/filamentboot/src/Observers/ActivityLogObserver.php`
- 登录日志监听器：`packages/filamentboot/src/Listeners/LogAdminLogin.php`

## PHPDoc 和注释

### 何时添加注释

- **类头部**：必须有 PHPDoc 注释说明类的用途
- **公开方法**：必须有 PHPDoc 注释说明参数、返回值、异常
- **复杂逻辑**：在关键步骤添加行内注释（中文）
- **临时代码**：避免使用 FIXME/TODO，改为创建 Issue

### PHPDoc 格式

### 类型注解

- **参数类型**：使用完整类型声明，如 `string`、`int`、`array<string, mixed>`
- **返回类型**：必须声明，如 `: JsonResponse`、`: array`
- **属性类型**：模型属性使用 `@property` 标注，如：
- **集合类型**：使用 `list<T>` 或 `array<K, V>`，如 `@return list<int>`

### 模型工厂文档

## 函数设计

### 函数大小

- 单个函数保持简洁，通常不超过 30 行
- 复杂逻辑分解为多个私有方法

### 参数规范

- **参数数量**：单个公开方法参数不超过 3 个，过多参数改为对象/DTO
- **类型声明**：所有参数必须有类型声明
- **默认值**：有默认值的参数放在后面

### 返回值规范

- **返回类型**：必须声明具体返回类型，不使用 `mixed`（特殊情况除外）
- **布尔返回**：布尔值通常代表检查结果，避免混淆
- **null 返回**：使用 `?Type` 显式标注可能返回 null

## 模块设计

### 导出规范

- **类导出**：只导出需要外部使用的类
- **静态工厂**：模型使用 `::factory()` 创建测试数据
- **服务定位器**：使用 `app(ClassName::class)` 获取容器实例

### 模块结构

- `Models/`：Eloquent 模型
- `Enums/`：业务枚举
- `Exceptions/`：应用异常
- `Http/Controllers/`：HTTP 控制器
- `Http/Requests/`：表单请求验证
- `Providers/`：服务提供者
- `Models/`：核心模型
- `Services/`：业务服务类
- `Listeners/`：事件监听器
- `Observers/`：模型观察器
- `Policies/`：授权策略
- `Filament/`：Filament 资源和页面

### 关键服务类

- `DepartmentTree`：部门树形结构管理
- `ActivityLogger`：活动日志记录
- `AdminNavigationBuilder`：后台导航构建
- 位置：`packages/filamentboot/src/Services/`

## 编码约定示例

### 枚举定义

### 服务类定义

### 控制器方法

<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->

## Architecture

## 系统概览

- **主包**（`packages/filamentboot/`）：发布为 Packagist 包 `filamentboot/filamentboot`，包含所有核心功能
- **演示项目**（项目根目录）：本地开发和集成测试用，依赖主包的本地副本进行开发

```text

```

## 核心模式概览

| 维度 | 模式 |
|------|------|
| **交付形式** | Composer Library 包（非 Skeleton Project） |
| **认证方式** | Laravel Sanctum Bearer Token（API）+ Guard 身份验证（Filament） |
| **权限体系** | RBAC（Role-Based Access Control）+ Spatie Permission + Gate::before 超级管理员 |
| **操作审计** | 自动 Observer + ActivityLogger 服务 |
| **菜单导航** | 数据库驱动 + AdminNavigationBuilder 动态构建 |
| **文件处理** | Spatie Media Library（头像、文件上传） |

## 分层与职责

### 第1层：Web 入口与路由

- Web 路由：`routes/web.php` - 提供静态首页和插件市场入口
- API 路由：`routes/api.php` - 提供 `/api/v1/admin/*` 端点（登录、当前用户、登出）
- 异常处理：`bootstrap/app.php` 统一捕获 ApiException、ValidationException、AuthenticationException

```php

```

### 第2层：HTTP Controller

- 接收 HTTP 请求，参数验证
- 调用业务服务层（通常为 Model + Service）
- 返回 API 标准格式（通过 Response::macro 注册）
- `login()` - 支持 account 或 email 登录，返回 Sanctum Bearer Token
- `me()` - 返回当前已认证用户信息
- `logout()` - 删除当前 Token

### 第3层：Service 业务层

- **ActivityLogger** - 操作日志记录
- **AdminNavigationBuilder** - 后台导航动态构建
- **DepartmentTree** - 部门树遍历

### 第4层：Filament Resource 层（前端控制器）

- **Login.php** - 自定义登录页，支持 account/email 双字段登录
- **Profile.php** - 个人资料页，编辑昵称、邮箱、头像等
- WelcomeWidget、SystemStatsWidget、QuickActionsWidget、RecentActivityWidget、QuickGuideWidget

### 第5层：Model 数据模型与关系

### 第6层：Policy 授权策略

- 所有 Policy 继承 BasePolicy
- 权限命名格式：`{action}_{resource_snake_case}`
- 例：`view_any_admin_user`、`create_admin_user`、`update_admin_user`、`delete_admin_user`
- 权限命名与 Filament Shield 4.x 配置严格对齐
- `AdminUserPolicy`、`DepartmentPolicy`、`MenuPolicy`、`LoginLogPolicy`、`RolePolicy`、`ActivityLogPolicy`

### 第7层：Observer 观察者与事件

- 在 FilamentbootServiceProvider 注册观察的模型：AdminUser、Department、Menu、Role
- 监听生命周期事件：created、updating、updated、deleting、deleted、restoring、restored
- 快照机制：updating 时存储前状态，updated 时计算变更，调用 ActivityLogger 写入日志
- 忽略字段：password、remember_token、two_factor_secret、two_factor_recovery_codes、created_at、updated_at
- 监听 Illuminate\Auth\Events\Login 和 Failed
- 成功登录：创建 LoginLog 记录，更新 AdminUser.last_login_at、last_login_ip、login_failures=0
- 失败登录：创建 LoginLog 记录，累加对应用户的 login_failures

### 第8层：数据库与迁移

| 表名 | 用途 | 包来源 |
|------|------|--------|
| admin_users | 管理员用户 | filamentboot |
| departments | 部门 | filamentboot |
| menus | 后台菜单 | filamentboot |
| login_logs | 登录日志 | filamentboot |
| activity_log | 操作审计日志 | spatie/laravel-activitylog |
| roles | 角色 | spatie/laravel-permission |
| permissions | 权限点 | spatie/laravel-permission |
| role_has_permissions | 角色权限关联 | spatie/laravel-permission |
| model_has_roles | 用户角色关联 | spatie/laravel-permission |
| personal_access_tokens | API Token（Sanctum） | laravel/sanctum |
| media | 媒体文件元数据 | spatie/laravel-medialibrary |

## 数据流

### 主流程：用户登录 → 后台访问

### API 流程：token 登录 → API 调用

### 操作审计流程

## 关键抽象与约定

### Plugin 扩展机制

- 用户通过 `->plugins([FilamentbootPlugin::make()])` 注册到 Filament panel
- 支持绑定自定义 Model 和 Resource：
- 注册 Resources、Pages、Widgets 到 Filament panel
- BasePolicy 自动推导权限点名称：类名去 Policy 后缀 → snake_case
- AdminUserPolicy → admin_user
- LoginLogPolicy → login_log
- 配合 Filament Shield 的 snake + _ 分隔符设置

### Trait 与 Mixin

| Trait | 模型 | 功能 |
|-------|------|------|
| HasApiTokens | AdminUser | Sanctum API Token 管理 |
| HasRoles | AdminUser | Spatie Permission 角色权限 |
| InteractsWithMedia | AdminUser | Spatie Media Library 文件处理 |
| TwoFactorAuthenticatable | AdminUser | 双因素认证（TOTP） |
| SoftDeletes | AdminUser、Department、Menu | 软删除支持 |
| ModelTree | Menu、Department | 树形关系操作（SolutionForest） |
| HasFactory | 所有 Model | Factory 工厂支持 |

### 设置与配置

- GeneralSettings - 系统基础设置
- SecuritySettings - 安全配置（密码策略、IP白名单等）
- LogSettings - 日志配置（保留天数、级别等）
- UploadSettings - 上传配置（大小限制、允许格式等）

## 入口点详解

### Filament 面板入口

- 继承 PanelProvider
- 配置 panel id、path、login page、auth guard、plugins
- 注册 FilamentbootPlugin、TwoFactorAuthenticationPlugin、FilamentShieldPlugin、ActivityLogPlugin
- 自定义导航构建：navigation() 回调调用 AdminNavigationBuilder->build()

### artisan 命令入口

- PublishCommand：生成用户可自定义的 Model、Resource、Page stub 到 app/
- 支持 `--model=AdminUser` 或 `--resource=AdminUserResource` 单个发布
- 支持 `--all` 发布所有扩展
- `php artisan filamentboot:clean-activity-logs {--days=180}` - 清理旧操作日志
- `php artisan filamentboot:clean-login-logs {--days=90}` - 清理旧登录日志

### 应用启动入口

- 定义 routing、middleware、exception handling
- 异常处理：ApiException → `response()->apiError()`、ValidationException → 字段错误、AuthenticationException → 401
- Middleware：ResetAuthGuards 在 API 请求前重置认证 guard
- 注册 API 响应 Macros：`response()->api()`、`response()->apiError()`、`response()->apiPaginated()`

## 主包与演示项目边界

### 主包职责（packages/filamentboot/）

- **Model 定义** - AdminUser、Department、Menu、LoginLog、Role（委托给 Spatie Permission）
- **Filament Resource** - CRUD 前端界面、表单验证、批量操作
- **Service 层** - ActivityLogger、AdminNavigationBuilder、DepartmentTree 等业务逻辑
- **Policy & Observer** - 权限检查、操作审计
- **迁移与种子** - 数据库表定义、初始数据
- **配置与翻译** - filamentboot.php、2FA 中文翻译

### 演示项目职责（项目根目录）

- **入口与路由** - routes/web.php（静态页）、routes/api.php（API 端点）
- **HTTP Controller** - Api\V1\Admin\AuthController、OfficialMarketIndexController
- **扩展与自定义** - 可通过 `filamentboot:publish` 继承或覆盖主包类
- **应用配置** - bootstrap/app.php、config/、Filament AdminPanelProvider
- **演示数据** - 种子、用户、测试用例
- **本地集成测试** - tests/ 验证主包与演示项目的兼容性

### 关键边界

| 功能 | 主包位置 | 演示项目位置 |
|------|---------|-----------|
| AdminUser Model | `src/Models/AdminUser.php` | 无（使用主包，或通过 publish 继承） |
| 登录页 Resource | `src/Filament/Pages/Auth/Login.php` | 无（使用主包） |
| API Controller | 无 | `app/Http/Controllers/Api/V1/Admin/AuthController.php` |
| Filament Panel | 无 | `app/Providers/Filament/AdminPanelProvider.php` |
| 数据库迁移 | `database/migrations/` | 无（运行主包迁移） |
| 权限 Policy | `src/Policies/` | 无（使用主包） |

## 中间件与全局流程

## 错误处理与响应格式

```php

```

- ApiException → `errorCode` + `message` + `data`
- ValidationException → `VALIDATION_FAILED` + 字段错误列表
- AuthenticationException → `UNAUTHENTICATED`
- 其他异常 → `SERVER_ERROR`

<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->

## Project Skills

No project skills found. Add skills to any of: `.claude/skills/`, `.agents/skills/`, `.cursor/skills/`, `.github/skills/`, or `.codex/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->

## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:

- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->

## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
