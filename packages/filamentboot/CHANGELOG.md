# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 规范，
版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [0.13.0] - 2026-08-12

> **跨度说明**：上一个正式版本是 2026-06-24 的 [0.5.3]，中间跨越一年多——本仓库到七期批次 5 为止从未真正对外发布过一次，`[Unreleased]` 段积累的是这整段时间的变更。**沿用五期已定口径继续 0.x（不打 1.0）**：1.0 是对下游的 API 稳定承诺，而这份承诺至今没有经过真实安装场景检验，要等真实安装场景（下面的分流试装）跑完才成立。

### Added

- **API 统一响应格式**：`Illuminate\Http\Response` 新增三个宏 `api()` / `apiError()` /
  `apiPaginated()`，统一 `{success, message, data}`、`{success, message, error_code, data}`、
  分页 `{data, meta, links}` 三种 JSON 响应形状；`Enums\ApiErrorCode`
  （`defaultMessage()` / `httpStatus()`）与 `Exceptions\ApiException` 随之进包
- **包级 API / Web 路由**（`routes/api.php` / `routes/web.php`，随
  `FilamentbootServiceProvider::boot()` 的 `loadRoutesFrom` 无条件加载，不依赖宿主
  `bootstrap/app.php` 是否声明了 `withRouting(api:)`，`composer require` 本包即生效，
  当前无开关）：
  - `POST /api/v1/admin/login`、`GET /api/v1/admin/me`、`DELETE /api/v1/admin/logout`
    （Sanctum Bearer Token 鉴权，`Http\Controllers\Api\V1\Admin\AuthController` +
    `Http\Requests\Api\V1\Admin\LoginRequest`）
  - `GET /plugin-market/index.json`（`Http\Controllers\OfficialMarketIndexController`，
    插件市场索引，`web` 中间件组）
- **插件市场后台 UI**：`Filament\Resources\Plugins\PluginResource`（含 List / View 两个
  Page）+ `Filament\Pages\Marketplace\MarketplacePage`，随 `FilamentbootPlugin::register()`
  自动挂载到面板，无需在下游 `AdminPanelProvider` 手工注册；`Policies\PluginPolicy`、
  `Services\MarketplaceService`、`Services\PackagistService` 随之进包
- 两个新增可发布配置文件：`config/official-market.php`（官方插件市场条目清单）、
  `config/plugin-platform.php`（`plugin_platform.official_market_index_url`，默认拼
  `APP_URL` + `/plugin-market/index.json`），随 `filamentboot-config` publish tag 一并发布
- `install_plugin` / `uninstall_plugin` 两个权限点补进 `AdminFoundationPermissionSeeder`
  （此前只在宿主 `PluginPolicy` 里被引用，seeder 里从未声明过，下游即便拿到插件管理 UI
  也无法通过角色授予这两个操作）
- **插件管理 / 插件市场 / API 鉴权相关的 14 个测试文件从宿主 `tests/` 迁入包内**（七期批次
  4e）：这些测试验证的能力（`PluginManager` 初始化与依赖检查、Composer 安装/卸载 Job、
  混合发现、插件市场浏览与安装、Packagist 检索、面板动态注册、管理员 API 登录/鉴权、
  统一响应格式）已在 4a/4b/4c 批次随类文件一起搬进包，测试若继续留在宿主，下游
  `composer require filamentboot/filamentboot` 之后这些能力完全没有回归测试保护。原
  Pest 语法改写为包既有的 PHPUnit 经典类语法（`extends Orchestra\Testbench\TestCase`），
  新增两个测试专用基础设施：`Filamentboot\Tests\Support\TestAdminPanelProvider`（最小
  Filament 管理面板 Provider，供需要真实 Panel 注册环境的用例复用）与
  `StabilizeLivewireDataStoreProvider`（修复 Testbench 环境下 `Livewire::test()` 渲染
  完整 Filament Page 时 `Livewire\Mechanisms\DataStore` 单例丢失导致 `getErrorBag()`
  返回 `null` 的问题）

### Changed

- **BREAKING**：`FilamentbootPlugin::register()` 现在无条件挂载 2FA
  （`TwoFactorAuthenticationPlugin`）、角色权限（`FilamentShieldPlugin`）、操作日志
  （`ActivityLogPlugin`）三个面板插件——三者本就是 `composer.json` 的硬依赖，此前
  `filamentboot:install` 生成的 `AdminPanelProvider` stub 只在注释里提示"如需扩展请参阅文档
  后手动添加"，从未真正接线。**若下游此前已按旧提示在自己的 `AdminPanelProvider` 里手动
  注册过这三个插件，升级后会与包自动挂载的实例重复**，需要下游自查并删除手工注册的那一份
