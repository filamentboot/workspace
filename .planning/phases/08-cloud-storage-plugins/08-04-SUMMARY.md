---
phase: 08-cloud-storage-plugins
plan: "04"
subsystem: monorepo-integration
tags: [cloud-storage, oss, cos, composer, settings, integration-test, plugin-scan]
dependency_graph:
  requires: ["08-01", "08-02"]
  provides: [monorepo-cloud-storage-integration, cloud-storage-plugin-scan-discovery]
  affects: [composer.json, config/settings.php, tests/Feature]
tech_stack:
  added:
    - "laravelstack/filament-admin-oss @dev（本地 path 仓库）"
    - "laravelstack/filament-admin-cos @dev（本地 path 仓库）"
    - "iidestiny/flysystem-oss 4.8.3（Flysystem OSS 适配器）"
    - "overtrue/laravel-filesystem-cos v4.0.0（COS Laravel 集成）"
    - "overtrue/flysystem-cos 5.2.0（Flysystem COS 适配器）"
    - "aliyuncs/oss-sdk-php v2.7.2（阿里云官方 SDK）"
    - "overtrue/qcloud-cos-client 2.2.1（腾讯云 COS 客户端）"
  patterns:
    - "Composer path repository symlink 模式（monorepo 本地包注册）"
    - "Spatie LaravelSettings settings[]/migrations_paths 多包注册模式"
    - "Pest it() 函数式测试，PluginManager::syncFromInstalled() 集成验证"
key_files:
  created:
    - path: "tests/Feature/CloudStoragePluginsIntegrationTest.php"
      purpose: "OSS/COS 集成测试：包发现、settings 注册、磁盘注入、无凭证不崩溃、plugin:scan 发现"
  modified:
    - path: "composer.json"
      purpose: "require 追加 laravelstack/filament-admin-oss 与 laravelstack/filament-admin-cos @dev"
    - path: "composer.lock"
      purpose: "锁定 OSS/COS 包及 7 个传递依赖版本"
    - path: "config/settings.php"
      purpose: "settings[] 追加 OssSettings/CosSettings，migrations_paths 追加两个包的 settings 目录"
    - path: "phpunit.xml"
      purpose: "补充 APP_KEY 环境变量（worktree 无 .env 阻断修复）"
decisions:
  - "Inline alias 临时修复 worktree 分支名版本约束：worktree 分支不满足 OSS/COS 包 require laravelstack/filament-admin ^0.5，用 dev-worktree-agent-* as 0.5.0 解决，安装完成后恢复为 @dev（正式写法在 main 分支有效）"
  - "phpunit.xml 补充 APP_KEY：worktree 无 .env 导致加密服务初始化失败，在 phpunit.xml 中硬编码测试用 key 是标准做法"
  - "使用 it() Pest 函数式风格而非 PHPUnit 类，与项目现有 Feature 测试一致"
metrics:
  duration: "约 30 分钟"
  completed: "2026-06-12"
  tasks_completed: 3
  tasks_total: 3
  files_created: 1
  files_modified: 4
---

# Phase 08 Plan 04: Monorepo 集成 Summary

**一句话总结：** 通过 composer path repository 将 OSS/COS 两个云存储插件接入演示项目，注册 Settings 类与迁移路径，5 个集成测试全绿验证端到端可用性。

## 任务执行结果

| 任务 | 名称 | 提交 | 状态 |
|------|------|------|------|
| Task 1 | 根 composer.json 注册本地包路径并安装依赖 | 1767fde | 完成 |
| Task 2 | config/settings.php 注册 Settings 类与迁移路径 + 集成测试 | 5de732e | 完成 |
| Task 3 | plugin:scan 发现两个云存储插件测试 | 3dd2782 | 完成 |

## 验证结果

- `composer show laravelstack/filament-admin-oss` 退出码 0
- `composer show laravelstack/filament-admin-cos` 退出码 0
- `php artisan test --filter=CloudStoragePluginsIntegrationTest`：5/5 通过
- `php artisan migrate --force`：Nothing to migrate（幂等）
- `config/settings.php` settings[] 含 OssSettings::class 与 CosSettings::class
- `config/settings.php` migrations_paths 含 OSS/COS 两个包的 database/settings 目录
- plugin:scan syncFromInstalled() 发现 filament-admin-oss 与 filament-admin-cos 并写入 plugins 表

## 集成测试覆盖

```
tests/Feature/CloudStoragePluginsIntegrationTest.php
├── OSS 与 COS 包的 ServiceProvider 和 Plugin 类存在
├── config/settings.php settings 数组已注册 OssSettings 与 CosSettings
├── OSS 凭证完整时 oss 磁盘配置被注入
├── 无凭证时应用正常启动不抛出异常
└── plugin:scan 发现 OSS 与 COS 两个云存储插件
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Composer path repo 版本约束冲突**
- **发现于：** Task 1
- **问题：** worktree 分支名 `dev-worktree-agent-a14ee95b4045fec81` 不满足 OSS/COS 包的传递依赖约束 `laravelstack/filament-admin ^0.5`（branch-alias 仅映射 dev-main）
- **修复：** 安装时临时使用 inline alias `dev-worktree-agent-a14ee95b4045fec81 as 0.5.0`，安装完成后恢复为 `@dev`（main 分支的 branch-alias 确保正式环境正常解析）
- **影响文件：** composer.json（最终提交为 @dev 形式）
- **提交：** 1767fde

**2. [Rule 3 - 阻断修复] phpunit.xml 缺失 APP_KEY**
- **发现于：** Task 2 测试阶段
- **问题：** worktree 目录无 `.env` 文件，Laravel 加密服务找不到 APP_KEY，所有集成测试失败
- **修复：** phpunit.xml 的 `<php>` 段追加 `<env name="APP_KEY" value="..."/>`
- **影响文件：** phpunit.xml
- **提交：** 5de732e

## Known Stubs

无。所有集成点均为真实实现，无 placeholder 或硬编码空值。

## Self-Check: PASSED

| 检查项 | 状态 |
|--------|------|
| tests/Feature/CloudStoragePluginsIntegrationTest.php 存在 | FOUND |
| config/settings.php 存在 | FOUND |
| composer.json 存在 | FOUND |
| 08-04-SUMMARY.md 存在 | FOUND |
| commit 1767fde 存在（Task 1） | FOUND |
| commit 5de732e 存在（Task 2） | FOUND |
| commit 3dd2782 存在（Task 3） | FOUND |
