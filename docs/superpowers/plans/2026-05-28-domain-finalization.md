# 收尾与发布 实现计划

> 修订记录：2026-05-29 根据审查问题清单修复 8 项问题（新增 e2e 测试任务、覆盖率门槛恢复 spec 要求）。

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 开发阿里云 OSS 官方插件、完善 CI/CD、补全架构文档，完成端到端集成测试，完成 v1.0.0 正式发布。

**Architecture:** OSS 插件为独立 Composer 包，基于 spatie/laravel-package-tools 骨架，通过 ModuleFilamentPlugin 接口集成 Filament。CI/CD 使用 GitHub Actions，跑 PHP 8.3/8.4 矩阵测试，配 MySQL 8.0 与 Redis 7 服务容器。

**Tech Stack:** spatie/laravel-package-tools, GitHub Actions, Packagist

---

## Task 1: 阿里云 OSS 官方插件

> **说明：** OSS 插件应作为独立 Composer 包发布（建议仓库名：`your-org/filament-admin-oss`），以下为骨架文件路径参考，实际开发时在单独目录/仓库中进行。

- [ ] 初始化插件包骨架（使用 spatie/laravel-package-tools）：

```bash
# 在插件包目录中
composer require spatie/laravel-package-tools:^1.0
```

骨架结构：
```
filament-admin-oss/
├── composer.json
├── src/
│   ├── FilamentAdminOssServiceProvider.php
│   ├── OssPlugin.php
│   ├── Settings/
│   │   └── OssSettings.php
│   └── Filament/
│       └── Pages/
│           └── OssSettingsPage.php
├── config/
│   └── filament-admin-oss.php
└── tests/
```

- [ ] 编写 `composer.json`（插件包）：

```json
{
    "name": "your-org/filament-admin-oss",
    "description": "FilamentAdmin 阿里云 OSS 官方插件",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "spatie/laravel-package-tools": "^1.0",
        "iidestiny/laravel-filesystem-oss": "^4.0"
    },
    "extra": {
        "laravel": {
            "providers": [
                "YourOrg\\FilamentAdminOss\\FilamentAdminOssServiceProvider"
            ]
        },
        "filament-admin": {
            "name": "阿里云 OSS",
            "plugin_class": "YourOrg\\FilamentAdminOss\\OssPlugin",
            "settings_class": "YourOrg\\FilamentAdminOss\\Settings\\OssSettings"
        }
    },
    "autoload": {
        "psr-4": {
            "YourOrg\\FilamentAdminOss\\": "src/"
        }
    }
}
```

- [ ] 创建 OssSettings 类（使用 spatie/laravel-settings）`src/Settings/OssSettings.php`：

```php
<?php

namespace YourOrg\FilamentAdminOss\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 阿里云 OSS 配置 Settings
 */
class OssSettings extends Settings
{
    /** AccessKey ID */
    public string $access_key_id;

    /** AccessKey Secret */
    public string $access_key_secret;

    /** Bucket 名称 */
    public string $bucket;

    /** Endpoint（如 oss-cn-hangzhou.aliyuncs.com）*/
    public string $endpoint;

    /** 自定义域名（可选）*/
    public ?string $domain;

    /** 是否启用 CDN */
    public bool $cdn_enabled = false;

    public static function group(): string
    {
        return 'oss';
    }
}
```

- [ ] 创建 ServiceProvider `src/FilamentAdminOssServiceProvider.php`：

```php
<?php

namespace YourOrg\FilamentAdminOss;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * OSS 插件 ServiceProvider
 */
class FilamentAdminOssServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-admin-oss')
            ->hasConfigFile()
            ->hasMigration('create_oss_settings');
    }

    public function packageBooted(): void
    {
        // 动态配置 filesystem disk
        $settings = app(OssSettings::class);

        config([
            'filesystems.disks.oss' => [
                'driver'     => 'oss',
                'access_key' => $settings->access_key_id,
                'secret_key' => $settings->access_key_secret,
                'bucket'     => $settings->bucket,
                'endpoint'   => $settings->endpoint,
                'domain'     => $settings->domain,
                'cdnDomain'  => $settings->cdn_enabled ? $settings->domain : '',
                'ssl'        => true,
            ],
            // MediaLibrary disk 指向 oss
            'media-library.disk_name' => 'oss',
        ]);
    }
}
```