- **BREAKING**：包新增全局 API 异常渲染——`ExceptionHandler::renderable()` 在包
  `boot()` 阶段为 `ApiException` / `ValidationException` / `AuthenticationException` /
  `Throwable` 四类异常注册统一 JSON 错误响应回调，命中条件是请求路径匹配 `api/*` 或
  `$request->expectsJson()`。**若下游此前在 `bootstrap/app.php` 的 `withExceptions()`
  里已经为同样条件写过自定义 JSON 错误格式，两份回调会并存**——Laravel 按注册顺序取第一个
  返回非 null 的结果，需要下游确认新旧回调的注册顺序与预期是否一致

### Fixed

- **4 个 Dashboard Widget 的 `$view` 补齐 `filamentboot::` 命名空间前缀**（`QuickActionsWidget`
  / `QuickGuideWidget` / `WelcomeWidget` / `RecentActivityWidget`）：此前不带前缀，只在宿主
  `resources/views/filament/widgets/` 恰好有逐字副本时能解析到；下游仅 `composer require`
  本包、不带这份副本时，后台首页会直接 `ViewNotFoundException`。删掉了早期宿主项目里
  那份不再需要的重复副本

---

## [0.5.3] - 2026-06-24

### Security

- **批量赋值加固**：`AdminUser` / `Menu` / `Department` / `LoginLog` 四个模型由 `$guarded = []` 改为显式 `$fillable` 白名单，杜绝主键、`login_failures`、`last_login_*`、`two_factor_*` 等敏感字段被批量赋值注入
- **菜单批量操作后端鉴权**：菜单列表的批量启用/停用 BulkAction 在动作执行处补 `abort_unless(auth('admin')->user()?->can('update_menu'), 403)`，防止无权限用户绕过 UI 越权批量修改菜单
- **登录失败锁定**：新增 `AdminUserStatus::Locked` 状态；登录连续失败累计达 `SecuritySettings::login_throttle_max_attempts`（默认 5）后自动锁定账号，`canAccessPanel()` 拒绝锁定账号登录，缓解暴力破解

---

## [0.5.0] - 2026-06-11

### Added

- **Impersonation（用户模拟登录）**：集成 `stechstudio/filament-impersonate`，管理员列表一键切换身份，顶栏显示"结束模拟"横幅（中文覆盖），由 `ImpersonationListener` 自动写入操作日志
- **Scramble API 文档**：集成 `dedoc/scramble`，`/docs/api` 自动生成 OpenAPI 3.0 文档界面（Stoplight Elements），生产环境通过 `RestrictedDocsAccess` 中间件禁止访问
- **make:filament-admin-resource**：`php artisan make:filament-admin-resource {name}` 在用户项目生成 Resource + 三个 Pages（List/Create/Edit），委托 `StubGenerator` 服务统一渲染
- **filament-admin:publish --model / --resource / --all**：真实实现，生成 Model + Resource + Migration + FeatureTest 四件套，支持 `--force` 覆盖、`--only` / `--except` 过滤、`--path` 自定义输出路径
- **vendor:publish 5 个 tag 完整注册**：`filament-admin-config` / `filament-admin-migrations` / `filament-admin-views` / `filament-admin-lang` / `filament-admin-stubs`
- **包 CI（GitHub Actions）**：PHP 8.3 / 8.4 矩阵，含 PHPUnit、PHPStan、Pint 三个作业，`composer audit` 安全扫描（警告模式）
- **包元数据合规**：`laravelstack/filament-admin` Packagist 坐标、MIT License、CONTRIBUTING / SECURITY / CODE_OF_CONDUCT 文档

### Changed

- `StubGenerator` 抽取为独立服务（D-28），`PublishCommand` 与 `make:filament-admin-*` 命令统一委托调用，消除重复渲染逻辑
- `filament-admin:publish --path` 限制输出路径必须位于 `app/` 之内（安全修复 WR-08）

### Fixed

- PublishCommand `FeatureTest` 命名空间来源统一修复（WR-06/WR-07）
- `publishResource` 传递给 `renderStub` 的无效键删除（WR-04）
- `filament-impersonate` 翻译路径修复，注册时序调整确保 zh_CN 语言包正确加载（CR-02）

