# FilamentAdmin

[![Latest Version](https://img.shields.io/badge/version-v0.4.0-blue.svg)](https://packagist.org/packages/filament-admin/filament-admin)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-red.svg)](https://laravel.com)
[![Filament](https://img.shields.io/badge/Filament-5-purple.svg)](https://filamentphp.com)

FilamentAdmin 是一个面向 Laravel 13 与 Filament 5 的后台基础包，提供管理员认证、角色权限、菜单、日志、个人资料页和后台基础导航能力。

## 当前定位

- **发布对象**：`filament-admin/filament-admin`
- **安装方式**：`composer require filament-admin/filament-admin`
- **当前范围**：后台基础管理主包
- **不包含**：演示站仓库、`PluginPlatform` 内置集成

## 安装

```bash
composer require filament-admin/filament-admin
```

安装后请参考 [安装文档](wiki/installation.md) 完成：

- 环境准备
- Plugin / ServiceProvider 注册
- 数据库迁移与初始化
- 管理员登录入口配置

## 当前状态

### 已完成

- 后台登录（支持 username 或 email）
- 管理员模型与独立 `admin` guard
- 角色与权限体系（Spatie Permission + Filament Shield）
- 登录日志
- 操作日志基础集成
- 双因素认证基础接入
- 部门、菜单、个人资料页与后台基础导航

### 当前发布目标

- 主包独立发布
- GitHub Release
- Packagist 安装验证

### 当前限制

- 当前发布对象不包含 `PluginPlatform`
- 演示站为独立仓库，不属于当前主包
- 部分系统配置、媒体库、API 规范仍处于后续版本范围

## 文档

- [安装文档](wiki/installation.md)
- [项目概览](wiki/guide/overview.md)
- [升级说明](wiki/guide/upgrading.md)
- [变更记录](CHANGELOG.md)
- [部署说明](wiki/guide/deployment.md)

## 许可证

本项目基于 [MIT 许可证](LICENSE) 开源。