- [ ] 创建 OssPlugin `src/OssPlugin.php`（实现 `Filament\Contracts\Plugin`）：注册 OssSettingsPage

---

## Task 2: GitHub Actions CI

- [ ] 创建目录结构：

```bash
mkdir -p .github/workflows
```

- [ ] 创建 `.github/workflows/tests.yml`（含 MySQL 8.0 与 Redis 7 服务容器，通过 env 覆盖 phpunit.xml 中硬编码的连接配置；注意 CI 内 MySQL 用默认端口 3306，与本地 3380 不同）：

```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.3', '8.4']

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: filamentadmin_test
        ports:
          - '3306:3306'
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
      redis:
        image: redis:7
        ports:
          - '6379:6379'
        options: >-
          --health-cmd="redis-cli ping"
          --health-interval=10s

    env:
      DB_CONNECTION: mysql
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: filamentadmin_test
      DB_USERNAME: root
      DB_PASSWORD: secret
      REDIS_HOST: 127.0.0.1
      REDIS_PORT: 6379
      REDIS_PASSWORD: null

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, pdo_mysql, redis
          coverage: xdebug
      - run: composer install --no-interaction
      - run: cp .env.example .env && php artisan key:generate
      # 整体覆盖率门槛 60%（spec 要求）
      - name: 测试与整体覆盖率
        run: php artisan test --coverage --min=60
      # 核心目录单独 70% 门槛：通过 phpunit.xml <coverage> 配置分目录阈值，
      # 或用 --coverage-text 输出后人工/脚本校验 app/Policies、app/Services、
      # app/Http/Controllers/Api、app/Models 等核心目录覆盖率 ≥ 70%。
      - name: 核心目录覆盖率（≥70%）
        run: php artisan test --coverage-text | tee coverage.txt

  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction
      - name: 代码风格检查
        run: composer pint:test
      - name: 静态分析
        run: composer phpstan
      - run: composer audit
```

- [ ] 创建 `.github/workflows/release.yml`（可选，自动发布）：

```yaml
name: Release

on:
  push:
    tags:
      - 'v*.*.*'

jobs:
  create-release:
    runs-on: ubuntu-latest
    permissions:
      contents: write
    steps:
      - uses: actions/checkout@v4
      - uses: softprops/action-gh-release@v2
        with:
          generate_release_notes: true
```

- [ ] 验证 CI 配置语法：在 GitHub 仓库推送后检查 Actions 面板

---

## Task 3: 项目文档完善

- [ ] 创建 `docs/architecture/tech-stack.md`：

