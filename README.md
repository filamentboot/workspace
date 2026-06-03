# FilamentAdmin 演示项目

当前仓库根目录是 **FilamentAdmin 的 Laravel 演示项目**，用于本地运行、联调和发包前验收。

## 当前结构

- 演示项目根目录：当前仓库
- 主包目录：`packages/filament-admin`
- GitHub 包仓库：`https://github.com/john-captain/filament-admin`
- Gitee 包仓库：`https://gitee.com/johncaptain/filament-admin`

## 根仓库职责

- 启动 Laravel 演示环境
- 作为宿主项目联调 `filament-admin/filament-admin`
- 承担人工验收和发布演练

## 主包职责

真正对外发布的 Composer 包不在根目录，而在：

```text
packages/filament-admin
```

演示项目通过本地 `path repository` 依赖该包。

## 演示项目开发

```bash
composer install
```

启动环境后访问：

```text
http://filamentadmin.local
```

## 对外安装主包

```bash
composer require filament-admin/filament-admin
```

更多说明：

- [安装文档](wiki/installation.md)
- [项目概览](wiki/guide/overview.md)
- [包发布设计](docs/superpowers/specs/2026-06-03-package-release-from-demo-repo-design.md)
