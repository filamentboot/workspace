# FilamentAdmin 文档

> 🌐 **在线体验**：https://demo.xitongapp.com  ·  演示账号 `demo@example.com` / `demo123`
> （演示环境每日凌晨 4:00 重置；高危操作已屏蔽）

FilamentAdmin 是一个 Laravel 13 + Filament 5 后台基础平台。

> **当前状态**：Skeleton 开发阶段，代码位于 `app/` 和 `src/`，尚未打包为独立 Library。

## 快速导航

### 入门
- [安装指南](installation.md) — 环境要求、数据库配置、创建管理员

### 使用指南
- [CRUD 开发规范](guide/crud-development.md) — Resource/Migration/Model/测试模板

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
| API 文档（Scribe）| 已完成 |

## 当前限制

- 尚未打包为 Composer Library（`composer require` 安装方式待实现）
- GraphQL、WebSocket、低代码表单暂不支持
