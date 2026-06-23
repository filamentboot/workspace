# 贡献指南

感谢你考虑为 Filamentboot 做出贡献！请在提交 Pull Request 前阅读本指南。

## 环境要求

| 组件 | 要求 |
|------|------|
| PHP | 8.3+ |
| MySQL | 8.0（端口 3380，如使用默认 Docker 配置） |
| Redis | 7.x（端口 6379，密码 `123456`，DB 15） |
| Node.js | 20+ |
| Composer | 2.x |

> 注意：项目 MySQL 运行在 Docker 中，默认端口为 **3380**（非标准 3306），请确认 `.env` 中 `DB_PORT=3380`。

## Fork 与分支约定

1. Fork 本仓库到你的 GitHub 账号
2. 从 `main` 分支创建功能分支，命名规范：
   - 新功能：`feature/简短描述`（例如：`feature/media-library`）
   - Bug 修复：`fix/简短描述`（例如：`fix/login-redirect`）
   - 文档更新：`docs/简短描述`
   - 重构：`refactor/简短描述`
3. 完成后向 `main` 分支提交 Pull Request

## 测试要求

提交 PR 前，**必须**确保所有测试通过：

```bash
composer test
```

该命令会先清除配置缓存，再运行 Pest 测试套件。**所有测试必须 100% 通过**，不允许有失败或跳过的测试。

如果你新增了功能，**必须**同步补充对应的测试用例。测试文件放在 `tests/Feature/` 或 `tests/Unit/` 目录下，使用中文描述测试意图。

**测试数据库**：测试使用独立的 `filamentadmin_test` 数据库，请提前创建：

```bash
mysql -uroot -p -h127.0.0.1 -P3380 -e "CREATE DATABASE filamentadmin_test"
```

### CI 环境端口差异

> **CI 环境端口差异：** GitHub Actions services 中的 MySQL 使用默认端口 **3306**（非 3380）。
> `phpunit.xml.dist` / `phpunit.xml` 中已通过环境变量 `DB_PORT=3306` 覆盖。
> 本地开发无需修改，保持 `.env` 中 `DB_PORT=3380` 即可。

## 代码风格

项目使用 Laravel Pint 进行代码格式化，提交前请运行：

```bash
# 自动修复格式问题
composer pint

# 仅检查，不修改文件（用于确认结果）
composer pint:test
```

**不允许**提交含有格式错误的代码。

## 静态分析

项目使用 PHPStan（level 6）进行静态分析：

```bash
composer phpstan
```

提交前请确保 PHPStan 无报错。配置文件为 `phpstan.neon`，**不要**在命令行额外指定 `--level`（配置文件已声明）。

## Commit Message 规范

- 使用**简体中文**，简洁明了
- 第一行为主题，不超过 50 个字
- 如有必要，空一行后补充详细说明
- 参考示例：
  - `feat: 添加管理员软删除功能`
  - `fix: 修复登录重定向路径错误`
  - `test: 补充权限检查测试用例`
  - `docs: 更新快速开始安装步骤`
  - `refactor: 提取登录逻辑到独立服务`

## Pull Request 要求

- 描述清楚本次变更的范围和目的
- 关联相关 Issue（如有）
- 确认以下检查项全部通过：
  - `composer test` 全部通过
  - `composer phpstan` 无报错
  - `composer pint:test` 无格式错误
  - 新增代码有对应测试用例
- 如有 UI 变更，请附上截图

感谢你的贡献！

## SemVer 版本规范

自 v0.5.0 起，本项目严格遵循 [SemVer 2.0.0](https://semver.org/lang/zh-CN/)，所有正式发版 tag 必须为 `vX.Y.Z` 字面格式（如 `v0.5.0`、`v0.5.1`、`v1.0.0`），**不**附加中文/英文后缀。

- 历史中文后缀 tag（如 `v0.2.0-权限体系`、`v0.5.0-API规范`、`v1.0.0-phase1` 等共 9 个）保留为历史记录，**但不再用于新发版**，避免破坏既有外链。
- 预发版形式：`vX.Y.Z-rc.N`（如 `v0.5.0-rc.1`）或 `vX.Y.Z-alpha.N`，由 Phase 4 发版脚本 `scripts/release-package.sh` 接受为有效参数。
- 主版本号（X）变更代表 breaking change；次版本号（Y）代表向后兼容新功能；修订号（Z）代表向后兼容 bug 修复。
- 包仓库 `filamentboot/filamentboot` 与主仓库 tag 同步推送，由 Phase 4 `.github/workflows/release.yml` 自动完成。

## 工作目录约定

- 所有包代码必须写在 `packages/filamentboot/src/`（PSR-4 命名空间 `Filamentboot\\`）。
- 根目录 `/src/` 已在 v0.5 Phase 1 删除，并由 `.gitignore` 拦截避免再生（COMPLY-06）。
- 演示项目代码（PSR-4 `App\\`）写在 `app/`；与主包代码物理隔离。
- 工厂与种子：主包用 `Filamentboot\\Database\\Factories\\` / `Seeders\\`（在 `packages/filamentboot/database/`）；演示项目用 `Database\\Factories\\` / `Seeders\\`（在 `database/`）。
- 测试：主包测试在 `packages/filamentboot/tests/Unit/`（PSR-4 `Filamentboot\\Tests\\`）；演示项目测试在 `tests/`（PSR-4 `Tests\\`）。
