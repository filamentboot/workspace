# FilamentAdmin 第一版开发计划

**文档版本**: v1.0  
**创建日期**: 2026-05-28  
**计划周期**: 10 个月  
**目标版本**: v1.0.0  

---
 本项目能用框架自带的，就用框架自带的。
 开发功能使用官方推荐的或者社区推荐的最优方案 
 制定开发规划的时候 @doc/需求.md 里的 很多技术方案带进来。  

## 一、项目背景

### 项目定位

FilamentAdmin 是一个基于 Laravel 13 + Filament 5 的后台基础平台，主打两大特色：

1. **开箱即用的代码市场**：对标 FastAdmin 的插件市场，支持双轨制插件（工具型插件住 `vendor/`，业务模块型插件解包到 `Modules/`）
2. **组合最优的代码基座**：严格选型标准，所有集成包都有明确的选型理由和升级路径

### 第一版范围

第一版聚焦"可安装、可登录、可扩展、可开发业务模块"的后台 MVP，具体包括：

- 认证系统：管理员登录、2FA、登录日志
- 权限体系：角色、权限、Policy、超级管理员
- 菜单管理：动态菜单、权限绑定
- 系统配置：Settings 类、插件配置接入
- 操作日志：opt-in 策略、自动记录 diff、日志清理
- 媒体库：基于 Spatie MediaLibrary，本地存储
- API 规范：统一响应格式、错误码注册、Sanctum 认证
- 插件系统：双轨制架构、启停管理、远程市场索引、依赖检查
- 质量保障：测试覆盖率 ≥ 60%、完整文档体系、CI/CD

### 第一版不做（延后到第二版）

- 邮件通知系统（SMTP 配置、邮件模板、测试邮件发送）
- 官方插件：短信（阿里云/腾讯云）
- 官方插件：Excel 导入导出
- 插件 API 优化（根据多个官方插件开发经验优化）
- 异步安装插件（后台一键安装）
- 在线升级插件
- 数据权限（行级过滤）
- 演示环境

---

## 二、开发策略

### 方案选择：垂直切片（Vertical Slicing）

**核心思路**：按功能域串行推进，每个域都"实现 + 测试 + 文档"做到 100% 再进入下一个。

**选择理由**：

1. **心理激励**：个人业余开发最大风险是半途而废。垂直切片让每 1-2 个月都有"完整的功能域"交付，持续的成就感能支撑长期开发。
2. **质量稳定**：不存在"最后补文档/测试"的债务，每个阶段交付的都是真正可用的功能。
3. **降低返工风险**：因为有测试覆盖，后期功能域调整前期架构时重构成本可控。
4. **文档质量高**：实现完立刻写文档，细节还在脑子里，文档质量高且写得快。

### 时间分配原则

- 业余时间开发，按每周 15-20 小时计算
- 每个阶段包含：实现 → 测试 → 文档 → 收尾
- 每个阶段结束打 Git Tag，便于回溯和展示进度
- 预留 Buffer 时间应对风险（插件系统阶段预留 2 周）

---

## 三、七个阶段详细计划

### 阶段一：认证与基础架构（1.5 个月）

**阶段目标**：搭建 FilamentAdmin 的骨架，实现"能登录、能管理管理员账号、有安全保障"的基础后台。

#### 核心任务

**1. 项目初始化与环境配置（3 天）**
- 基于当前 Laravel 13 + Filament 5 初始化代码整理目录结构
- 配置 Pest + Larastan + Pint，跑通 GitHub Actions CI
- 创建 `docs/` 目录结构，初始化所有文档骨架（空文件占位）
- 配置 `filament-admin.php` 核心配置文件

**2. 管理员认证系统（7 天）**
- 创建 `admin_users` 表 Migration（区别于 Laravel 默认 `users`）
- 实现 `AdminUser` Model + Policy
- 配置 Filament Panel 使用 `admin_users` 作为认证守卫
- 实现管理员 CRUD Resource（创建、编辑、禁用、密码重置）
- 实现登录限流（复用 Filament 5 内置能力，确认配置正确）

**3. 双因素认证（2FA）（5 天）**
- 集成 `stephenjude/filament-two-factor-authentication`
- 配置 TOTP + Recovery Code（Passkey 可选，标记为"体验优化"延后）
- 实现"默认关闭，管理员个人启用"的机制
- 测试：从启用 2FA 到登录验证的完整流程

**4. 登录日志（4 天）**
- 创建 `login_logs` 表 Migration
- 监听 `AdminLoggedIn` / `AdminLoggedOut` / `AdminLoginFailed` 事件写入日志
- 实现登录日志 Resource（只读，展示 IP、UA、时间、结果）
- 实现定期清理命令（`php artisan filament-admin:clean-login-logs --days=90`）

**5. 基础测试覆盖（8 天）**
- 管理员 CRUD 单元测试（创建、更新、禁用、Policy 校验）
- 登录流程测试（成功、失败、限流、2FA 开启后的流程）
- 登录日志写入测试
- 目标覆盖率：本阶段代码 70%+

**6. 文档编写（6 天）**
- `docs/guide/installation.md`（环境要求、安装步骤、首次配置）
- `docs/architecture/overview.md`（整体架构图骨架，当前阶段只写认证部分）
- `docs/features/authentication.md`（登录、2FA、登录日志完整说明）
- `README.md` 初版（项目介绍、快速开始、文档索引）

**7. 阶段收尾（2 天）**
- 代码 Review 自查（Larastan level 6、Pint 检查）
- 补充遗漏的行内注释和 PHPDoc
- 提交阶段 Git Tag：`v0.1.0-认证与基础架构`

