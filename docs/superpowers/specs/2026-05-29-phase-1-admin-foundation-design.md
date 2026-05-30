# 一期后台基础管理完整包设计

> 更新时间：2026-05-29  
> 对应规划：`doc/项目开发规划.md` 的“一期：后台基础管理”  
> 设计状态：已审核通过  
> 目标：把后台自身治理能力做成完整闭环，做到账号、角色、菜单、数据范围和操作审计都能在后台真实使用。

---

## 一、设计结论

一期采用“后台基础管理整包闭环”方案，不再把管理员、权限、菜单、审计、部门和数据权限拆散到多个远期阶段。交付后，后台应具备完整自管理能力：

- 谁能登录：管理员账号、启用禁用、软删除恢复、密码重置。
- 谁能做什么：角色、权限点、Policy、超级管理员绕过。
- 谁能看什么菜单：菜单规则、权限绑定、动态导航、拖拽排序。
- 谁能看哪些数据：部门组织、角色数据范围、查询作用域。
- 谁做过什么：登录日志和操作日志审计。

一期内部按小阶段推进，但最终作为一个可发布功能块验收。

## 二、交付范围

### 2.1 管理员管理

管理员管理负责后台账号的完整生命周期。

必须包含：

- `AdminUserResource`：列表、创建、编辑、删除、恢复、详情。
- 账号状态：新增 `status`，至少支持 `active` 和 `disabled`。
- 登录限制：`disabled` 管理员不能访问后台；软删除管理员不能正常使用。
- 密码重置：受权限控制，不展示原密码。
- 角色分配：在管理员表单中分配 Spatie 角色，guard 固定为 `admin`。
- 部门归属：管理员可绑定一个主部门，用于数据权限计算。
- 权限点：覆盖 view、create、update、delete、restore、force_delete、reset_password、assign_role。

不做：

- 管理员头像上传。头像依赖媒体库，放到媒体库那一期接入。
- 多部门任职。当前仅支持一个主部门，多部门场景后续按真实业务再扩展。

### 2.2 管理员日志

管理员日志基于已有 `login_logs`，一期补齐后台查看能力。

必须包含：

- `LoginLogResource`：只读列表、详情。
- 筛选：管理员、登录结果、IP、时间范围。
- 搜索：用户名、IP、User-Agent。
- 权限：查看列表、查看详情；默认不提供删除按钮。
- 清理命令：允许按天数清理旧日志，默认保守，不自动删除。

不做：

- 登录日志写入机制重写。已有监听器继续复用。
- 登录日志变更审计。登录日志本身是审计数据，不再对查看行为做二次审计。

### 2.3 角色与权限

角色管理继续使用 Filament Shield 自带 `RoleResource`，项目不自写 RoleResource。

必须包含：

- Shield RoleResource 正常显示在后台基础管理菜单组。
- 权限点命名与 `BasePolicy` 保持一致：`view_any_admin_user`、`update_admin_user` 等。
- 为新增 Resource 生成权限点：管理员、登录日志、菜单、部门、操作日志。
- 超级管理员继续使用 `super_admin` + `Gate::before()`。
- 普通管理员按角色权限访问后台功能。
- `AdminUserResource` 内支持角色分配。

不做：

- 自研角色管理页面。
- 行级数据权限塞进 Shield RoleResource 表单。数据权限独立页面管理，避免侵入 Shield 自带资源。

### 2.4 菜单规则与动态导航

菜单规则真实接管后台左侧导航，不只做菜单表。

必须包含：

- `Menu` 模型和 `MenuResource`。
- 菜单树：父子级、排序、图标、名称、启用状态。
- 指向方式：优先支持 Filament 路由名称；外链或普通 URL 作为补充。
- 权限绑定：菜单可绑定 `admin` guard 下的权限名。
- 动态导航：左侧导航根据启用菜单、排序和当前管理员权限生成。
- 拖拽排序：提供可视化拖拽排序，至少支持同级排序；跨层级拖动如实现复杂，可用明确的移动父级操作配合排序。
- 无效路由保护：菜单指向的路由不存在时，不让后台崩溃；菜单 Resource 尽量校验，运行时跳过无效项。

不做：

- 插件菜单自动注册。这里只预留菜单来源字段或设计口径，插件中心那一期再接入。
- 菜单多语言。近期只保留文案规范。

### 2.5 部门组织架构

部门是数据权限的基础，不单独做复杂 HR 系统。

必须包含：

- `Department` 模型和 `DepartmentResource`。
- 部门树：父子级、名称、编码、排序、启用状态。
- 部门负责人：可选绑定一个管理员作为负责人。
- 管理员主部门：`admin_users.department_id`。
- 部门禁用：禁用部门不影响历史数据，但不能作为新管理员可选部门。
- 树形查询服务：提供获取下级部门 ID 的方法，供数据权限使用。

不做：

