# FilamentAdmin 阶段二至七执行设计

**文档版本**: v1.0  
**创建日期**: 2026-05-28  
**依赖文档**: `2026-05-28-filament-admin-v1-development-plan.md`

---

## 一、执行策略

### 核心原则

- **顺序执行**：8 个功能域按依赖顺序依次完成，每个功能域结束后进入下一个
- **减量测试**：只写核心路径测试，覆盖率目标 ≥ 50%（放弃边界场景和可选测试）
- **全量文档**：每个功能域的功能文档完整交付，不降低文档质量
- **功能域概念**：不再以"阶段"组织，改以"功能域"为单位，每个功能域独立交付

### 执行顺序（按依赖关系）

```
前置条件：阶段一完全完成（AdminUser CRUD Resource + 测试 + 文档）

1. 权限体系       ← 只依赖 AdminUser 模型
2. 系统配置       ← 只依赖基础 Laravel
3. 媒体库         ← 只依赖基础 Laravel
4. API 规范       ← 只依赖基础 Laravel
5. 菜单管理       ← 依赖 权限体系
6. 操作日志       ← 依赖 系统配置
7. 插件系统       ← 依赖 权限体系 + 系统配置 + 所有 Resource
8. 收尾           ← 依赖所有功能域
```

---

## 二、功能域详细设计

### 功能域 1：权限体系

**Git Tag**: `v0.2.0-权限体系`

#### 技术选型

| 包 | 用途 |
|----|------|
| `spatie/laravel-permission` | 角色/权限底层存储与 Gate 集成 |
| `bezhansalleh/filament-shield` | 自动为 Resource 生成权限点 + 权限分配 UI |

#### 数据库变更

- `roles` / `permissions` / `model_has_roles` / `model_has_permissions` / `role_has_permissions` 表（Shield 迁移）

#### 核心实现

1. **AdminUser 集成**
   - 加 `HasRoles` Trait
   - `canAccessPanel()` 改为检查激活状态（暂时保持 true，配合 super_admin 逻辑）

2. **超级管理员机制**
   - 创建 `super_admin` 角色（可通过 `filament-admin.php` 配置角色名）
   - `AuthServiceProvider`：`Gate::before()` 拦截器，super_admin 跳过所有权限检查
   - Seeder：`SuperAdminSeeder` 创建首个超级管理员账号

3. **Shield 集成**
   - `php artisan shield:install` 生成权限
   - 为已有 Resource（AdminUser、LoginLog）自动生成权限点
   - Panel Provider 启用 Shield Plugin

4. **RoleResource**
   - 角色 CRUD（名称、描述）
   - 权限分配：按 Resource 分组的 `CheckboxList`
   - 角色成员列表（关联管理员）

5. **BasePolicy 抽象基类**
   - 封装 `viewAny / view / create / update / delete / restore / forceDelete`
   - 默认实现：检查对应 Spatie Permission 权限点
   - AdminUser Policy 继承 BasePolicy

#### 测试范围（核心路径）

- super_admin 绕过所有权限检查 ✓
- 普通角色只能访问已分配权限的 Resource ✓
- 未分配任何角色的管理员被 403 拦截 ✓
- 角色创建、权限分配、绑定管理员 ✓

#### 文档交付

- `docs/features/permissions.md`（完整权限体系说明）
- `docs/development/custom-permissions.md`（如何为新 Resource 添加权限）

---

### 功能域 2：系统配置

**Git Tag**: `v0.3.0-系统配置`

#### 技术选型

| 包 | 用途 |
|----|------|
| `spatie/laravel-settings` | 类型安全的配置类 |
| `filament/spatie-laravel-settings-plugin` | 自动生成 Filament 配置页面 |

#### 数据库变更

- `settings` 表（Settings 迁移）

#### 核心实现

1. **GeneralSettings 类**
   - 属性：`site_name`、`site_logo`、`icp_number`、`contact_email`
   - 加密字段：无（第一版）
   - 数据库存储 + Redis 缓存

2. **配置页面**
   - 使用 Settings Plugin 自动生成
   - 分组 Tab：基础配置 / 插件配置（插件配置 Tab 第一版为空，供插件系统使用）

3. **缓存机制**
   - `filament-admin.php` 配置 `settings_cache_driver`（默认 redis）
   - 配置保存时自动清除缓存