#### 验收标准
- [ ] 能通过 `php artisan migrate` 初始化数据库
- [ ] 能创建管理员账号并登录后台
- [ ] 启用 2FA 后登录需要输入验证码
- [ ] 登录日志能正确记录成功/失败
- [ ] Pest 测试全部通过，本阶段代码覆盖率 ≥ 70%
- [ ] Larastan level 6 通过，Pint 格式检查通过
- [ ] 三篇核心文档完整可读，无占位符

#### 风险与应对
- **风险**：Filament 5 的 2FA 插件可能有兼容性问题（新版本刚发布）
  - **应对**：前 3 天先跑通插件 Demo，不行就降级到纯 TOTP 方案
- **风险**：个人开发写测试容易拖延
  - **应对**：严格执行"实现完当天必须写测试"，不能拖到阶段末尾

#### 时间分配
- 第 1-2 周：任务 1-3（初始化 + 管理员 + 2FA）
- 第 3-4 周：任务 4-5（登录日志 + 测试）
- 第 5-6 周：任务 6-7（文档 + 收尾）

---

### 阶段二：权限体系（1.5 个月）

**阶段目标**：实现完整的角色权限系统，覆盖 Filament Resource 的所有操作（viewAny/view/create/update/delete 等），并沉淀可复用的权限规范。

#### 核心任务

**1. Spatie Permission 集成（5 天）**
- 安装 `spatie/laravel-permission`，执行 Migration（roles/permissions 表）
- 配置 `AdminUser` Model 使用 `HasRoles` Trait
- 实现超级管理员机制：
  - 创建 `super_admin` 角色（可配置角色名）
  - 在 `AuthServiceProvider` 中注册 `Gate::before()` 拦截器（super_admin 绕过所有权限检查）
  - 数据库 Seeder 创建首个超级管理员账号
- 测试：超级管理员能访问所有资源，普通管理员不能

**2. Filament Shield 集成（6 天）**
- 安装 `bezhansalleh/filament-shield`
- 执行 `php artisan shield:install` 生成权限
- 为阶段一创建的 `AdminUserResource` 自动生成权限点（`view_admin_user`/`create_admin_user`/`update_admin_user`/`delete_admin_user` 等）
- 在 Panel Provider 中启用 Shield Plugin
- 测试：创建测试角色，分配部分权限，验证访问控制生效

**3. 角色管理 Resource（7 天）**
- 实现 `RoleResource`：角色 CRUD、权限分配 UI
- 使用 Filament 的 `CheckboxList` 或 `Select` 组件展示权限树
- 权限按 Resource 分组展示（如"管理员管理"分组下有 view/create/update/delete 四个权限）
- 实现角色成员管理（查看哪些管理员属于该角色）
- 测试：创建角色、分配权限、绑定管理员、验证权限生效

**4. 权限点管理 Resource（可选，3 天）**
- 实现 `PermissionResource`（只读展示）
- 展示系统中所有权限点、所属 Resource、关联角色
- 提供搜索和筛选功能
- **注意**：权限点不应手动创建，应由 Shield 自动生成，此 Resource 仅用于查看和调试

**5. Policy 规范与基类（5 天）**
- 创建 `BasePolicy` 抽象类，封装常见权限检查逻辑
- 为 `AdminUserResource` 编写完整 Policy（覆盖所有 Filament 操作）
- 沉淀 Policy 编写规范文档（什么时候用 Policy、如何处理超级管理员、如何测试）
- 测试：覆盖所有 Policy 方法（viewAny/view/create/update/delete/restore/forceDelete）

**6. 数据权限预留（2 天）**
- 在 `BaseResource` 中预留 `scopeQuery()` 方法（空实现，标记为 `@deprecated until v2`）
- 在文档中说明"数据权限第一版不实现，第二版补全"
- 提供示例代码注释，展示如何在第二版中重写该方法

**7. 完整测试覆盖（8 天）**
- 权限分配与验证测试（创建角色、分配权限、检查 Gate 是否正确拦截）
- 超级管理员绕过权限测试
- Policy 边界测试（禁用的管理员不能登录、不属于任何角色的管理员访问被拒绝）
- 多角色场景测试（一个管理员属于多个角色，权限合并是否正确）
- 目标覆盖率：本阶段代码 70%+

**8. 文档编写（6 天）**
- `docs/features/permissions.md`（完整权限体系说明：Spatie + Shield 架构、超级管理员机制、如何为新 Resource 生成权限、Policy 编写规范）
- `docs/development/custom-permissions.md`（开发者指南：为自己的 Resource 注册权限、编写 Policy、测试权限）
- `docs/architecture/tech-stack.md` 补充权限部分（为什么选 Spatie、与 Bouncer 等替代方案的对比）
- 更新 `README.md` 添加权限系统介绍

**9. 阶段收尾（2 天）**
- 代码 Review 自查
- 补充遗漏的测试用例
- 提交阶段 Git Tag：`v0.2.0-权限体系`

#### 验收标准
- [ ] 能创建角色、分配权限、绑定管理员
- [ ] 超级管理员能访问所有资源，普通角色受权限限制
- [ ] 没有权限的管理员访问 Resource 时被 403 拦截
- [ ] 所有 Policy 方法都有对应测试，覆盖率 ≥ 70%
- [ ] Larastan level 6 通过，Pint 格式检查通过
- [ ] 三篇权限文档完整可读，开发者能按文档为新 Resource 添加权限