- 岗位、职级、组织人员编制。
- 多组织、多租户。

### 2.6 数据权限

数据权限与角色绑定，但不侵入 Shield RoleResource。使用独立数据权限配置管理角色的数据范围。

必须包含：

- 数据范围类型：
  - `all`：全部数据。
  - `department`：本部门数据。
  - `department_and_children`：本部门及下级部门数据。
  - `self`：仅本人数据。
  - `custom_departments`：指定部门数据。
- `RoleDataScope` 配置：为 Spatie 角色配置数据范围。
- 自定义部门范围：支持为角色选择多个部门。
- 多角色合并：如果任一角色是 `all`，则拥有全部数据；否则合并多个角色允许的数据范围。
- 超级管理员：不受数据权限限制。
- 应用范围：一期至少应用到 `AdminUserResource` 和 `LoginLogResource`，并提供可复用服务给后续业务 Resource。

不做：

- 字段级权限。
- 数据脱敏。
- 复杂表达式权限，例如“某区域 + 某业务线 + 某金额范围”。

### 2.7 操作日志审计

操作日志用于记录关键后台行为，补齐“谁做过什么”。

必须包含：

- 安装并接入 `spatie/laravel-activitylog` 4.x 与 `alizharb/filament-activity-log`。
- 优先复用插件提供的 `ActivityLogResource`，并通过项目配置收口为只读列表、详情、筛选。
- 记录对象：
  - 管理员创建、修改、禁用、删除、恢复、密码重置、角色变更。
  - 角色权限变更。
  - 菜单创建、修改、排序、启用禁用、删除。
  - 部门创建、修改、排序、启用禁用、删除。
  - 数据权限配置变更。
- 展示内容：操作人、动作、对象、时间、IP、变更前后数据。
- 清理命令：支持按保留天数清理普通操作日志。

不做：

- 审计日志不可篡改存储。当前只做基础审计，不做合规级防篡改。
- 操作日志通知。通知系统后续单独规划。

## 三、数据模型设计

### 3.1 `admin_users` 调整

新增字段：

- `status`：字符串或枚举，默认 `active`。
- `department_id`：可空外键，关联 `departments.id`。

约束：

- `status = disabled` 时不能访问 Filament 面板。
- 软删除管理员不进入正常选择项。
- `department_id` 可空，便于初始化超级管理员；但数据权限计算时，无部门普通管理员只能看到本人数据。

### 3.2 `menus`

建议字段：

- `id`
- `parent_id`
- `title`
- `icon`
- `route_name`
- `url`
- `permission_name`
- `sort`
- `is_active`
- `target`
- `source`
- `created_at`
- `updated_at`
- `deleted_at`

规则：

- `route_name` 和 `url` 至少填一个。
- `permission_name` 为空表示只受登录限制；不为空时必须检查当前管理员是否拥有该权限。
- `source` 默认 `core`，后续插件可使用 `plugin:<name>`。
- 同一父级下按 `sort` 升序展示。

### 3.3 `departments`

建议字段：

- `id`
- `parent_id`
- `name`
- `code`
- `leader_admin_user_id`
- `sort`
- `is_active`
- `created_at`
- `updated_at`
- `deleted_at`

规则：

- `code` 唯一，用于后续导入导出或外部同步。
- 部门树先使用邻接表模型，不引入复杂嵌套集合。
- 下级部门 ID 由服务类递归计算并缓存到请求级别，不做长期缓存。

### 3.4 `role_data_scopes`

建议字段：

- `id`
- `role_id`
- `scope`
- `department_ids`
- `created_at`
- `updated_at`

规则：

- `role_id` 关联 Spatie `roles.id`。
- `scope` 取值为 `all`、`department`、`department_and_children`、`self`、`custom_departments`。
- `department_ids` 只在 `custom_departments` 时使用，使用 JSON 存储。
- 未配置数据范围的普通角色默认按 `self` 处理。

### 3.5 `activity_log`

使用 `spatie/laravel-activitylog` 4.x 默认表结构，后台展示复用 `alizharb/filament-activity-log` 的资源页；必要时通过 `properties` 存储：

- `ip`
- `user_agent`
- `before`
- `after`
- `resource`
- `action`

## 四、核心服务与职责

### 4.1 `AdminNavigationBuilder`

职责：

- 读取启用菜单。
- 按父子级和排序生成导航树。
- 根据当前管理员权限过滤菜单。
- 将菜单转换为 Filament 导航项。
- 跳过无效路由，避免后台崩溃。

使用位置：

- `AdminPanelProvider` 的导航配置中调用。

### 4.2 `DepartmentTree`

职责：

- 获取部门下级 ID。
- 获取部门及下级 ID。
- 校验父子级关系，避免形成循环。
- 给数据权限服务提供部门集合。

### 4.3 `DataScopeResolver`

职责：