4. **SettingsUpdated 事件**
   - 配置变更时触发
   - 占位用于后续操作日志记录

5. **插件配置注册接口**
   - `SettingsRegistry::register(string $settingsClass)` 方法
   - 插件系统后期使用该接口将自己的 Settings 类注入"插件配置"Tab

#### 测试范围（核心路径）

- 读写 GeneralSettings ✓
- 修改后缓存失效 ✓
- SettingsUpdated 事件触发 ✓

#### 文档交付

- `docs/features/settings.md`（配置系统说明、配置类编写规范、缓存机制）

---

### 功能域 3：媒体库

**Git Tag**: `v0.4.0-媒体库`

#### 技术选型

| 包 | 用途 |
|----|------|
| `spatie/laravel-medialibrary` | 文件关联、缩略图生成 |
| `filament/spatie-laravel-media-library-plugin` | Filament 上传组件集成 |

#### 数据库变更

- `media` 表（MediaLibrary 迁移）

#### 核心实现

1. **基础配置**
   - 默认磁盘：`public`（本地存储）
   - 缩略图：`thumb`（150×150）、`medium`（600×600）、`large`（1200×1200）
   - 队列处理缩略图（`MEDIA_QUEUE_CONVERSIONS=true`）

2. **MediaResource**
   - 展示：文件名、类型、大小、上传时间、关联 Model
   - 筛选：按文件类型（image/video/document/other）、按时间范围
   - 预览：图片内嵌预览，其他文件显示下载链接
   - 删除：直接删除（同时删除物理文件）

3. **Collection 规范**
   - 预定义 Collection：`default`、`avatars`、`attachments`
   - 编写 Collection 使用规范（供业务 Model 参考）

#### 测试范围（核心路径）

- 上传图片后 media 记录创建 ✓
- 缩略图 URL 可访问 ✓
- 删除媒体文件后物理文件一并删除 ✓

#### 文档交付

- `docs/features/media.md`（媒体库使用、Collection 分组、为 Model 关联媒体）

---

### 功能域 4：API 规范

**Git Tag**: `v0.5.0-api`

#### 技术选型

| 包 | 用途 |
|----|------|
| `laravel/sanctum` | Bearer Token 认证（Laravel 13 默认已安装） |

#### 核心实现

1. **路由分离**
   - `routes/api.php`：`/api/v1/*`（公开 API + Token 认证）
   - `routes/admin-api.php`：`/admin/api/*`（后台专用 API，Session 认证）

2. **统一响应格式**
   ```json
   {
     "data": {},
     "message": "操作成功",
     "code": 0
   }
   ```
   - 分页响应：`data[]` + `meta.pagination`
   - 验证错误（422）：`errors.field[]`
   - 业务错误：`code` 非零 + `message`

3. **BusinessException**
   - `BusinessException(int $code, string $message, int $httpStatus = 400)`
   - `Handler.php` 捕获并统一格式化

4. **BaseApiResource**
   - 包裹 `data` 字段
   - 分页响应自动生成 `meta.pagination`

5. **ErrorCodeRegistry**
   - 单例，管理全局错误码
   - 核心保留段：`0`（成功），`1000-1999`（认证），`2000-2999`（权限），`3000-3999`（验证），`9000-9999`（系统）
   - `register(int $code, string $name, string $description)` 方法
   - `php artisan filament-admin:check-error-codes` 检测冲突
   - 插件注册自己的错误码段（`10000+` 为插件段）

6. **示例 Controller**
   - `AuthController`：`POST /api/v1/auth/login` 返回 Sanctum Token
   - 展示完整响应格式

#### 测试范围（核心路径）

- Token 生成和验证 ✓
- 成功/422/业务错误响应格式符合规范 ✓
- 错误码冲突检测 ✓

#### 文档交付

- `docs/features/api.md`（认证、响应格式、错误码规范）
- `docs/reference/api-response.md`（完整格式示例）
- `docs/reference/error-codes.md`（核心错误码清单）

---

### 功能域 5：菜单管理

**Git Tag**: `v0.6.0-菜单管理`

**前置条件**: 权限体系完成（需要 Spatie Permission 的权限点绑定）

#### 数据库变更

- `menus` 表：`id / parent_id / name / icon / route / sort / permission / is_active`

#### 核心实现