#### 风险与应对
- **风险**：Filament Shield 的权限 UI 可能不符合预期（如权限树太深、分组逻辑不清晰）
  - **应对**：第 2 周做 Shield 集成时先跑 Demo，如果 UI 不满意，考虑自己实现权限分配表单（底层仍用 Spatie）
- **风险**：Policy 边界情况复杂（如软删除、强制删除、恢复的权限区分）
  - **应对**：参考 Filament 官方文档的 Policy 示例，不自己发明规则
- **风险**：测试编写时间可能超出预期（权限场景太多）
  - **应对**：优先测试核心路径（超级管理员、普通角色、无权限），边界场景标记为 TODO

#### 时间分配
- 第 1-2 周：任务 1-2（Spatie 集成 + Shield 集成）
- 第 3-4 周：任务 3-5（角色管理 + Policy 规范）
- 第 5-6 周：任务 7-9（测试 + 文档 + 收尾）
- 任务 4（权限点管理）和任务 6（数据权限预留）穿插在空闲时间

---

### 阶段三：菜单与配置（1.25 个月）

**阶段目标**：实现后台菜单管理和系统配置管理，让 FilamentAdmin 具备"开箱即配"的能力。

#### 核心任务

**1. 菜单管理系统（8 天）**
- 创建 `menus` 表 Migration（父级菜单、图标、排序、路由、权限绑定）
- 实现 `MenuResource`：菜单 CRUD、树形展示、拖拽排序
- 实现菜单与权限点的绑定逻辑（某菜单只对有权限的角色可见）
- 从数据库动态生成 Filament Panel 的 NavigationItem
- 测试：创建菜单、调整顺序、绑定权限、验证不同角色看到的菜单不同

**2. 系统配置框架（7 天）**
- 安装 `spatie/laravel-settings` + `filament/spatie-laravel-settings-plugin`
- 创建 `settings` 表 Migration
- 实现第一个配置类：`GeneralSettings`（站点名称、Logo、备案号、联系方式）
- 配置 Filament Settings Plugin，自动生成配置页面
- 实现配置缓存机制（Redis/File Cache）
- 测试：修改配置、验证缓存失效、验证前端读取到最新配置

**3. 配置分组与插件配置接入（4 天）**
- 实现配置页面的分组 Tab（基础配置、插件配置等）
- 设计插件配置注册机制：插件可以注册自己的 Settings 类，自动出现在"插件配置"分组
- 编写插件配置注册文档（供后续插件开发参考）
- 测试：创建一个 Mock 插件配置类，验证能正确出现在后台

**4. 配置变更事件与审计（3 天）**
- 实现 `SettingsUpdated` 事件，配置变更时触发
- 将配置变更写入操作日志（复用后续阶段四的操作日志系统，如未实现则先占位）
- 测试：修改配置、验证事件触发、验证操作日志记录

**5. 完整测试覆盖（6 天）**
- 菜单 CRUD 测试（创建、排序、权限绑定、动态生成导航）
- 配置读写测试（配置类属性赋值、缓存机制、缓存失效）
- 配置页面测试（Filament Form 提交、验证规则）
- 目标覆盖率：本阶段代码 65%+（UI 交互部分测试成本高，适当降低目标）

**6. 文档编写（6 天）**
- `docs/features/menu.md`（菜单管理、权限绑定、插件如何注册菜单）
- `docs/features/settings.md`（系统配置、配置类编写规范、缓存机制、插件配置注册）
- `docs/development/conventions.md` 补充配置类命名规范
- 更新 `docs/architecture/tech-stack.md` 添加 Spatie Settings 选型说明

**7. 阶段收尾（2 天）**
- 代码 Review 自查
- 提交阶段 Git Tag：`v0.3.0-菜单与配置`

#### 验收标准
- [ ] 能在后台创建菜单、调整顺序、绑定权限，不同角色看到的菜单不同
- [ ] 能修改系统配置（站点名称等），配置自动缓存
- [ ] 插件能注册自己的配置类，出现在后台配置页面
- [ ] 测试覆盖率 ≥ 65%，Larastan/Pint 通过
- [ ] 文档完整，开发者能按文档创建配置类

#### 风险与应对
- **风险**：菜单动态生成可能与 Filament Panel 的静态导航机制冲突
  - **应对**：第 1 周先调研 Filament 的 `NavigationBuilder`，确认能从数据库动态生成导航
- **风险**：配置缓存失效时机可能遗漏（如队列 Worker 读到旧配置）
  - **应对**：文档中明确说明"配置变更后需重启 Worker"，第二版再考虑自动通知

#### 时间分配
- 第 1-2 周：任务 1-2（菜单管理 + 配置框架）
- 第 3 周：任务 3-4（插件配置接入 + 配置变更事件）
- 第 4-5 周：任务 5-7（测试 + 文档 + 收尾）

---

### 阶段四：日志与审计（1 个月）

**阶段目标**：实现操作日志（opt-in 策略）和日志清理机制，补全系统的可追溯性。

#### 核心任务

**1. Spatie ActivityLog 集成（5 天）**
- 安装 `spatie/laravel-activitylog` + `pxlrbt/filament-activity-log`
- 创建 `activity_log` 表 Migration
- 配置 opt-in 策略：默认只记录 Filament Action 触发的操作（通过 Filament Event 监听）
- 为 `AdminUser` / `Role` 等核心 Model 启用日志（在 Model 上 `use LogsActivity` Trait）
- 测试：创建/更新/删除管理员，验证日志自动记录 before/after diff

