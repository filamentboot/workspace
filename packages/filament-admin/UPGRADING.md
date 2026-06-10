# 升级指南

本文档面向 `laravelstack/filament-admin` 包的消费者，列出各版本间的不兼容变更（Breaking Changes）及升级操作步骤。

---

## v0.4 → v0.5 升级指南

### 摘要

v0.4.x 是独立包的骨架初始化版本，大量功能为空壳实现。v0.5 是第一个真正可用的功能完整版本。升级时请按照本指南逐项操作，否则可能导致功能不可用。

---

### Breaking Change 1：vendor:publish 新增 5 个 tag

**影响：** 所有从 v0.4.x 升级的用户。

**背景：** v0.4.x 的 `FilamentAdminServiceProvider` 未注册任何 `publishes()`（空壳）。v0.5 新增了全部 5 个 vendor:publish tag，资源不会自动覆盖已有文件。

**操作：升级后，请手动执行以下 5 条命令：**

```bash
# 发布配置文件（覆盖：加 --force）
php artisan vendor:publish --tag=filament-admin-config

# 发布数据库迁移文件
php artisan vendor:publish --tag=filament-admin-migrations

# 发布视图文件
php artisan vendor:publish --tag=filament-admin-views

# 发布多语言文件（含 en / zh_CN 两个语言包）
php artisan vendor:publish --tag=filament-admin-lang

# 发布 Stub 文件（用于 filament-admin:publish 命令的模板）
php artisan vendor:publish --tag=filament-admin-stubs
```

**各 tag 发布目标路径：**

| Tag | 发布目标（用户项目） |
|-----|------------------|
| `filament-admin-config` | `config/filament-admin.php` |
| `filament-admin-migrations` | `database/migrations/`（Laravel 追加时间戳前缀） |
| `filament-admin-views` | `resources/views/vendor/filament-admin/` |
| `filament-admin-lang` | `lang/vendor/filament-admin/en/` 与 `zh_CN/` |
| `filament-admin-stubs` | `stubs/vendor/filament-admin/` |

> **注意：** 如需覆盖已存在的配置文件，请在命令后追加 `--force` 参数。

---

### Breaking Change 2：filament-admin:publish 命令真实可用

**影响：** 使用 `filament-admin:publish` 命令的用户。

**背景：** v0.4.x 的 `filament-admin:publish` 是空壳命令，执行后无任何输出且不生成任何文件。v0.5 该命令已真实实现，支持完整的 8 个选项。

**v0.5 命令签名（完整 8 个选项）：**

| 选项 | 类型 | 说明 |
|------|------|------|
| `--model=` | 值选项 | 发布指定 Model stub，如 `--model=Product` |
| `--resource=` | 值选项 | 发布指定 Resource stub（同时生成 3 个 Page 文件） |
| `--all` | 开关选项 | 单独使用时发布全套内置资源；配合 `--model`/`--resource` 时补齐四件套 |
| `--path=` | 值选项 | Resource 输出根路径（默认 `app/Filament/Resources/`，**必须位于 app/ 之内**） |
| `--with-models` | 开关选项 | 配合 `--path`，在对应子目录下生成独立 Model 副本 |
| `--force` | 开关选项 | 覆盖已存在文件（默认跳过不报错） |
| `--only=` | 值选项 | 配合 `--all`，只生成逗号分隔的子集 |
| `--except=` | 值选项 | 配合 `--all`，排除逗号分隔的名称 |

**典型使用示例：**

```bash
# 发布单个 Model stub
php artisan filament-admin:publish --model=Product

# 发布单个 Resource stub（同时生成 3 个 Page 文件）
php artisan filament-admin:publish --resource=Product

# 为指定名称补齐四件套（Model + Resource + Migration + FeatureTest）
php artisan filament-admin:publish --model=Product --all

# 发布全套内置资源（AdminUser / Department / Menu / LoginLog）
php artisan filament-admin:publish --all

# 指定输出路径（必须在 app/ 内）
php artisan filament-admin:publish --resource=Product --path=app/Filament/Reseller

# 覆盖已存在文件
php artisan filament-admin:publish --model=Product --force

# 批量发布并排除某些资源
php artisan filament-admin:publish --all --except=Menu,LoginLog
```

> **安全限制：** `--path` 参数不接受 `..` 路径上溯或 `/` 开头的绝对路径，必须位于 `app/` 之内。

**Stub 查找顺序：** 优先读取 `stubs/vendor/filament-admin/{Name}.stub`（用户自定义）；找不到时 fallback 到包内默认 `stubs/{Name}.stub`。

---

### Breaking Change 3：新增配置文件 filament-admin.php

**影响：** 所有升级用户（执行过 `vendor:publish --tag=filament-admin-config` 后生效）。

**背景：** v0.5 新增 `config/filament-admin.php` 配置文件，暴露两个关键配置项。

**配置项说明：**

| 配置键 | 环境变量 | 默认值 | 说明 |
|--------|---------|--------|------|
| `super_admin_role` | `SUPER_ADMIN_ROLE` | `super_admin` | 超级管理员角色名称，须与 Spatie Permission 角色名一致 |
| `log_retention_days` | `LOG_RETENTION_DAYS` | `90` | 操作日志保留天数，超出后由清理命令删除 |

**在 `.env` 中可覆盖默认值：**

```dotenv
# FilamentAdmin 包级配置
SUPER_ADMIN_ROLE=super_admin
LOG_RETENTION_DAYS=90
```

**操作：** 执行 `vendor:publish --tag=filament-admin-config` 后，检查生成的 `config/filament-admin.php`，按需调整配置。

---

### Breaking Change 4：composer 约束建议

请将 `composer.json` 中的版本约束更新为：

```json
{
    "require": {
        "laravelstack/filament-admin": "^0.5"
    }
}
```

---

## 默认账号安全提示

如果在 v0.4.x 中已运行过 `SuperAdminSeeder`，默认账号 `admin@example.com / password` 仍然存在。升级到 v0.5 后：

1. 登录后台（`/admin`），前往「个人资料」页立即修改密码。
2. 检查生产环境是否使用了默认密码，若是请立即更换。

---

## 完整升级清单

- [ ] 执行 `composer update laravelstack/filament-admin`
- [ ] 执行 5 条 `vendor:publish` 命令（Breaking Change 1）
- [ ] 执行 `php artisan migrate`（迁移文件有变动时）
- [ ] 检查并配置 `config/filament-admin.php`（Breaking Change 3）
- [ ] 在 `.env` 中追加 `SUPER_ADMIN_ROLE` / `LOG_RETENTION_DAYS`（如需自定义）
- [ ] 更新 `composer.json` 约束为 `^0.5`（Breaking Change 4）
- [ ] 修改生产环境默认密码（安全提示）

---

更多信息请参阅：

- [详细安装文档](https://github.com/john-captain/filament-admin/blob/main/wiki/installation.md)
- [变更记录](CHANGELOG.md)
