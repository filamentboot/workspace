# 贡献指南

感谢你考虑为 FilamentAdmin 做出贡献！请在提交 Pull Request 前阅读本指南。

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