**2. 操作日志 Resource（6 天）**
- 实现 `ActivityLogResource`（只读）
- 展示：操作人、操作对象、操作类型（created/updated/deleted）、变更前后 JSON diff
- 实现筛选器：按操作人、按对象类型、按时间范围筛选
- 实现 diff 可视化展示（使用 Filament 的 JSON 组件或自定义 View）
- 测试：查看日志列表、筛选、查看 diff 详情

**3. 日志清理机制（4 天）**
- 实现 `filament-admin:clean-activity-log --days=N` 命令
- 实现 `filament-admin:clean-login-logs --days=N` 命令（复用阶段一的登录日志）
- 在 `filament-admin.php` 配置文件中添加 `log_retention_days` 配置项
- 配置 Laravel Scheduler：每天凌晨自动执行清理（在 `routes/console.php` 或 Kernel 中注册）
- 测试：手动执行命令、验证旧日志被删除、验证定时任务注册正确

**4. 业务 Model 日志接入指南（3 天）**
- 编写示例代码：如何为自己的业务 Model 启用日志
- 说明哪些 Model 应该记录日志（用户敏感操作）、哪些不应该（高频写入表）
- 提供 `getActivitylogOptions()` 配置示例（指定记录字段、忽略字段、日志名称）
- 补充到文档中

**5. 日志事件与通知（可选，3 天）**
- 实现 `ActivityLogged` 事件（日志写入时触发）
- 监听关键操作（如删除管理员、修改权限）发送通知给超级管理员
- 测试：删除管理员、验证超级管理员收到通知（第二版才做通知系统，则先标记为 TODO）

**6. 完整测试覆盖（6 天）**
- 日志自动记录测试（CRUD 操作触发日志写入）
- opt-in 策略测试（未启用 Trait 的 Model 不自动记录日志）
- 日志清理测试（创建过期日志、执行清理、验证删除）
- diff 展示测试（验证 before/after JSON 正确）
- 目标覆盖率：本阶段代码 70%+

**7. 文档编写（5 天）**
- `docs/features/activity-log.md`（操作日志机制、opt-in 策略、如何查看日志、清理策略）
- `docs/development/conventions.md` 补充"如何为业务 Model 启用日志"
- 更新 `docs/architecture/customizations.md` 说明"操作日志默认策略与 Spatie 原版的差异"

**8. 阶段收尾（2 天）**
- 代码 Review 自查
- 提交阶段 Git Tag：`v0.4.0-日志与审计`

#### 验收标准
- [ ] 管理员 CRUD 操作自动记录日志，能在后台查看 diff
- [ ] 业务 Model 默认不记录日志，添加 Trait 后开始记录
- [ ] 日志清理命令能正确删除过期日志
- [ ] 定时任务每天自动执行清理
- [ ] 测试覆盖率 ≥ 70%，Larastan/Pint 通过
- [ ] 文档完整，开发者能按文档为自己的 Model 启用日志

#### 风险与应对
- **风险**：opt-in 策略实现可能不符合预期（如 Filament Action 监听不到所有操作）
  - **应对**：先调研 Filament Event 机制，如果监听不到则改为"手动在 Resource 中调用 `activity()->log()`"
- **风险**：JSON diff 可视化可能很复杂（嵌套对象、大 JSON）
  - **应对**：第一版只做简单展示（raw JSON），第二版再优化 UI

#### 时间分配
- 第 1-2 周：任务 1-2（ActivityLog 集成 + 日志 Resource）
- 第 3 周：任务 3-4（清理机制 + 接入指南）
- 第 4 周：任务 6-8（测试 + 文档 + 收尾）
- 任务 5（日志事件）穿插在空闲时间或标记为可选

---

### 阶段五：媒体库与 API（1.5 个月）

**阶段目标**：实现媒体库（基于 Spatie MediaLibrary）和统一 API 响应规范，为业务模块提供文件上传和 API 能力。

#### 核心任务

**1. Spatie MediaLibrary 集成（6 天）**
- 安装 `spatie/laravel-medialibrary` + `filament/spatie-laravel-media-library-plugin`
- 创建 `media` 表 Migration
- 配置默认磁盘（本地 `public` 磁盘）
- 配置图片自动生成缩略图（thumb/medium/large 三种尺寸）
- 创建示例 Model（如 `Post`），关联媒体库，验证上传功能
- 测试：上传图片、验证缩略图生成、验证文件关联

**2. 媒体库管理 Resource（7 天）**
- 实现 `MediaResource`：展示所有已上传文件
- 展示：文件名、类型、大小、上传者、上传时间、关联的 Model
- 实现筛选器：按文件类型（图片/视频/文档）、按上传者、按时间筛选
- 实现文件预览（图片直接预览，其他文件显示下载链接）
- 实现文件删除（软删除或直接删除，可配置）
- 测试：查看文件列表、筛选、预览、删除

**3. 媒体库 Collection 分组（可选，3 天）**
- 为不同业务场景创建 Collection（如 `avatar` / `post_images` / `attachments`）
- 在上传时指定 Collection，便于后续管理
- 在 MediaResource 中按 Collection 筛选
- 测试：上传到不同 Collection、验证筛选正确

**4. API 认证与路由（5 天）**
- 安装 `laravel/sanctum`（Laravel 13 默认已安装，确认配置）
- 配置 API 认证守卫（Bearer Token + Session 双模式）
- 创建 `/api/v1` 路由文件（`routes/api.php`）
- 创建 `/admin/api` 路由文件（`routes/admin-api.php`）
- 实现 Token 生成 API（`POST /api/v1/auth/login`，返回 Sanctum Token）
- 测试：生成 Token、使用 Token 访问受保护 API

