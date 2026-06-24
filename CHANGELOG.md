# 变更记录

本文件遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 规范，
版本号遵循 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

本文件记录 monorepo（演示项目 + 主包）的整体变更，从 v0.4.0 起。
主包 `laravelstack/filament-admin` 的对外变更详见 [packages/filament-admin/CHANGELOG.md](packages/filament-admin/CHANGELOG.md)。

## [Unreleased]

---

## [0.5.3] - 2026-06-24

### Security

- 发版前安全加固：模型批量赋值白名单（`$guarded=[]`→`$fillable`）、菜单批量操作补后端鉴权、登录失败达阈值自动锁定账号。详见 [packages/filamentboot/CHANGELOG.md](packages/filamentboot/CHANGELOG.md)

---

## [0.5.0] - 2026-06-11

### Added

- **发布自动化**：`.github/workflows/release.yml` — push tag 触发 git subtree split 推包仓库 + GitHub Release 自动创建（awk 版本号过滤 CHANGELOG，非位置驱动）
- **发版脚本三件套**：`scripts/release-package.sh`（本地发版 + Gitee 双推）、`scripts/verify-package-install.sh`（干净 Laravel 安装验证）、`scripts/release-rollback.sh`（幂等回滚）
- `AGENTS.md` 发版流程文档：Gitee 同步步骤、三脚本用途、所需 GitHub Secrets 说明
- `wiki/installation.md` 完整化：Prerequisites 表、Quick Start 5 步、默认账号小节、AdminPanelProvider 完整示例
- 根 `README.md` 改写为对外友好格式：核心能力清单、未来路线图

### Changed

- 根 CI（`.github/workflows/ci.yml`）：`APP_KEY` 改为 `${{ secrets.CI_APP_KEY }}` 引用，移除硬编码密钥（RELEASE-04）；补充 `composer audit` 安全扫描步骤（RELEASE-03，警告模式）
- 根 `CHANGELOG.md` 格式修正为 Keep a Changelog 1.1.0 标准，移除非标分组

### Fixed

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

[Unreleased]: https://github.com/john-captain/filament-admin/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/john-captain/filament-admin/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/john-captain/filament-admin/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/john-captain/filament-admin/releases/tag/v0.4.0
