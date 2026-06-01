# FilamentAdmin

[![Latest Version](https://img.shields.io/badge/version-dev-blue.svg)](https://packagist.org/packages/filament-admin/filament-admin)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](https://github.com/filament-admin/filament-admin/actions)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-red.svg)](https://laravel.com)
[![Filament](https://img.shields.io/badge/Filament-5-purple.svg)](https://filamentphp.com)

基于 Laravel 13 + Filament 5 构建的开箱即用后台管理平台，提供完整的认证体系、权限管理和插件扩展能力。

## 特色

- **开箱即用**：克隆即可运行，内置登录、权限、日志等核心后台能力
- **组合最优**：精选社区成熟包（Spatie Permission、Filament Shield 等），避免重复造轮子
- **插件生态**：规划中的代码市场（store.xitongapp.com）将提供可组合的功能插件
- **诚实可信**：文档严格区分已完成、已铺垫和规划中，不虚标进度

## 技术栈

| 组件 | 版本 |
|------|------|
| PHP | 8.3 |
| Laravel | 13 |
| Filament | 5.x |
| MySQL | 8.0 |
| Redis | 7.x |
| spatie/laravel-permission | 6.x |
| bezhansalleh/filament-shield | 4.x |
| stephenjude/filament-two-factor-authentication | latest |
| spatie/laravel-activitylog | 4.x |

## 快速开始

> **即将支持**：`composer create-project filament-admin/filament-admin my-admin`（Packagist 注册后可用）

**当前手动安装步骤：**

```bash
# 1. 克隆项目
git clone https://github.com/filament-admin/filament-admin.git
cd filament-admin

# 2. 安装 PHP 依赖
composer install

# 3. 配置环境
cp .env.example .env
php artisan key:generate

# 4. 配置数据库（编辑 .env 中的 DB_* 配置）
php artisan migrate

# 5. 初始化数据
php artisan db:seed

# 6. 创建超级管理员
php artisan make:filament-user

# 7. 安装前端依赖并构建
npm install && npm run build
```

配置 Web 服务器（Nginx/Apache）将根目录指向 `public/` 目录，访问 `/admin` 即可进入后台。

## 已完成能力

### 已完成

| 功能 | 说明 |
|------|------|
| 后台登录 | 支持 username 或 email 双模式登录 |
| 管理员模型 | `AdminUser`，独立 `admin` guard + `admin_users` 表，含软删除 |
| 角色与权限 | Spatie Permission + Filament Shield，`admin` guard 隔离 |
| 双因素认证基础 | stephenjude/filament-two-factor-authentication 接入 |
| 登录日志 | 记录每次后台登录事件 |
| 软删除与回收站 | 管理员支持软删除 |

### 已铺垫（底层就绪，UI 待建）

| 功能 | 说明 |
|------|------|
| 操作日志 | spatie/laravel-activitylog 4.x 已集成，Resource UI 待建 |
| 登录日志 Resource | 数据已记录，后台列表页面待建 |
| 管理员 Resource | 模型和权限层就绪，CRUD 界面待建 |

### 规划中

- 菜单管理、部门管理、数据权限
- 系统配置
- 媒体库
- Sanctum / API 层
- 插件市场（独立 Composer 基础包，store.xitongapp.com）
- 演示站（demo.xitongapp.com）
- Packagist 发布

## 文档

- [项目概览](docs/guide/overview.md)
- [贡献指南](CONTRIBUTING.md)
- [安全政策](SECURITY.md)
- [行为准则](CODE_OF_CONDUCT.md)

## 贡献

欢迎提交 Issue 和 Pull Request！请先阅读 [贡献指南](CONTRIBUTING.md)。

## 许可证

本项目基于 [MIT 许可证](LICENSE) 开源。