**5. API 响应规范实现（6 天）**
- 创建 `BusinessException` 异常类（携带 `code` 字段）
- 在 `app/Exceptions/Handler.php` 中统一处理异常，返回 JSON 格式
- 创建 API Resource 基类（`BaseApiResource`），统一包裹 `data` 字段
- 实现分页响应（`meta` + `links` 字段）
- 创建示例 API Controller（如 `AdminUserController`），展示完整响应格式
- 测试：成功响应、验证错误响应、业务错误响应、分页响应

**6. 错误码注册机制（5 天）**
- 创建 `ErrorCodeRegistry` 单例类，管理全局错误码
- 定义核心保留错误码（`AUTH_*` / `TOKEN_*` / `PERMISSION_*` 等）
- 实现错误码注册接口（插件可注册自己的错误码）
- 实现错误码冲突检测（`php artisan filament-admin:doctor` 命令）
- 创建 `docs/reference/error-codes.md` 文档，列出所有核心错误码
- 测试：注册错误码、检测冲突、查看文档

**7. 完整测试覆盖（8 天）**
- 媒体库测试（上传、缩略图生成、关联 Model、删除）
- API 认证测试（Token 生成、Token 访问、Token 过期）
- API 响应格式测试（成功/失败/分页/422 验证错误）
- 错误码注册测试（注册、冲突检测）
- 目标覆盖率：本阶段代码 65%+

**8. 文档编写（7 天）**
- `docs/features/media.md`（媒体库使用、Collection 分组、如何为 Model 关联媒体）
- `docs/features/api.md`（API 认证、响应格式规范、错误码规范）
- `docs/reference/api-response.md`（完整 API 响应格式示例）
- `docs/reference/error-codes.md`（核心错误码清单）
- `docs/development/conventions.md` 补充 API Controller 编写规范

**9. 阶段收尾（2 天）**
- 代码 Review 自查
- 提交阶段 Git Tag：`v0.5.0-媒体库与API`

#### 验收标准
- [ ] 能上传文件、自动生成缩略图、在后台查看和管理
- [ ] 能通过 API 登录获取 Token，使用 Token 访问受保护 API
- [ ] API 响应格式符合需求文档规范（data/message/code/errors/meta）
- [ ] 错误码注册机制可用，插件能注册自己的错误码
- [ ] 测试覆盖率 ≥ 65%，Larastan/Pint 通过
- [ ] 五篇文档完整，开发者能按文档使用媒体库和编写 API

#### 风险与应对
- **风险**：Spatie MediaLibrary 配置复杂（磁盘、缩略图、队列等）
  - **应对**：第 1 周先跑通官方 Demo，确认配置正确
- **风险**：API 响应格式统一处理可能遗漏边界情况（如 404、500）
  - **应对**：在 Handler 中捕获所有异常，确保始终返回 JSON
- **风险**：错误码管理可能变复杂（插件之间冲突、命名空间混乱）
  - **应对**：第一版只做基础注册和冲突检测，复杂场景延后

#### 时间分配
- 第 1-2 周：任务 1-2（MediaLibrary 集成 + 管理 Resource）
- 第 3-4 周：任务 4-6（API 认证 + 响应规范 + 错误码）
- 第 5-6 周：任务 7-9（测试 + 文档 + 收尾）
- 任务 3（Collection 分组）穿插在空闲时间或标记为可选

---

### 阶段六：插件系统（2.5 个月）

**阶段目标**：实现 FilamentAdmin 的核心特色——双轨制插件系统，包含本地插件中心、远程市场索引、启停管理、依赖检查。

#### 核心任务

**1. 插件架构设计与规范（7 天）**
- 编写插件开发规范文档（`composer.json` `extra.filament-admin` 字段定义）
- 定义工具型插件（`type: package`）和业务模块型插件（`type: module`）的标准结构
- 设计插件注册流程（ServiceProvider Auto-Discovery + Filament Plugin 接口）
- 创建插件基类：`ModuleFilamentPlugin`（封装 `discoverResources/discoverPages/discoverWidgets`）
- 编写示例插件（Mock OSS 插件），验证架构可行性

**2. nwidart/laravel-modules 集成（6 天）**
- 安装 `nwidart/laravel-modules`
- 创建 `modules_statuses.json`
- 创建示例业务模块（如 `Modules/Mall`），包含 Resource、Migration、Seeder
- 验证模块启停机制（通过 `modules_statuses.json` 控制）
- 测试：创建模块、启用、访问 Resource、禁用、验证 Resource 消失

**3. 插件注册表（5 天）**
- 创建 `plugins` 表 Migration（包名、类型、版本、启停状态、配置入口）
- 实现插件扫描命令（`php artisan filament-admin:scan-plugins`），自动发现已安装的 Composer 包和模块
- 将扫描结果写入 `plugins` 表
- 测试：安装插件、执行扫描、验证插件出现在数据库

**4. 插件中心 Resource（8 天）**
- 实现 `PluginResource`：展示已安装插件列表
- 展示：插件名称、类型（package/module）、版本、说明、启停状态
- 实现启用/禁用操作（工具型走数据库标记，模块型走 `modules_statuses.json`）
- 实现配置入口跳转（如果插件注册了 Settings 类，显示"配置"按钮）
- 实现依赖检查（插件 A 依赖插件 B，禁用 B 前显示警告）
- 测试：查看插件列表、启用/禁用、跳转配置、依赖检查