---

## [0.4.1] - 2026-06-03

### Changed

- 主包 Composer 坐标调整为 `laravelstack/filament-admin`（原 `filament-admin/filament-admin`）
- 同步修正安装文档、测试断言和发布口径

---

## [0.4.0] - 2026-06-03

### Added

- 独立包目录骨架初始化（`packages/filament-admin/`）
- `FilamentbootServiceProvider` 注册框架（publishes 空壳，v0.5 补全实现）
- `PublishCommand` 命令框架（v0.5 补全实现）
- Composer 元数据：`extra.laravel.providers`、`extra.branch-alias`、`support` 字段
- 包级 `phpunit.xml.dist` 与 Pest 4.x 测试框架配置
- 包级 PHPStan（`phpstan.neon`）与 Pint（`pint.json`）代码质量配置

---

## [0.3.0] - 2026-05-29

> **[ASSUMED]** — 对应历史 tag `v0.3.0-参数配置`，内容以代码状态推断。

### Added

- `config/filament-admin.php` 配置文件（`super_admin_role`、`log_retention_days`）
- `SUPER_ADMIN_ROLE` / `LOG_RETENTION_DAYS` 环境变量支持
- GeneralSettings / SecuritySettings / LogSettings / UploadSettings 系统设置类
- Filament Settings 页面集成（`filament/spatie-laravel-settings-plugin`）

---

## [0.2.0] - 2026-05-29

> **[ASSUMED]** — 对应历史 tag `v0.2.0-权限体系`，内容以代码状态推断。

### Added

- Spatie Permission 集成（`spatie/laravel-permission`），角色 / 权限模型（admin guard）
- Filament Shield 4.x 集成，自动注册 Resource 权限点
- `Gate::before` 超级管理员绕过机制
- `BasePolicy` 基类，统一权限命名规范（`{action}_{resource_snake_case}`）
- `AdminUserPolicy`、`DepartmentPolicy`、`MenuPolicy`、`LoginLogPolicy`、`RolePolicy`、`ActivityLogPolicy`
- `SuperAdminSeeder`，默认账号 `admin@example.com / password`
- 数据权限 5 种范围枚举（全部 / 本部门 / 本部门及下级 / 仅本人 / 指定部门）与 `DataScopeResolver`

### Changed

- `AdminUser` 模型新增 `HasRoles` Trait，接入 Spatie Permission

---

## [0.1.0] - 2026-05-28

> **[ASSUMED]** — 对应历史内部里程碑，初始骨架建立，内容以代码状态推断。

### Added

- Laravel 13 + Filament 5 后台骨架初始化
- `AdminUser` 模型（含 `HasApiTokens`、`TwoFactorAuthenticatable`、`InteractsWithMedia`、`SoftDeletes`）
- 自定义登录页（账号名 / 邮箱双模式，`Filament\Pages\Auth\Login` 扩展）
- 后台 Panel 配置（`AdminPanelProvider`，guard = `admin`）
- `Department`、`Menu`、`LoginLog` 模型与 Filament Resource CRUD
- `ActivityLogObserver` + `LogAdminLogin` Listener，自动记录操作日志与登录日志
- `AdminNavigationBuilder`、`DepartmentTree`、`ActivityLogger` 核心服务类
- 数据库迁移：`admin_users`、`departments`、`menus`、`login_logs`
- Spatie ActivityLog / MediaLibrary / Settings 三包集成
- Laravel Sanctum Bearer Token API 认证
- `filament-admin:clean-activity-logs` / `filament-admin:clean-login-logs` 清理命令

---

[Unreleased]: https://github.com/filamentboot/filamentboot/compare/v0.13.0...HEAD
[0.13.0]: https://github.com/filamentboot/filamentboot/compare/v0.5.3...v0.13.0
[0.5.3]: https://github.com/filamentboot/filamentboot/compare/v0.5.0...v0.5.3
[0.5.0]: https://github.com/filamentboot/filamentboot/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/filamentboot/filamentboot/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/filamentboot/filamentboot/releases/tag/v0.4.0
[0.3.0]: https://github.com/filamentboot/filamentboot/releases/tag/v0.3.0
[0.2.0]: https://github.com/filamentboot/filamentboot/releases/tag/v0.2.0
[0.1.0]: https://github.com/filamentboot/filamentboot/releases/tag/v0.1.0
