# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 规范，
版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## [Unreleased]

### Added

- `filament-admin:publish` 命令真实实现（8 个选项：`--model`/`--resource`/`--all`/`--path`/`--with-models`/`--force`/`--only`/`--except`）
- vendor:publish 5 个 tag 完整注册：`filament-admin-config`/`migrations`/`views`/`lang`/`stubs`
- 包 CI（GitHub Actions）覆盖 PHP 8.3 / 8.4，含 PHPUnit、PHPStan、Pint 三个作业
- 包元数据合规（`laravelstack/filament-admin` 坐标、MIT License、badges）

### Changed

- `filament-admin:publish --path` 限制输出路径必须位于 `app/` 之内（安全修复 WR-08）

### Fixed

- PublishCommand `FeatureTest` 命名空间来源统一修复（WR-06/WR-07）
- `publishResource` 传递给 `renderStub` 的无效键删除（WR-04）

---

## [0.4.1] - 2026-06-03

### Changed

- 主包 Composer 坐标调整为 `laravelstack/filament-admin`（原 `filament-admin/filament-admin`）
- 同步修正安装文档、测试断言和发布口径

---

## [0.4.0] - 2026-06-03

### Added

- 独立包目录骨架初始化（`packages/filament-admin/`）
- `FilamentAdminServiceProvider` 注册框架（publishes 空壳，v0.5 补全实现）
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

[Unreleased]: https://github.com/john-captain/filament-admin/compare/v0.4.1...HEAD
[0.4.1]: https://github.com/john-captain/filament-admin/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/john-captain/filament-admin/releases/tag/v0.4.0
[0.3.0]: https://github.com/john-captain/filament-admin/releases/tag/v0.3.0
[0.2.0]: https://github.com/john-captain/filament-admin/releases/tag/v0.2.0
[0.1.0]: https://github.com/john-captain/filament-admin/releases/tag/v0.1.0