**5. Panel 层插件启停机制（6 天）**
- 在 `AdminPanelProvider` 中根据 `plugins` 表的启用状态动态注册 Filament Plugin
- 禁用的插件不调用 `->plugin(XxxPlugin::make())`
- 验证禁用后菜单、Resource、路由不可见
- 编写文档说明"启停/安装/卸载"的语义区分（参考需求文档表格）
- 测试：禁用插件、验证后台无该插件功能、启用后恢复

**6. 远程插件市场索引（7 天）**
- 设计插件市场 JSON 索引格式（插件列表、分类、版本、兼容性、安装命令）
- 实现远程索引读取（从 GitHub/Gitee 读取 JSON 文件）
- 实现插件市场 Resource：浏览远程插件、按分类筛选、搜索
- 展示插件详情页（说明、版本历史、安装命令、文档链接）
- 提供"复制安装命令"按钮（工具型：`composer require xxx`，模块型：下载 ZIP 步骤）
- 测试：浏览市场、搜索插件、查看详情、复制安装命令

**7. 插件来源标签与品质门槛（5 天）**
- 在插件市场中展示来源标签（官方/推荐/社区）
- 在插件详情页展示品质指标（测试覆盖率、Larastan level、文档完整性）
- 编写插件上架标准文档（参考需求文档"插件开发品质门槛"）
- 创建 Mock 插件索引 JSON，包含不同来源和品质的插件
- 测试：浏览市场、验证标签显示、验证品质指标显示

**8. 商业化预留（3 天）**
- 在 `composer.json` `extra.filament-admin` 中预留 `license` / `price` / `vendor_id` 字段（值为空）
- 在插件市场索引 JSON 中预留 `paid` / `license_required` 字段
- 在插件中心 UI 预留 License Key 输入入口（不做校验逻辑，仅 UI 占位）
- 编写商业化预留说明文档（第二版启动时可直接启用）

**9. 完整测试覆盖（10 天）**
- 插件扫描测试（发现 Composer 包、发现模块、写入数据库）
- 插件启停测试（启用/禁用、Panel 层不注册、Resource 不可见）
- 依赖检查测试（A 依赖 B、禁用 B 时警告、卸载 B 时阻止）
- 远程索引读取测试（JSON 解析、网络失败降级）
- 插件市场 UI 测试（浏览、搜索、筛选、详情页）
- 目标覆盖率：本阶段代码 60%+（UI 测试成本高，适当降低目标）

**10. 文档编写（10 天）**
- `docs/plugins/overview.md`（双轨制架构、工具型 vs 业务模块型、启停机制说明）
- `docs/plugins/using-plugins.md`（如何安装、启用、禁用、配置插件）
- `docs/plugins/develop-package.md`（工具型插件开发完整指南）
- `docs/plugins/develop-module.md`（业务模块型插件开发完整指南，重点说明 `discoverResources` 坑点）
- `docs/architecture/customizations.md` 补充插件机制与 Laravel 原生的差异
- 更新 `README.md` 突出插件系统特色

**11. 阶段收尾（3 天）**
- 代码 Review 自查
- 创建示例插件仓库（用于测试和文档演示）
- 提交阶段 Git Tag：`v0.6.0-插件系统`

#### 验收标准
- [ ] 能扫描已安装插件，在后台查看列表
- [ ] 能启用/禁用插件，禁用后后台不可见该插件功能
- [ ] 能浏览远程插件市场，搜索插件，查看详情
- [ ] 插件依赖检查生效，禁用被依赖插件时显示警告
- [ ] 文档完整，开发者能按文档开发工具型和业务模块型插件
- [ ] 测试覆盖率 ≥ 60%，Larastan/Pint 通过

#### 风险与应对
- **风险**：Panel 层动态注册插件可能遇到 Filament 框架限制
  - **应对**：第 1 周先调研 Filament Plugin 机制，验证动态注册可行性
- **风险**：业务模块型插件的 `discoverResources` 机制复杂，容易踩坑
  - **应对**：在 `ModuleFilamentPlugin` 基类中封装复杂逻辑，模块开发者只需继承即可
- **风险**：依赖检查逻辑复杂（循环依赖、版本冲突等）
  - **应对**：第一版只做简单依赖检查（A 依赖 B），复杂场景延后
- **风险**：2.5 个月时间可能不够（插件系统是最复杂的模块）
  - **应对**：优先做核心功能（扫描、启停、远程市场），品质门槛和商业化预留可压缩

#### 时间分配
- 第 1-2 周：任务 1-2（架构设计 + nwidart 集成）
- 第 3-4 周：任务 3-5（注册表 + 插件中心 + 启停机制）
- 第 5-6 周：任务 6-7（远程市场 + 来源标签）
- 第 7-8 周：任务 9-10（测试 + 文档）
- 第 9-10 周：任务 11（收尾）+ Buffer（应对延期风险）
- 任务 8（商业化预留）穿插在空闲时间

---

### 阶段七：官方插件与收尾（1.5 个月）

**阶段目标**：开发阿里云 OSS 官方插件验证插件 API 易用性，完善架构文档，配置 CI/CD，准备第一版正式发布。

#### 核心任务

**1. 官方插件：阿里云 OSS（10 天）**
- 创建独立仓库 `filament-admin/oss-plugin`
- 基于 `spatie/laravel-package-tools` 搭建插件骨架
- 实现 `Filament\Contracts\Plugin` 接口
- 配置 `OssSettings` 类（AccessKey、Bucket、域名）
- 集成 `aliyuncs/oss-sdk-php`，配置 Medialibrary 使用 OSS 磁盘
- 编写测试（连接测试、上传测试）
- 编写文档（README、配置说明、故障排查）
- 发布到 Packagist