1. **MenuResource**
   - 树形列表（`parent_id` 嵌套）
   - 字段：菜单名、图标、路由/URL、排序、绑定权限点、是否启用
   - 排序：手动输入 `sort` 数字（第一版不做拖拽，降低复杂度）

2. **权限绑定**
   - 选择 Spatie Permission 的权限点（可选字段，为空则所有人可见）
   - 权限点通过 `Select` 从 Permission 表加载

3. **动态导航**
   - `AdminPanelProvider` 使用 `NavigationBuilder` 从数据库构建
   - 根据当前用户权限过滤菜单
   - 缓存菜单结构（按用户角色，Redis 5 分钟）

#### 测试范围（核心路径）

- 创建菜单并绑定权限后，无权限用户看不到该菜单 ✓
- super_admin 能看到所有菜单 ✓
- 菜单排序生效 ✓

#### 文档交付

- `docs/features/menu.md`（菜单管理、权限绑定、插件如何注册菜单）

---

### 功能域 6：操作日志

**Git Tag**: `v0.7.0-操作日志`

**前置条件**: 系统配置完成（日志保留天数来自 Settings）

#### 技术选型

| 包 | 用途 |
|----|------|
| `spatie/laravel-activitylog` | 操作日志记录、before/after diff |
| `pxlrbt/filament-activity-log` | Filament Resource 集成 |

#### 数据库变更

- `activity_log` 表（ActivityLog 迁移）

#### 核心实现

1. **opt-in 策略**
   - 默认不记录：Model 不加 `LogsActivity` Trait 则无日志
   - 已开启 Model：`AdminUser`、`Role`
   - `getActivitylogOptions()` 标准配置（记录所有字段，隐藏 password / two_factor_secret）

2. **ActivityLogResource**
   - 只读列表：操作人、操作对象类型 + ID、操作类型（created/updated/deleted）、时间
   - 详情页：before/after JSON（原始展示，不做 diff 高亮）
   - 筛选：操作人、对象类型、时间范围

3. **日志清理**
   - `filament-admin:clean-activity-log --days=N`（N 默认读 GeneralSettings 中的 `log_retention_days`）
   - `filament-admin:clean-login-logs --days=N`（同上）
   - `routes/console.php` 注册 daily 调度

#### 测试范围（核心路径）

- AdminUser 更新触发日志，diff 正确 ✓
- 未加 Trait 的 Model 不产生日志 ✓
- 清理命令删除超期日志 ✓

#### 文档交付

- `docs/features/activity-log.md`（操作日志机制、opt-in 策略、清理策略）
- `docs/development/conventions.md` 补充"如何为业务 Model 启用日志"

---

### 功能域 7：插件系统

**Git Tag**: `v0.8.0-插件系统`

**前置条件**: 权限体系 + 系统配置 + 所有 Resource 完成

#### 技术选型

| 包 | 用途 |
|----|------|
| `nwidart/laravel-modules` | 业务模块型插件的生命周期管理 |
| `spatie/laravel-package-tools` | 工具型插件骨架 |

#### 数据库变更

- `plugins` 表：`id / name / slug / type(package|module) / version / is_enabled / settings_class / description`

#### 核心实现

1. **插件架构规范**
   - `composer.json` `extra.filament-admin` 字段：`{ "type": "package|module", "name": "", "slug": "" }`
   - `ModuleFilamentPlugin` 基类（封装 `discoverResources/discoverPages/discoverWidgets`）

2. **nwidart 集成**
   - `modules_statuses.json` 控制模块启停
   - 示例模块：`Modules/Example`（含 Resource、Migration、Seeder）

3. **插件扫描命令**
   - `filament-admin:scan-plugins`
   - 扫描所有 Composer 包中含 `extra.filament-admin` 的包
   - 扫描 `Modules/` 目录下的模块
   - 写入 `plugins` 表（已存在则更新版本）

4. **PluginResource**
   - 展示：名称、类型、版本、说明、启停状态
   - 启用/禁用操作：
     - `package` 类型：更新 `plugins.is_enabled`
     - `module` 类型：更新 `modules_statuses.json` + `plugins.is_enabled`
   - 配置入口：若 `settings_class` 不为空，显示"配置"按钮跳转

5. **Panel 层动态注册**
   - `AdminPanelProvider` 启动时查询 `plugins` 表
   - `is_enabled = true` 的插件才调用 `->plugin(XxxPlugin::make())`

