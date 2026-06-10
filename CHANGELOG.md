# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 规范，
版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

本文件记录 monorepo（演示项目 + 主包）的整体变更，从 v0.4.0 起。
主包 `laravelstack/filament-admin` 的对外变更详见 [packages/filament-admin/CHANGELOG.md](packages/filament-admin/CHANGELOG.md)。

## [Unreleased]

### Added

### Changed

### Fixed

## [0.5.0] - 待发布

### Added

- `wiki/installation.md` 完整化：Prerequisites 表、Quick Start 5 步、默认账号小节、AdminPanelProvider 完整示例
- 根 `README.md` 改写为对外友好格式：后台首页截图、Star CTA、核心能力清单、未来路线图
- 根 `UPGRADING.md` 新建：v0.4 → v0.5 升级路径与 breaking changes 列表
- `CONTRIBUTING.md` 补充 CI 环境端口差异说明（本地 3380 / CI 3306）

### Changed

- 根 `CHANGELOG.md` 格式修正为 Keep a Changelog 1.1.0 标准，移除非标分组（Notes 段并入 Changed）

## [0.4.1] - 2026-06-03

### Changed

- 主包 Composer 坐标调整为 `laravelstack/filament-admin`
- 演示项目依赖、README、安装文档和发布文档同步切换到新坐标
- `filament-admin/filament-admin` 旧坐标因 Packagist vendor 已被占用，不再作为本项目正式发布坐标

## [0.4.0] - 2026-06-03

### Changed

- 主包发布对象调整为独立 Composer 包（`laravelstack/filament-admin`）
- 主包移除对 `PluginPlatform` 的内置依赖与运行时接入
- 对外文档改为主包视角，演示站与插件市场不再作为当前发布对象描述

### Removed

- 删除仓库内 `packages/plugin-platform/`
- 删除主包测试集中与 `PluginPlatform` 绑定的测试套件

[Unreleased]: https://github.com/john-captain/filament-admin/compare/v0.4.1...HEAD
[0.5.0]: https://github.com/john-captain/filament-admin/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/john-captain/filament-admin/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/john-captain/filament-admin/releases/tag/v0.4.0