**2. 架构文档完善（8 天）**
- 完善 `docs/architecture/tech-stack.md`（所有集成包的选型理由）
- 完善 `docs/architecture/customizations.md`（所有定制改动清单）
- 完善 `docs/architecture/directory-structure.md`（目录结构逐项说明）
- 完善 `docs/development/base-classes.md`（所有基类与 Trait 说明）
- 完善 `docs/development/conventions.md`（命名规范、开发注意事项）

**3. CHANGELOG 与 UPGRADE 文档（3 天）**
- 编写 `CHANGELOG.md`（遵循 Keep a Changelog 格式）
- 编写 `UPGRADE.md`（第一版无升级，先占位）
- 编写 `CONTRIBUTING.md`（贡献指南）
- 编写 `SECURITY.md`（安全漏洞报告方式）

**4. CI/CD 完善（5 天）**
- 完善 GitHub Actions 工作流（PR 触发、主分支 push 触发）
- 跑 Pest 测试矩阵（PHP 8.3 / 8.4）
- 跑 Larastan level 6 + Pint 格式检查
- 跑依赖安全检查（`composer audit`）
- 配置测试覆盖率报告（Codecov 或 GitHub Actions）

**5. 最终测试与 Bug 修复（10 天）**
- 端到端测试：从安装到配置到使用到安装插件，完整流程跑通
- 修复所有已知 Bug
- 补充遗漏的测试用例，确保整体覆盖率 ≥ 60%
- 测试多环境兼容性（MySQL 5.7/8.0、PHP 8.3/8.4）

**6. README 与快速开始优化（3 天）**
- 重写 `README.md`：项目介绍、特色功能、快速安装、文档索引
- 优化 `docs/guide/quick-start.md`：10 分钟跑起来第一个 CRUD
- 添加截图和 Demo 站链接（Demo 站延后到第二版，先占位）

**7. 第一版发布准备（4 天）**
- 整理 Release Notes（汇总所有功能、已知限制、后续计划）
- 创建 GitHub Release：`v1.0.0`
- 发布到 Packagist（确保 `composer create-project filament-admin/skeleton` 可用）
- 社区宣传（Laravel China、Reddit、Twitter 等）

#### 验收标准
- [ ] 阿里云 OSS 官方插件开发完成并发布到 Packagist
- [ ] 所有架构文档完整，开发者能快速理解项目结构
- [ ] CI/CD 流水线稳定，PR 自动跑测试
- [ ] 整体测试覆盖率 ≥ 60%，Larastan level 6 通过
- [ ] 端到端流程跑通，无阻塞性 Bug
- [ ] README 和快速开始文档质量高，新用户能快速上手
- [ ] 第一版正式发布到 GitHub 和 Packagist

#### 风险与应对
- **风险**：OSS 插件开发可能暴露核心插件 API 问题
  - **应对**：如发现 API 设计缺陷，标记为 Issue 延后到第二版优化（短信/Excel 插件开发时一并优化）
- **风险**：端到端测试可能发现大量 Bug
  - **应对**：优先修复阻塞性 Bug，非阻塞性 Bug 标记为 Issue 延后
- **风险**：测试覆盖率可能不达标
  - **应对**：优先测试核心路径（认证、权限、插件启停），边界场景可延后

#### 时间分配
- 第 1-2 周：任务 1（OSS 插件开发）
- 第 3 周：任务 2（架构文档完善）
- 第 4 周：任务 3-4（CHANGELOG + CI/CD）
- 第 5 周：任务 5（最终测试与 Bug 修复）
- 第 6 周：任务 6-7（README 优化 + 发布准备）

---

## 四、时间线总览

| 阶段 | 时长 | 累计时间 | 核心交付 | Git Tag |
|------|------|---------|---------|---------|
| 一：认证与基础架构 | 1.5个月 | 1.5个月 | 登录、2FA、登录日志、测试、文档 | v0.1.0 |
| 二：权限体系 | 1.5个月 | 3个月 | 角色、权限、Policy、超级管理员、测试、文档 | v0.2.0 |
| 三：菜单与配置 | 1.25个月 | 4.25个月 | 菜单管理、系统配置、插件配置接入、测试、文档 | v0.3.0 |
| 四：日志与审计 | 1个月 | 5.25个月 | 操作日志、日志清理、测试、文档 | v0.4.0 |
| 五：媒体库与 API | 1.5个月 | 6.75个月 | 媒体库、API 响应规范、错误码、测试、文档 | v0.5.0 |
| 六：插件系统 | 2.5个月 | 9.25个月 | 双轨制插件、启停管理、远程市场、测试、文档 | v0.6.0 |
| 七：官方插件与收尾 | 1.5个月 | 10.75个月 | OSS 插件、架构文档、CI/CD、发布 v1.0 | v1.0.0 |
| **总计** | **10.75个月** | — | **第一版完整交付** | **v1.0.0** |

**预计完成时间**：2026-05-28 开始，2027-04 中旬完成第一版

---

## 五、质量目标

### 测试覆盖率

- **整体目标**：≥ 60%
- **核心模块目标**：认证、权限、插件启停 ≥ 70%
- **UI 模块目标**：配置页面、插件市场 ≥ 50%（UI 测试成本高，适当降低）

### 静态分析

- **Larastan**：level 6
- **Pint**：PSR-12 标准，所有代码通过格式检查

