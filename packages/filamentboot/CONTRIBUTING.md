# 贡献指南

感谢你愿意为 filamentboot 贡献代码。这份指南说明报告问题、提交代码的流程与规范。

## 报告问题

在 [GitHub Issues](https://github.com/filamentboot/filamentboot/issues) 提交，请包含：

- Laravel、Filament、PHP 版本号
- 复现步骤，最好附带最小复现代码
- 期望的行为与实际观察到的行为

## 开发环境

```bash
composer install
composer test        # PHPUnit（Pest 语法）
composer phpstan      # 静态分析
composer pint:test    # 代码风格检查（不落地改动）
composer pint         # 代码风格自动修正
```

## 提交代码

1. Fork 仓库，基于 `main` 分支新建特性分支。
2. 代码遵循 PSR-12，4 空格缩进，LF 换行，文件末尾留空行；类文件 PascalCase、方法 camelCase；不使用拼音命名。
3. PHPDoc 注释使用中文（`/** ... */`），公共方法与非显而易见的实现细节需要说明。
4. 新功能与修复必须附带测试；提交前本地跑通 `composer test`、`composer phpstan`、`composer pint:test`。
5. 提交信息说明改动的原因，而不只是罗列改了什么文件。
6. 提交 Pull Request，在描述中关联对应的 Issue（如果有）。

## 行为准则

以尊重、建设性的态度参与讨论。不接受人身攻击、骚扰或歧视性言论。

## 许可协议

提交的代码将以本项目使用的 [MIT 协议](LICENSE) 发布。
