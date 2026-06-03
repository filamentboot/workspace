# 变更记录

本文档记录 `laravelstack/filament-admin` 主包的对外发布变更。

格式参考 Keep a Changelog，版本号遵循 Semantic Versioning。

## [0.4.1] - 2026-06-03

### Changed

- 主包 Composer 坐标调整为 `laravelstack/filament-admin`
- 演示项目依赖、README、安装文档和发布文档同步切换到新坐标

### Notes

- `filament-admin/filament-admin` 因 Packagist vendor 已被占用，不再作为本项目正式发布坐标

## [0.4.0] - 2026-06-03

### Changed

- 主包发布对象调整为独立 Composer 包
- 主包移除对 `PluginPlatform` 的内置依赖与运行时接入
- 对外文档改为主包视角，演示站与插件市场不再作为当前发布对象描述

### Removed

- 删除仓库内 `packages/plugin-platform/`
- 删除主包测试集中与 `PluginPlatform` 绑定的测试套件

### Notes

- 当前版本以 GitHub 仓库发布为准
- Packagist 安装验证属于本次发布验收的一部分
