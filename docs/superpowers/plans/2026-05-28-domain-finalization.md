# 收尾与发布 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 开发阿里云 OSS 官方插件、完善 CI/CD、补全架构文档，完成 v1.0.0 正式发布。

**Architecture:** OSS 插件为独立 Composer 包，基于 spatie/laravel-package-tools 骨架，通过 ModuleFilamentPlugin 接口集成 Filament。CI/CD 使用 GitHub Actions，跑 PHP 8.3/8.4 矩阵测试。

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

- [ ] 创建 `.github/workflows/tests.yml`：

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

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, pdo_mysql
          coverage: xdebug
      - run: composer install --no-interaction
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan test --coverage --min=50

  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction
      - run: ./vendor/bin/pint --test
      - run: ./vendor/bin/phpstan analyse --level=6
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

## Task 4: 发布文件

- [ ] 创建 `CHANGELOG.md`（Keep a Changelog 格式）：

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-05-28

### Added
- 完整的权限管理体系（基于 spatie/laravel-permission）
- 多租户支持（基于 stancl/tenancy）
- 系统设置管理（基于 spatie/laravel-settings）
- 双轨制插件系统（Composer 包 + nwidart 业务模块）
- 远程插件市场浏览
- 阿里云 OSS 官方插件
- GitHub Actions CI/CD 流水线
- 完整的架构文档
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
- 本地开发环境搭建步骤
- 代码规范要求（pint + phpstan）
- 测试要求（≥50% 覆盖率）
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

- [ ] 重写 `README.md`：

内容结构：
1. 项目标题 + 徽章（CI 状态、PHP 版本、License）
2. 项目简介（一句话）
3. 功能特性列表
4. 快速安装（Requirements + 安装步骤）
5. 文档索引（链接到 docs/ 下各文档）
6. 贡献指南（链接到 CONTRIBUTING.md）
7. License

---

## Task 5: 最终验证与发布

- [ ] 运行完整测试套件并确认覆盖率：

```bash
php artisan test --coverage --min=50
```

预期：所有测试通过，覆盖率 ≥ 50%

- [ ] 运行静态分析：

```bash
./vendor/bin/phpstan analyse --level=6
```

预期：0 errors

- [ ] 运行代码风格检查：

```bash
./vendor/bin/pint --test
```

预期：0 issues

- [ ] 运行安全审计：

```bash
composer audit
```

预期：无已知漏洞

- [ ] 最终提交：

```bash
git add .
git commit -m "chore: 收尾工作，完善文档和发布配置"
```

- [ ] 创建 GitHub Release v1.0.0：

```bash
# 打标签
git tag v1.0.0
git push origin main --tags

# 使用 GitHub CLI 创建 Release
gh release create v1.0.0 \
  --title "v1.0.0 - 首个正式版本" \
  --notes-file CHANGELOG.md \
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
