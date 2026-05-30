# 一期后台基础管理收口计划

> 状态：待审核  
> 前置计划：`docs/superpowers/plans/2026-05-29-phase-1-admin-foundation.md`  
> 范围：只处理一期后台基础管理发布前收口，不新增二期功能。

## 目标

一期主体功能已经具备可用后台入口，收口阶段要把“能用”推进到“可发布”。本计划只覆盖四件事：

- 关键后台动作写入操作日志。
- 管理员密码重置权限单独收严。
- 管理员登录日志筛选体验补齐。
- 文档同步和发布前验收。

## 执行原则

- 继续按功能闭环推进，每一步完成后跑对应测试。
- 不升级 PHP、Laravel、Filament 或已安装依赖。
- 不自写 Shield `RoleResource`，只在项目侧做可维护接线。
- 操作日志记录关键后台治理动作，不做合规级防篡改审计。
- 文档只写真实已落地状态，不把后续规划写成已完成。

## Step 1：关键后台动作接入操作日志

### 目标

让管理员、菜单、部门、数据权限等一期核心后台对象的关键变更写入 `activity_log`，做到后台关键操作可追踪。

### 涉及对象

- 管理员：创建、编辑、禁用、删除、恢复、角色变更。
- 管理员密码：重置密码单独记录。
- 菜单规则：创建、编辑、排序、启停、删除、恢复。
- 部门组织：创建、编辑、排序、启停、删除、恢复。
- 数据权限：创建、编辑角色数据范围。
- 角色权限：优先记录 Shield 角色本体变更；权限勾选变更需要先确认 Shield 保存钩子，避免改 vendor。

### 预期改动

- 复用 `App\Services\ActivityLogger`。
- 在对应 Filament Resource Page 生命周期中记录操作日志。
- 对排序动作补测试；如果 Filament 5 排序钩子不稳定，先记录资源更新和显式排序入口，保留拖拽排序记录的实现说明。
- 操作日志 properties 中保留 `before`、`after`、`action`、`ip`、`user_agent`。

### 重点文件

- `app/Services/ActivityLogger.php`
- `app/Filament/Resources/AdminUsers/Pages/*.php`
- `app/Filament/Resources/Menus/Pages/*.php`
- `app/Filament/Resources/Departments/Pages/*.php`
- `app/Filament/Resources/RoleDataScopes/Pages/*.php`
- 可能新增 `app/Observers/RoleObserver.php` 或项目侧事件监听器
- `tests/Feature/AdminFoundation/ActivityLogResourceTest.php`

### 验收标准

- 创建、编辑、删除、恢复管理员会产生操作日志。
- 修改管理员角色会产生操作日志。
- 修改菜单、部门、数据权限配置会产生操作日志。
- 操作日志记录操作人、对象、动作和变更前后数据。
- 相关测试通过，且 `composer test` 不回退。

## Step 2：管理员密码重置权限收严

### 目标

把“编辑管理员资料”和“重置管理员密码”拆开授权。有 `update_admin_user` 不等于能改别人密码，必须额外拥有 `reset_password_admin_user`。

### 当前问题

`AdminUserResource` 里已经有 `password` 字段，但它还没有严格独立到 `reset_password_admin_user` 权限。发布前需要补齐，否则权限颗粒度和设计文档不一致。

### 预期改动

- `password` 字段仅在创建管理员或拥有 `reset_password_admin_user` 时可见/可提交。
- 编辑管理员时，如果没有重置密码权限，即使提交了 `password` 字段也不能生效。
- 重置密码成功后写入操作日志。
- 保留现有创建管理员密码必填行为。

### 重点文件

- `app/Filament/Resources/AdminUsers/AdminUserResource.php`
- `app/Filament/Resources/AdminUsers/Pages/EditAdminUser.php`
- `app/Policies/AdminUserPolicy.php`
- `tests/Feature/AdminFoundation/AdminUserResourceTest.php`

### 验收标准

- 拥有 `update_admin_user` 但没有 `reset_password_admin_user` 的管理员不能修改目标管理员密码。
- 拥有 `reset_password_admin_user` 的管理员可以修改目标管理员密码。
- 修改密码会写入操作日志。
- 创建管理员时仍然要求填写密码。

## Step 3：管理员登录日志筛选体验补齐

### 目标

让管理员登录日志从“能看列表”补齐到“可运营排查”，满足设计里的筛选要求。

### 预期改动

- 增加管理员筛选。
- 增加 IP 筛选或 IP 精确搜索入口。
- 增加创建时间范围筛选。
- 保留现有登录账号、IP、User-Agent 搜索能力。
- 登录日志保持只读，不提供编辑和删除按钮。

### 重点文件

- `app/Filament/Resources/LoginLogs/LoginLogResource.php`
- `tests/Feature/AdminFoundation/LoginLogResourceTest.php`

### 验收标准

- 可以按管理员筛选登录日志。
- 可以按登录结果筛选登录日志。
- 可以按时间范围筛选登录日志。
- 可以搜索登录账号、IP、User-Agent。
- 登录日志资源仍然只读。

## Step 4：文档同步与发布前验收

### 目标

把一期真实状态同步到公开说明、功能说明和开发梳理文档中，并做一次发布前验收。

### 预期改动

- 更新 `docs/features/admin-foundation.md`，补充收口后的最终能力。
- 更新 `docs/guide/overview.md`，只把真实已完成能力写进去。
- 更新 `doc/一期开发后的梳理.md`，修正当前状态和剩余风险。
- 必要时更新 `AGENTS.md`，只加入后续开发必须记住的硬规则。

### 发布前人工验收清单

- 超级管理员可以访问一期所有后台入口。
- 普通管理员只能看到有权限的菜单和资源。
- 数据权限能限制管理员列表和登录日志列表。
- 菜单启停、权限绑定、排序后左侧导航真实变化。
- 禁用管理员不能访问后台。
- 关键后台动作能在操作日志中查到。
- 登录日志筛选可用于排查账号、IP、时间段问题。

### 自动验证命令

```bash
composer test
composer phpstan
composer pint:test
```

### 验收标准

- 文档状态与代码状态一致。
- 自动测试、静态检查和格式检查通过。
- 发布前人工验收清单无阻塞项。
- 若有非阻塞遗留项，必须在文档里标明具体原因和后续处理位置。

## 建议执行顺序

1. 先做 Step 2：密码重置权限收严。它风险小、边界清楚，也会影响 Step 1 的密码日志。
2. 再做 Step 1：关键后台动作写入操作日志。
3. 再做 Step 3：管理员登录日志筛选体验补齐。
4. 最后做 Step 4：文档同步与发布前验收。

## 不在本次收口范围

- 媒体库、头像上传、附件管理。
- 系统配置、插件中心、API、导入导出。
- 字段级权限、数据脱敏、多组织、多部门任职。
- 审计日志防篡改、审计通知、审计报表。
- 升级 PHP、Laravel、Filament 或 Activitylog 主版本。