内容要求：
- 列出所有主要依赖包及版本
- 每个包的选型理由（为什么选这个而不是替代品）
- 包括：Laravel, Filament, spatie/* 系列, nwidart/laravel-modules, Pest 等

- [ ] 创建 `docs/architecture/customizations.md`：

内容要求：
- 列出对框架/包的所有定制改动
- 包括：覆盖的视图、扩展的基类、修改的配置等
- 格式：表格（文件路径 | 改动类型 | 改动说明）

- [ ] 创建 `docs/architecture/directory-structure.md`：

内容要求：
- 完整目录树（`tree` 命令输出 + 注释）
- 重点说明非标准目录：`app/Base/`, `app/Data/`, `Modules/`, `docs/`

- [ ] 创建 `docs/development/base-classes.md`：

内容要求：
- 列出所有基类（BaseResource, BaseListRecords 等）
- 每个基类的用途和使用示例

- [ ] 创建 `docs/development/conventions.md`（完整版）：

内容要求：
- 命名规范（文件、类、方法、变量）
- 代码风格（PHPDoc 格式、中文注释要求）
- 测试规范（Pest 语法、命名约定）
- Git 规范（分支命名、Commit Message 格式）
- 新增功能域的 Checklist

---

## Task 4: 功能域文档完整性检查

- [ ] 校验以下 8 个功能域文档全部存在于 `docs/features/`，缺一则 finalization 不通过：

```
docs/features/permission.md       # 权限
docs/features/settings.md         # 参数
docs/features/media.md            # 媒体
docs/features/api.md              # API
docs/features/menu.md             # 菜单
docs/features/operation-log.md    # 日志
docs/features/plugin.md           # 插件
docs/features/two-factor.md       # 双因素
```

- [ ] 校验脚本（可在 CI 中跑）：

```bash
for f in permission settings media api menu operation-log plugin two-factor; do
  test -f "docs/features/$f.md" || { echo "缺少 docs/features/$f.md"; exit 1; }
done
```

---

## Task 5: 端到端集成测试

- [ ] 创建 `tests/Feature/E2E/FullBusinessFlowTest.php`，使用 Pest 4 中文 `it()` 描述，覆盖完整业务链路：

```php
<?php

use App\Models\AdminUser;

// 1. 权限与子管理员流程
it('超管登录后可创建角色、分配权限、创建子管理员，子管理员能访问授权资源、被拒未授权资源', function () {
    // 超管登录 → 创建角色 → 分配权限 →
    // 创建子管理员并赋角色 → 子管理员登录 →
    // 访问授权 Resource 返回 200 → 访问未授权 Resource 返回 403
});

// 2. 媒体流程
it('管理员上传媒体后可关联到业务资源并在媒体列表中查看', function () {
    // 上传文件 → 关联到资源 model → 媒体列表分页可见
});

// 3. 系统参数流程
it('修改系统参数后立即在运行时生效', function () {
    // 更新 Settings → 读取 settings 实例 → 断言新值
});

// 4. C 端 API 流程
it('C 端用户通过 token 调用 API，未授权返回 401，超出限流返回 429，业务错误返回标准错误码', function () {
    // 注册/获取 token → 携带 token 调用受保护接口 200 →
    // 不带 token 401 → 触发限流 429 → 业务校验失败返回统一错误码
});

// 5. 菜单按权限过滤
it('菜单按当前用户权限过滤展示，无权限菜单项不出现', function () {
    // 不同角色登录 → 拉取菜单 → 断言菜单项集合
});

// 6. 操作日志
it('上述关键动作（登录、创建角色、分配权限、修改参数、上传媒体）全部被操作日志记录', function () {
    // 执行动作 → 查询 operation_logs 表断言记录存在
});
```

- [ ] 运行 e2e 测试套件：

```bash
php artisan test tests/Feature/E2E
```

预期：所有 e2e 场景通过。

---

## Task 6: 发布文件

- [ ] 创建/更新 `CHANGELOG.md`（Keep a Changelog 风格，列出 v1.0.0 全部功能）：

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-05-29

### Added
- 认证体系：admin guard、admin_users 表、用户名/邮箱登录、双因素认证
- 权限管理：基于 spatie/laravel-permission，角色/权限/资源 Policy
- 系统参数管理：基于 spatie/laravel-settings
- 媒体管理：基于 spatie/laravel-medialibrary
- 菜单管理：按权限动态过滤
- 操作日志：关键动作全程记录
- C 端 API：token 鉴权、限流、统一错误码
- 双轨制插件系统（Composer 包 + nwidart 业务模块）+ 远程插件市场
- 阿里云 OSS 官方插件（独立包）
- GitHub Actions CI/CD（PHP 8.3/8.4 矩阵 + MySQL 8 + Redis 7）
- 完整架构文档与 8 个功能域文档
- 端到端集成测试套件
```

- [ ] 创建 `UPGRADE.md`：

```markdown
# 升级指南

## 从无到 v1.0.0

这是项目的首个正式版本，无需升级步骤。

请按照 README.md 中的安装说明进行全新安装。
```

- [ ] 创建 `CONTRIBUTING.md`：

内容要求：
- 贡献流程（Fork → Branch → PR）
- 本地开发环境搭建步骤（MySQL 3380、Redis 6379 密码 123456、admin guard 等关键信息）
- 代码规范要求（`composer pint` + `composer phpstan`）
- 测试要求：整体覆盖率 ≥ 60%，核心目录（Policies/Services/Api Controllers/Models）≥ 70%
- PR 模板说明

- [ ] 创建 `SECURITY.md`：

```markdown
# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

如发现安全漏洞，请**不要**通过 GitHub Issues 公开报告。

请发送邮件至：security@your-domain.com

邮件内容应包含：
1. 漏洞描述
2. 复现步骤
3. 影响范围评估
4. 建议的修复方案（可选）

我们承诺在 72 小时内回复，并在 14 天内发布修复版本。
```

- [ ] 更新 `README.md`：

内容结构：
1. 项目标题 + **状态徽章**（版本、PHP 版本、Laravel 版本、CI 测试通过、覆盖率）
2. 项目简介（一句话）
3. 功能特性列表（8 个功能域）
4. 快速开始（核对关键信息：MySQL 端口 **3380**、Redis 密码 **123456**、admin guard、本地域名 `http://filamentadmin.local`）
5. 文档索引（链接到 docs/architecture/、docs/development/、docs/features/）
6. 贡献指南（链接到 CONTRIBUTING.md）
7. License

---

## Task 7: 最终验证

- [ ] 运行完整测试套件并确认整体覆盖率门槛（60%）：

```bash
php artisan test --coverage --min=60
```

预期：所有测试通过，整体覆盖率 ≥ 60%。

- [ ] 校验核心目录覆盖率 ≥ 70%（`app/Policies/`、`app/Services/`、`app/Http/Controllers/Api/`、`app/Models/`）：

```bash
php artisan test --coverage-text
```

人工或脚本检查上述目录的覆盖率行，均需 ≥ 70%。

- [ ] 运行静态分析（level 在 `phpstan.neon` 中已配 6，命令行不要传 `--level`）：

```bash
composer phpstan
```

预期：0 errors

- [ ] 运行代码风格检查（仅检查不修改）：

```bash
composer pint:test
```

预期：0 issues

- [ ] 运行安全审计：

```bash
composer audit
```

预期：无已知漏洞

- [ ] 校验功能域文档齐全（参考 Task 4 脚本）。

- [ ] 运行端到端集成测试（Task 5）。

---

## Task 8: Release 发布流程

- [ ] 确保所有 `feature/phase-X-*` 分支已合入主分支：

```bash
git checkout main
git pull --ff-only
git log --oneline -20
```

- [ ] 三件套全绿：

```bash
composer test
composer phpstan
composer pint:test
```

- [ ] 最终提交（如有未提交的文档/CI 改动）：

```bash
git add .
git commit -m "chore: 收尾工作，完善文档、CI 与发布配置"
git push origin main
```

- [ ] 打标签 v1.0.0 并推送：

```bash
git tag v1.0.0 -m "FilamentAdmin v1.0.0"
git push origin v1.0.0
```

- [ ] 创建 GitHub Release，正文复制 `CHANGELOG.md` v1.0.0 段：

```bash
gh release create v1.0.0 \
  --title "FilamentAdmin v1.0.0" \
  --notes-file <(awk '/^## \[1.0.0\]/,/^## \[/{if(/^## \[/ && !/1.0.0/)exit; print}' CHANGELOG.md) \
  --latest
```

- [ ] 发布到 Packagist：
  1. 登录 [packagist.org](https://packagist.org)
  2. 点击 "Submit"，填写 GitHub 仓库 URL
  3. 配置 GitHub Webhook（自动同步）：仓库 Settings → Webhooks → 添加 Packagist webhook
  4. 验证包已出现在 Packagist 搜索结果中

- [ ] 验证 Packagist 安装可用：

```bash
# 在全新目录测试安装
composer create-project --no-scripts your-org/filament-admin:^1.0 test-install
```
