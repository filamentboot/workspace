# 发布说明

## v0.5.0 — 2026-06-11

这是 FilamentAdmin v0.5 里程碑正式发布版本，核心使命：让 `composer require laravelstack/filament-admin` 真正可用——资源发布完整、主包 CI 全绿、功能骨架完整可扩展。

### 安装

```bash
composer require laravelstack/filament-admin
```

演示站：[https://demo.xitongapp.com](https://demo.xitongapp.com)

---

### Added（新增）

- **包发布合规（5 个 vendor:publish tag）**：`filament-admin-config` / `filament-admin-migrations` / `filament-admin-views` / `filament-admin-lang` / `filament-admin-stubs` 全部注册到 `FilamentAdminServiceProvider`，首次安装后 `php artisan vendor:publish` 可正常使用
- **filament-admin:publish 真实实现**：支持 `--model=AdminUser` / `--resource=AdminUserResource` 单个发布，以及 `--all` 发布全套扩展（Model + Resource + Migration + FeatureTest 四件套），含 `--force` 覆盖、`--only / --except` 过滤、`--path` 自定义输出路径
- **make:filament-admin-resource 命令**：`php artisan make:filament-admin-resource {name}` 在用户项目生成 Resource + 三个 Pages（List/Create/Edit），委托 `StubGenerator` 服务统一渲染模板
- **Impersonation（用户模拟登录）**：集成 `stechstudio/filament-impersonate`，管理员列表一键切换身份，顶栏显示中文"结束模拟"横幅，`ImpersonationListener` 自动写入操作日志
- **Scramble API 文档**：集成 `dedoc/scramble`，`/docs/api` 自动生成 OpenAPI 3.0 文档界面（Stoplight Elements），生产环境通过 `RestrictedDocsAccess` 中间件禁止公开访问
- **插件市场基础设施**：`plugins` 表 + `Plugin` Eloquent 模型（scopeEnabled / casts / SoftDeletes）+ `PluginFactory`；`PluginManager` 服务类统一生命周期调度；`MarketplaceService` 从官方市场拉取插件元数据；官方市场 JSON API（`GET /plugin-market/index.json`）
- **官网占位页**：`GET /` 返回最小 landing 页（项目定位 + 功能清单 + 安装指引 + 演示站链接）
- **GeneralSettings 扩展字段**：新增 `logo_url`（Logo 图片 URL）与 `contact_email`（联系邮箱）属性，含 SettingsMigration 迁移及设置页表单字段
- **Exporter 导出授权**：管理员列表 / 部门列表 / 登录日志列表三个 ExportAction 补充 `->authorize('export_admin_user'|'export_department'|'export_login_log')` 权限点授权 + `->after()` ActivityLogger 导出审计
- **包 CI（GitHub Actions）**：PHP 8.3 / 8.4 矩阵，含 PHPUnit、PHPStan、Pint 三个作业，`composer audit` 安全扫描（警告模式，`continue-on-error: true`）
- **包元数据合规**：MIT License，CONTRIBUTING / SECURITY / CODE_OF_CONDUCT 文档完整，Packagist 坐标 `laravelstack/filament-admin`

### Changed（变更）

- `StubGenerator` 抽取为独立服务（D-28），`PublishCommand` 与 `make:filament-admin-*` 命令均委托调用，消除重复渲染逻辑
- `filament-admin:publish --path` 路径输出限制在 `app/` 目录内（安全修复 WR-08）
- 演示站重置命令 `demo:reset` 支持 `migrate:fresh --seed`，护栏验证通过后自动播种演示数据
- 发布自动化脚本更新：Gitee / GitHub 双线同步，CI 密钥从环境变量注入

### Fixed（修复）

- `PublishCommand` FeatureTest 命名空间来源统一（WR-06/WR-07）
- `publishResource` 传递给 `renderStub` 的无效键删除（WR-04）
- `filament-impersonate` 翻译路径修复，注册时序调整确保 zh_CN 语言包正确加载
- `demo:reset` 捕获 `migrate:fresh` 退出码，失败时返回 FAILURE（CR-02）
- 演示站部署脚本改用 `npm ci` 安装全依赖以支持前端构建（IN-02）

---

## v0.4.1 — 2026-06-03

- Composer 坐标调整为 `laravelstack/filament-admin`（原 `filament-admin/filament-admin`）
- 同步修正安装文档、测试断言和发布口径

---

## v0.4.0 — 2026-06-03

- 独立包目录骨架初始化（`packages/filament-admin/`）
- `FilamentAdminServiceProvider` 注册框架（publishes 空壳，v0.5 补全实现）
- `PublishCommand` 命令框架
- 包级测试框架（Pest 4.x）与代码质量配置（PHPStan / Pint）
