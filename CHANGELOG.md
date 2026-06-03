# 变更记录

本文档记录 `filament-admin/filament-admin` 主包的对外发布变更。

格式参考 Keep a Changelog，版本号遵循 Semantic Versioning。

## [0.4.0] - 2026-06-03

### Changed

- 主包发布对象调整为 `filament-admin/filament-admin`
- 主包移除对 `PluginPlatform` 的内置依赖与运行时接入
- 对外文档改为主包视角，演示站与插件市场不再作为当前发布对象描述

### Removed

- 删除仓库内 `packages/plugin-platform/`
- 删除主包测试集中与 `PluginPlatform` 绑定的测试套件

### Notes

- 当前版本以 GitHub 仓库发布为准
- Packagist 安装验证属于本次发布验收的一部分
