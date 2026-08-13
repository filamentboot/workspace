# Filamentboot 文档

> 🌐 **在线体验**：https://demo.xitongapp.com  ·  演示账号 `demo@example.com` / `demo123`
> （演示环境每日凌晨 4:00 重置；高危操作已屏蔽）

Filamentboot 是一个基于 Laravel 13 + Filament 5 的企业级后台基础包，通过 Packagist 以
`composer require filamentboot/filamentboot` 分发，`php artisan filamentboot:install`
一条命令即可起站。

## 快速导航

### 入门
- [安装指南](installation.md) — 环境要求、安装步骤、初始化

### 使用指南
- [CRUD 开发规范](guide/crud-development.md) — Resource/Migration/Model/测试模板
- [插件开发指南](plugin-development.md) — 开发与提交兼容插件
- [插件安装与管理](plugin-usage.md) — 后台一键安装、启用/禁用、常见问题排查

### 技术参考
- [API 认证](reference/api-auth.md) — Sanctum Bearer Token 认证方式
- [API 响应格式](reference/api-response.md) — 统一 JSON 响应结构
- [错误码清单](reference/error-codes.md) — 所有 API 错误码定义

## 已完成能力

| 能力 | 状态 |
|------|------|
| 管理员认证（account/email + 2FA） | 已完成 |
| 角色权限（RBAC，Shield）| 已完成 |
| 部门管理 | 已完成 |
| 菜单管理 | 已完成 |
| 登录日志 | 已完成 |
| 操作日志（Activitylog）| 已完成 |
| 系统配置（Settings）| 已完成 |
| 媒体库（Media Library）| 已完成 |
| 基础导出（Export）| 已完成 |
| API 认证（Sanctum）| 已完成 |
| API 统一响应格式 | 已完成 |
| 插件市场（一键安装/启用/禁用） | 已完成 |

官方一方插件（存储驱动、编辑器、官网内容管理插件等）见
[README「插件生态」](../README.md#插件生态)。

## 当前限制

- GraphQL、WebSocket、低代码表单暂不支持