6. **依赖检查（简单版）**
   - `plugins` 表增加 `requires` JSON 字段（slug 列表）
   - 禁用前检查是否有其他启用插件依赖此插件，有则显示警告并阻止

7. **远程插件市场**
   - 从 GitHub Raw 读取 JSON 索引文件
   - 索引格式：`[{ "name", "slug", "type", "version", "description", "install_command", "docs_url", "source": "official|recommended|community" }]`
   - `MarketplaceResource`：浏览 + 搜索 + 详情
   - 网络失败时降级：显示"无法连接市场"提示

#### 测试范围（核心路径）

- 扫描命令发现已安装的 Composer 包和模块 ✓
- 禁用插件后 Panel 不注册该插件 ✓
- 依赖检查阻止禁用被依赖插件 ✓
- 远程索引解析 ✓

#### 文档交付

- `docs/plugins/overview.md`（双轨制架构说明）
- `docs/plugins/using-plugins.md`（安装/启用/禁用/配置）
- `docs/plugins/develop-package.md`（工具型插件开发指南）
- `docs/plugins/develop-module.md`（业务模块型插件开发指南）

---

### 功能域 8：收尾

**Git Tag**: `v1.0.0`

**前置条件**: 所有功能域完成

#### 核心实现

1. **阿里云 OSS 官方插件**
   - 独立仓库 `filament-admin/oss-plugin`（或 `filament-admin-oss`）
   - 基于 `spatie/laravel-package-tools` 骨架
   - `OssSettings` 类（AccessKey/SecretKey/Bucket/Endpoint/Domain）
   - 配置 MediaLibrary 使用 OSS 磁盘
   - README + 配置说明 + 故障排查
   - 发布到 Packagist

2. **CI/CD 完善**
   - GitHub Actions：PR + 主分支 push 触发
   - PHP 矩阵：8.3 / 8.4
   - 检查项：Pest 测试 + Larastan level 6 + Pint + `composer audit`
   - 测试覆盖率上报

3. **项目文档完善**
   - `docs/architecture/tech-stack.md`（所有包的选型理由）
   - `docs/architecture/customizations.md`（定制改动清单）
   - `docs/architecture/directory-structure.md`（目录结构说明）
   - `docs/development/base-classes.md`（基类与 Trait 说明）
   - `docs/development/conventions.md`（命名规范完整版）

4. **发布准备**
   - `CHANGELOG.md`（Keep a Changelog 格式）
   - `UPGRADE.md`（占位）
   - `CONTRIBUTING.md`
   - `SECURITY.md`
   - `README.md` 重写（特色功能 + 快速安装 + 文档索引）
   - GitHub Release：`v1.0.0` + Release Notes

---

## 三、质量目标（调整后）

| 维度 | 目标 |
|------|------|
| 测试覆盖率 | ≥ 50%（只测核心路径，放弃边界场景） |
| 静态分析 | Larastan level 6 |
| 格式检查 | Pint PSR-12 |
| 功能文档 | 每个功能域有对应的 `docs/features/` 文档 |
| 开发者文档 | `docs/development/` 完整 |
| 架构文档 | `docs/architecture/` 完整（收尾阶段完成） |

---

## 四、Git Tag 规划

| 功能域 | Tag |
|--------|-----|
| 阶段一（认证+基础） | `v0.1.0` |
| 权限体系 | `v0.2.0` |
| 系统配置 | `v0.3.0` |
| 媒体库 | `v0.4.0` |
| API 规范 | `v0.5.0` |
| 菜单管理 | `v0.6.0` |
| 操作日志 | `v0.7.0` |
| 插件系统 | `v0.8.0` |
| 收尾/正式发布 | `v1.0.0` |

---

## 五、已知风险与降级方案

| 风险 | 降级方案 |
|------|---------|
| Filament NavigationBuilder 不支持完全动态 | 先用静态注册 + 权限过滤，第二版再做动态 |
| Panel 层动态注册插件遇框架限制 | 改为在 Provider 中用 config 控制，手动维护插件列表 |
| 远程市场网络不稳定 | 本地 JSON 缓存 24 小时，失败静默降级 |
| nwidart/laravel-modules 与 Filament 5 兼容性问题 | 先跑通 Demo，若冲突降级到自研简单模块加载机制 |