### 依赖安全

- 定期跑 `composer audit` 检查 CVE
- CI 中集成依赖安全检查

### 文档完整性

- 所有功能域都有对应文档（features/）
- 开发者指南完整（development/）
- 架构文档完整（architecture/）
- 参考文档完整（reference/）

---

## 六、延后到第二版的功能

以下功能从第一版调整到第二版，以聚焦核心 MVP：

1. **邮件通知系统**（SMTP 配置、邮件模板、测试邮件发送）
   - 延后理由：第二版会做完整通知模块（站内通知 + 邮件通知 + 短信/Webhook 插件），一起实现体验更好
2. **官方插件：短信**（阿里云/腾讯云多驱动）
   - 延后理由：第一版只做一个官方插件（OSS）验证插件 API，短信插件延后到第二版
3. **官方插件：Excel 导入导出**（基于 maatwebsite/excel）
   - 延后理由：同上
4. **插件 API 优化**（根据多个官方插件开发经验优化）
   - 延后理由：第二版开发短信/Excel 插件时会积累更多经验，那时再统一优化插件 API

---

## 七、风险管理

### 高风险项（需要提前验证）

| 风险 | 影响阶段 | 验证时机 | 缓解措施 |
|------|---------|---------|---------|
| Filament 5 的 2FA 插件兼容性问题 | 阶段一 | 第 1 周 | 前 3 天先跑通 Demo，不行就降级到纯 TOTP 方案 |
| Filament Shield 权限 UI 不符合预期 | 阶段二 | 第 2 周 | 先跑 Demo，UI 不满意就自己实现权限分配表单（底层仍用 Spatie）|
| 菜单动态生成与 Filament 静态导航冲突 | 阶段三 | 第 1 周 | 先调研 Filament 的 `NavigationBuilder`，确认能从数据库动态生成 |
| Panel 层动态注册插件遇到框架限制 | 阶段六 | 第 1 周 | 先调研 Filament Plugin 机制，验证动态注册可行性 |

### 中风险项（可能导致延期）

| 风险 | 影响阶段 | 缓解措施 |
|------|---------|---------|
| 个人开发写测试容易拖延 | 所有阶段 | 严格执行"实现完当天必须写测试"，不能拖到阶段末尾 |
| 插件系统时间可能不够（最复杂模块）| 阶段六 | 优先做核心功能，品质门槛和商业化预留可压缩；预留 2 周 Buffer |
| 端到端测试可能发现大量 Bug | 阶段七 | 优先修复阻塞性 Bug，非阻塞性 Bug 标记为 Issue 延后 |

### 低风险项（可降级处理）

| 风险 | 影响阶段 | 降级方案 |
|------|---------|---------|
| JSON diff 可视化很复杂 | 阶段四 | 第一版只做简单展示（raw JSON），第二版再优化 UI |
| 错误码管理变复杂 | 阶段五 | 第一版只做基础注册和冲突检测，复杂场景延后 |
| 依赖检查逻辑复杂 | 阶段六 | 第一版只做简单依赖检查（A 依赖 B），循环依赖等延后 |

---

## 八、成功标准

第一版发布时，需满足以下标准：

### 功能完整性
- [ ] 能通过 `composer create-project filament-admin/skeleton` 快速安装
- [ ] 能创建管理员、分配角色、配置权限、登录后台
- [ ] 能创建菜单、修改系统配置、查看操作日志
- [ ] 能上传文件、通过 API 访问数据
- [ ] 能安装插件、启用/禁用插件、浏览远程插件市场
- [ ] 至少有 1 个官方插件（OSS）可供参考

### 质量达标
- [ ] 整体测试覆盖率 ≥ 60%，核心模块 ≥ 70%
- [ ] Larastan level 6 通过，Pint 格式检查通过
- [ ] CI/CD 流水线稳定，PR 自动跑测试
- [ ] 无阻塞性 Bug，非阻塞性 Bug 已记录为 Issue

### 文档完备
- [ ] 安装文档完整，新用户能快速上手
- [ ] 功能文档完整，覆盖所有功能域
- [ ] 开发者指南完整，能按文档开发插件和业务模块
- [ ] 架构文档完整，开发者能快速理解项目结构

### 社区就绪
- [ ] README 质量高，能吸引开发者关注
- [ ] 发布到 Packagist，能通过 Composer 安装
- [ ] 发布到 GitHub，有完整的 Release Notes
- [ ] 社区宣传完成（Laravel China、Reddit 等）

---

## 九、第二版预览

第二版将在第一版基础上补全以下能力（详见 `doc/需求2.md`）：

### 核心功能
- 插件异步安装 + 在线升级
- 系统升级流水线（Git 部署场景）
- 数据权限体系（行级过滤）
- 通知系统（站内通知 + 邮件通知）
- 定时任务与队列可视化

### 插件生态
- 官方插件：短信（阿里云/腾讯云）
- 官方插件：Excel 导入导出
- 插件 API 优化（根据三个官方插件开发经验）
- 云存储插件（AWS S3、七牛云）

### 商业化闭环（高优先级）
- 开发者入驻流程（GitHub OAuth + 实名认证）
- 付费插件机制（License Key 在线验证）
- 平台抽成结算（5% 抽成，T+15 月结）
- 私有 Packagist 分发

### 其他增强
- 演示环境
- 多语言支持
- 安全增强（文件上传安全、登录安全、操作审计）

**第二版预计周期**：6-8 个月（取决于第一版的用户反馈和商业化启动时机）

---

**文档结束**
