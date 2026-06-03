# 升级说明

## 从旧仓库结构升级到 `0.4.0`

`0.4.0` 的核心变化不是功能增加，而是发布对象收口。当前主包只保留后台基础能力，不再内置 `PluginPlatform`，也不再承载演示站仓库职责。

## 升级影响

### 1. `PluginPlatform` 不再随主包提供

如果历史环境仍引用过以下内容，需要清理：

- `composer.json` 中的 `filament-admin/plugin-platform`
- 指向 `packages/plugin-platform` 的本地 `path repository`
- 主包运行时代码中与 `PluginPlatform` 相关的资源发现、导航或页面接入

### 2. 文档入口改为主包视角

请改为使用以下文档：

- [README](../../README.md)
- [安装文档](../installation.md)
- [项目概览](overview.md)

### 3. 安装方式以 Composer 包为准

推荐安装方式：

```bash
composer require filament-admin/filament-admin
```

如果本地仍保留旧的联调依赖，升级前建议执行：

```bash
composer remove filament-admin/plugin-platform
composer update
```

## 升级检查清单

- `composer.json` 不再包含 `filament-admin/plugin-platform`
- `composer.json` 不再包含 `packages/plugin-platform` 的本地仓库定义
- 主包仓库内不再保留 `packages/plugin-platform/`
- 主包运行时代码不再引用 `FilamentAdmin\\PluginPlatform`
- 后台基础能力仍可正常加载

## 已知限制

- 插件市场能力不在 `0.4.0` 主包发布范围内
- 若后续发布独立插件市场包，需要按独立安装文档接入