- 根据当前管理员角色计算最终数据范围。
- 合并多角色范围。
- 识别超级管理员并返回全部数据。
- 为 Resource 查询提供可复用的过滤条件。

合并规则：

- 任一角色为 `all`，最终为全部数据。
- `department_and_children` 包含本部门和所有下级部门。
- `department` 只包含当前管理员主部门。
- `custom_departments` 合并所有指定部门。
- `self` 只包含当前管理员本人创建或本人关联的数据。

### 4.4 `ActivityLogger`

职责：

- 封装关键操作日志写入。
- 资源展示优先复用插件，不在项目里重复手写一套 ActivityLog Resource。
- 统一记录 IP、User-Agent、操作人、操作对象和变更前后数据。
- 避免 Resource 中散落大量日志拼装代码。

## 五、后台菜单结构

一期完成后，建议后台左侧出现一个“基础管理”或“常规管理”菜单组：

- 管理员管理
- 管理员日志
- 角色管理
- 菜单规则
- 部门管理
- 数据权限
- 操作日志

命名最终可在实现时统一，但一组功能必须放在同一个基础管理区域中，不要分散到多个无关菜单组。

## 六、权限点设计

一期新增或确认以下权限点：

- `view_any_admin_user`
- `view_admin_user`
- `create_admin_user`
- `update_admin_user`
- `delete_admin_user`
- `restore_admin_user`
- `force_delete_admin_user`
- `reset_password_admin_user`
- `assign_role_admin_user`
- `view_any_login_log`
- `view_login_log`
- `view_any_menu`
- `view_menu`
- `create_menu`
- `update_menu`
- `delete_menu`
- `restore_menu`
- `reorder_menu`
- `view_any_department`
- `view_department`
- `create_department`
- `update_department`
- `delete_department`
- `restore_department`
- `reorder_department`
- `view_any_role_data_scope`
- `view_role_data_scope`
- `update_role_data_scope`
- `view_any_activity_log`
- `view_activity_log`

权限生成仍以 Shield 为主；自定义动作权限需要在权限生成或 Seeder 中补齐。

## 七、测试策略

一期测试重点是业务规则、权限边界和导航结果，不追求每个 UI 像素。

必须覆盖：

- `active` 管理员可访问后台，`disabled` 管理员不能访问后台。
- 管理员可创建、编辑、禁用、删除、恢复、重置密码、分配角色。
- 登录日志可查看、可筛选、不可普通删除。
- 超级管理员绕过功能权限和数据权限。
- 普通角色只能访问被授权 Resource。
- 菜单启用时显示，禁用时隐藏。
- 菜单绑定权限后，无权限用户看不到，有权限用户看得到。
- 菜单拖拽排序后，同级菜单顺序正确。
- 部门树能创建、排序、禁用，并能计算下级部门。
- 数据权限能过滤管理员列表和登录日志列表。
- 操作日志能记录管理员、角色、菜单、部门、数据权限的关键变更。
- 现有登录、2FA、登录日志写入、权限基础测试继续通过。

## 八、交付门槛

一期完成必须满足：

- 后台有完整基础管理菜单组。
- 管理员、登录日志、角色、菜单、部门、数据权限、操作日志都可在后台使用。
- 动态菜单真实影响左侧导航。
- 禁用账号不能访问后台。
- 普通角色受功能权限和数据权限限制。
- 超级管理员能绕过功能权限和数据权限，但不能绕过账号禁用和软删除。
- 关键操作写入操作日志。
- `composer test`、`composer phpstan`、`composer pint:test` 通过；无法运行时必须记录原因。
- 更新 `doc/项目开发规划.md`、一期功能文档和必要开发规范。

## 九、风险与处理

### 9.1 菜单接管导航可能受 Filament API 限制

处理方式：实现前先用最小实验验证 Filament 5 的导航构建 API。如果无法完全替换导航，就采用“菜单表生成导航项 + Resource 路由仍由 Filament 管理”的方式，不改变路由注册机制。

### 9.2 Shield RoleResource 不适合承载数据权限配置

处理方式：数据权限使用独立 `RoleDataScopeResource` 或管理页面，避免改 Shield 源码或自写 RoleResource。

### 9.3 数据权限容易过度设计

处理方式：一期只做角色级数据范围，不做字段权限、表达式权限、多组织和多部门任职。

### 9.4 操作日志范围扩大后任务量增加

处理方式：先记录核心后台治理对象，不把所有后续业务模型强行纳入一期。

## 十、自查结果

- 没有未决占位项。
- 一期边界已经包含用户确认必须进入一期的菜单拖拽排序、操作日志审计、部门权限和数据权限。
- 角色管理继续使用 Shield 自带 Resource，不和项目“不自写 RoleResource”的约定冲突。
- 数据权限独立于 Shield RoleResource，避免侵入第三方包。
- 菜单接管导航明确只接管显示，不改变 Filament Resource 路由注册机制。
