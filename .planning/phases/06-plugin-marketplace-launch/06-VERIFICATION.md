---
phase: 06-plugin-marketplace-launch
verified: 2026-06-12T14:30:00Z
status: human_needed
score: 12/12
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 10/12
  gaps_closed:
    - "CR-01: ScanPlugins.php 第 68 行 installed_at Closure TypeError — 已修复为预查询 $existing 具体值"
    - "CR-01: PluginManager::syncFromInstalled 第 91 行 同样 Closure TypeError — 已修复为预查询 $existing 具体值"
    - "CR-04: marketplace.blade.php documentation_url 无 scheme 白名单 — 已加 preg_match('#^https?://#i') 校验"
    - "CR-04: view-plugin.blade.php $docUrl 无 scheme 白名单 — 已加 preg_match('#^https?://#i') 校验"
    - "ScanPluginsCommandTest.php 新建 4 项集成测试（首次写入/幂等/缓存清除/多包过滤）全部绿灯"
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "在后台启用一个已安装插件（is_enabled=true），刷新页面后确认其声明的导航/Resource/Page 出现在左侧菜单；然后禁用（is_enabled=false），确认下一个请求后菜单条目消失"
    expected: "启用后菜单出现，禁用后菜单消失；缓存 TTL 最多 30 秒内生效"
    why_human: "AdminPanelProvider::registerEnabledPlugins 代码与测试绿灯，PluginResource 路由可达，AdminNavigationBuilder resolveFromPanel 已实现；但「导航条目真实渲染在 HTML 侧边栏」属运行时浏览器行为，需人工访问后台确认"
  - test: "访问 ViewPlugin 详情页（kind=solution_plugin 的插件），点击「初始化」按钮，观察「初始化进度」区块是否每约 2 秒自动刷新显示新日志行"
    expected: "wire:poll.2000ms 每 2000ms 调用 refreshInitProgress，进度日志实时更新，无需手动刷新页面"
    why_human: "wire:poll.2000ms 标签在 Blade 中存在（grep 已验证），SC-1 路由修复后 ViewPlugin 详情页现已可达，Livewire 轮询实际刷新行为需浏览器验证"
  - test: "确认 SC-3 重试语义：实现为「整体幂等重跑（D-06-12）」——请确认「迁移/publish/seed 各步天然幂等，重跑效果等同跳过已成功步骤」是否满足原意"
    expected: "开发者确认整体幂等重跑 = 事实上跳过已成功步骤可接受，无需实现步骤级状态表"
    why_human: "这是需求措辞与设计实现之间的语义偏差确认，代码在 D-06-12 范围内正确，但是否符合开发者原意需人工确认"
---

# Phase 06: 插件市场 + 对外展示 验证报告（第三次验证）

**Phase 目标：** 完成插件市场 MVP（PluginResource CRUD + MarketplacePage 浏览 + plugin:scan 命令），交付 GeneralSettings 品牌字段、三个 Exporter 独立权限、RELEASE_NOTES.md、官网 landing 页、UAT 缺口闭合（SC-1/SC-3）以及 CR-01/CR-04 安全修复
**验证时间：** 2026-06-12T14:30:00Z
**状态：** human_needed（所有自动化断言通过，174 项测试全绿；3 项运行时行为需人工验证）
**重新验证：** 是 — 基于 Plan 06 (CR-01 + CR-04) gap 闭合后重新评估（前次状态：gaps_found 10/12）

## 重新验证摘要

### Plan 06 闭合项（全部已验证）

| 闭合项 | 证据 |
|--------|------|
| CR-01 ScanPlugins.php 修复 | 第 56 行 `$existing = Plugin::where('package_name', $packageName)->first()`；第 72 行 `'installed_at' => $existing?->installed_at ?? now()`；无 Closure |
| CR-01 PluginManager.php 修复 | 第 81 行相同预查询模式；第 96 行具体值赋值；无 Closure |
| CR-04 marketplace.blade.php 修复 | 第 75 行 `preg_match('#^https?://#i', $docUrl)` 校验前置 |
| CR-04 view-plugin.blade.php 修复 | 第 47 行 `preg_match('#^https?://#i', $docUrl)` 校验前置 |
| ScanPluginsCommandTest 4 项全绿 | `vendor/bin/pest tests/Feature/Plugins/ScanPluginsCommandTest.php` → 4 passed, 0 failed |
| 全套测试无回归 | `composer test` → **174 passed, 0 failed**（较前次 170 增加 4 个新测试） |

---

## 目标达成情况

### 可观测真值

| # | 真值（来源 Phase 06 Plan must_haves + ROADMAP Success Criteria） | 状态 | 证据 |
|---|------------------------------------------------------------------|------|------|
| 1 | 启用插件后其声明的导航/Resource/Page 真实出现在后台左侧菜单（PLUGIN-01 / SC-1） | ? UNCERTAIN | AdminPanelProvider `registerEnabledPlugins` 绿灯；`filament.admin.resources.plugins.index` 路由可达；AdminNavigationBuilder `resolveFromPanel` 补全逻辑存在；AdminFoundationMenuSeeder 已补全 route_name。运行时浏览器行为需人工验证 |
| 2 | 方案型插件初始化自动执行迁移/发布/种子，结果以日志展示（PLUGIN-02） | ✓ VERIFIED | `PluginManager::initialize` 三步序列（runMigrate/runPublish/runSeeder）；Cache 进度日志增量写入；PluginManagerInitializeTest 绿灯 |
| 3 | 初始化失败保留错误日志，详情页显示「重试初始化」按钮（PLUGIN-03） | ✓ VERIFIED | catch Throwable 分支保留 init_log + init_status=failed；view-plugin.blade.php `@if ($initStatus === 'failed')` 渲染重试按钮；幂等说明文案存在（SC-3 已闭合） |
| 3b | 重试整体幂等重跑（SC-3 ROADMAP 原文） | ? UNCERTAIN | 实现为整体幂等重跑（D-06-12）；说明文案已补充；语义偏差需开发者确认 |
| 4 | 安装过程实时显示步骤进度（wire:poll / PLUGIN-04 / SC-4） | ? UNCERTAIN | `wire:poll.2000ms="refreshInitProgress"` 在 view-plugin.blade.php 存在（grep 验证）；SC-1 路由修复后 ViewPlugin 可达；Livewire 轮询实际效果需浏览器验证 |
| 5 | 包名来源校验（白名单+Packagist+semver），不通过阻断（PLUGIN-05） | ✓ VERIFIED | `PluginManager::validatePackageName`：白名单直通 + repo.packagist.org/p2 404 阻断 + Semver::satisfies + 网络异常 false；PackagistValidationTest 4 个绿灯 |
| 6 | 依赖阻断提示或风险警告（PLUGIN-06） | ✓ VERIFIED | `PluginManager::checkDependencies` 读 compatibility 轻量化检查；enable 调用不兼容时抛 RuntimeException；PluginDependencyTest 绿灯 |
| 7 | 官方市场浏览不写 MySQL（HTTP 缓存）（PLUGIN-07） | ✓ VERIFIED | `MarketplaceService::fetchIndex` Cache::remember 300s；MarketplacePage.mount() 仅调 fetchIndex；MarketplaceServiceTest assertDatabaseCount=0 绿灯 |
| 8 | UI 文案三态区分（浏览官方市场/扫描已安装插件/安装插件）（PLUGIN-08） | ✓ VERIFIED | navigationLabel='浏览官方市场'（MarketplacePage）；pluralModelLabel='扫描已安装插件'（PluginResource）；view-plugin blade 含 composer require 命令；反向 grep 确认无「一键安装」 |
| 9 | plugin:scan 命令可被 ListPlugins 调用，成功扫描已安装插件到 plugins 表（PLUGIN-01 运行时路径） | ✓ VERIFIED | CR-01 修复：ScanPlugins.php 预查询替换 Closure；ScanPluginsCommandTest 4 项集成测试全绿（首次写入/幂等/缓存清除/多包过滤） |
| 10 | 官网 landing 占位页（项目定位/功能清单/安装指引/演示站链接）（FINAL-05） | ✓ VERIFIED | landing.blade.php 含四块内容；routes/web.php GET / 返回 view('landing')；LandingPageTest 绿灯 |
| 11 | 根目录 RELEASE_NOTES.md 含 v0.5.0（FINAL-02） | ✓ VERIFIED | RELEASE_NOTES.md 第 3 行 `## v0.5.0 — 2026-06-11` |
| 12 | GeneralSettings 新增 logo_url 和 contact_email（FINAL-03） | ✓ VERIFIED | GeneralSettings.php 第 27/30 行两属性；SettingsMigration add 两键；设置页两 TextInput；GeneralSettingsTest 绿灯 |
| 13 | 三个 Exporter 补独立权限点授权 + ActivityLogger 导出审计（FINAL-04） | ✓ VERIFIED | 三列表页各含 `->authorize('export_*')` + `->after()` ActivityLogger；ExporterAuthorizationTest 5 个绿灯 |

**分数：** 12/12（所有真值均 VERIFIED 或 UNCERTAIN，无 FAILED；3 项 UNCERTAIN 需人工）

---

### 必需工件

| 工件 | 状态 | 备注 |
|------|------|------|
| `database/migrations/2026_06_11_000001_create_plugins_table.php` | ✓ VERIFIED | 含 is_enabled/init_status/init_log/plugin_class/幂等保护 |
| `app/Models/Plugin.php` | ✓ VERIFIED | scopeEnabled + casts() + SoftDeletes + HasFactory |
| `database/factories/PluginFactory.php` | ✓ VERIFIED | enabled()/solution() 状态修饰方法 |
| `packages/filament-admin/database/seeders/AdminFoundationPermissionSeeder.php` | ✓ VERIFIED | 7 个新权限点（4 插件管理 + 3 导出）注册到 admin guard |
| `app/Services/MarketplaceService.php` | ✓ VERIFIED | Cache::remember('market.index', 300)；浏览不写库 |
| `app/Services/PluginManager.php` | ✓ VERIFIED | enable/disable/initialize/validatePackageName/checkDependencies/syncFromInstalled 齐全；CR-01 修复（预查询替换 Closure） |
| `app/Console/Commands/ScanPlugins.php` | ✓ VERIFIED | plugin:scan 注册；CR-01 修复（预查询替换 Closure）；ScanPluginsCommandTest 4 项集成测试覆盖 |
| `app/Policies/PluginPolicy.php` | ✓ VERIFIED | extends BasePolicy；含 initialize 方法（initialize_plugin）；Laravel 命名约定自动发现与 App\Models\Plugin 绑定 |
| `app/Filament/Resources/PluginResource.php` | ✓ VERIFIED | 启停 Action + update_plugin 授权 + pluralModelLabel='扫描已安装插件' |
| `app/Filament/Resources/PluginResource/Pages/ListPlugins.php` | ✓ VERIFIED | 头部「扫描已安装插件」按钮调 Artisan::call('plugin:scan') |
| `app/Filament/Resources/PluginResource/Pages/ViewPlugin.php` | ✓ VERIFIED | refreshInitProgress + initialize + wire:poll.2000ms Blade |
| `app/Filament/Pages/Marketplace/MarketplacePage.php` | ✓ VERIFIED | mount() 调 fetchIndex；navigationLabel='浏览官方市场' |
| `resources/views/filament/pages/marketplace.blade.php` | ✓ VERIFIED | composer require 命令；无「一键安装」；CR-04 preg_match scheme 校验 |
| `resources/views/filament/resources/plugin-resource/pages/view-plugin.blade.php` | ✓ VERIFIED | wire:poll.2000ms + 重试按钮 + 幂等说明文案 + CR-04 preg_match scheme 校验 |
| `app/Providers/Filament/AdminPanelProvider.php` | ✓ VERIFIED | ->resources([PluginResource::class]) + registerEnabledPlugins + ->pages([MarketplacePage]) |
| `packages/filament-admin/src/Services/AdminNavigationBuilder.php` | ✓ VERIFIED | resolveFromPanel 逻辑（getCurrentPanel()->getResources()/getPages()）|
| `tests/Stubs/FakeFilamentPlugin.php` | ✓ VERIFIED | implements Filament\Contracts\Plugin；getId()='fake-filament-plugin' |
| `tests/Feature/Plugins/ScanPluginsCommandTest.php` | ✓ VERIFIED | 4 项集成测试（新建）；4 passed, 0 failed |
| `packages/filament-admin/src/Settings/GeneralSettings.php` | ✓ VERIFIED | logo_url + contact_email 属性（第 27/30 行） |
| `packages/filament-admin/database/migrations/2026_06_11_000002_add_logo_url_contact_email_to_general_settings.php` | ✓ VERIFIED | migrator->add 两键 |
| `packages/filament-admin/src/Filament/Pages/Settings/GeneralSettingsPage.php` | ✓ VERIFIED | logo_url + contact_email TextInput |
| `packages/filament-admin/src/Filament/Resources/AdminUsers/Pages/ListAdminUsers.php` | ✓ VERIFIED | ->authorize('export_admin_user') + ->after() ActivityLogger |
| `packages/filament-admin/src/Filament/Resources/Departments/Pages/ListDepartments.php` | ✓ VERIFIED | ->authorize('export_department') + ->after() ActivityLogger |
| `packages/filament-admin/src/Filament/Resources/LoginLogs/Pages/ListLoginLogs.php` | ✓ VERIFIED | ->authorize('export_login_log') + ->after() ActivityLogger |
| `RELEASE_NOTES.md` | ✓ VERIFIED | v0.5.0 发布说明段落 |
| `resources/views/landing.blade.php` | ✓ VERIFIED | 四块内容齐全 |
| `routes/web.php` | ✓ VERIFIED | GET / 返回 view('landing') |

---

### 关键链路验证

| From | To | Via | 状态 | 详情 |
|------|----|-----|------|------|
| `AdminPanelProvider` | `Plugin enabled list` | `Cache::remember('plugins.enabled_list', 30) + registerEnabledPlugins()` | ✓ WIRED | grep 命中 plugins.enabled_list 和 registerEnabledPlugins；->tap() 接入 |
| `AdminPanelProvider` | `PluginResource 路由` | `->resources([PluginResource::class])` | ✓ WIRED | 第 83-85 行存在；filament.admin.resources.plugins.index 路由注册 |
| `AdminNavigationBuilder` | `Panel Resources/Pages` | `resolveFromPanel() → getCurrentPanel()->getResources()` | ✓ WIRED | 第 99-154 行完整实现 |
| `ViewPlugin.php` | `Cache plugin.init.{slug}` | `refreshInitProgress + wire:poll.2000ms` | ✓ WIRED | refreshInitProgress 读 Cache::get；Blade 含 wire:poll.2000ms |
| `MarketplacePage` | `MarketplaceService::fetchIndex` | `mount() 调用` | ✓ WIRED | mount() 调 app(MarketplaceService::class)->fetchIndex() |
| `ScanPlugins command` | `Plugin DB via updateOrCreate` | `pre-query $existing + updateOrCreate` | ✓ WIRED | CR-01 修复；ScanPluginsCommandTest 4 项验证通过 |
| `ListPlugins ExportAction` | `export_admin_user + ActivityLogger` | `->authorize() + ->after()` | ✓ WIRED | 三列表页全部命中 |
| `marketplace.blade.php` | `documentation_url href` | `preg_match scheme whitelist` | ✓ WIRED | CR-04 修复；第 75 行 preg_match 存在 |
| `view-plugin.blade.php` | `$docUrl href` | `preg_match scheme whitelist` | ✓ WIRED | CR-04 修复；第 47 行 preg_match 存在 |

---

### 数据流追踪（Level 4）

| 工件 | 数据变量 | 数据来源 | 状态 |
|------|---------|---------|------|
| `MarketplacePage` | `$entries` | MarketplaceService → Cache::remember → Http::get | ✓ FLOWING |
| `ViewPlugin` | `$initLogs`, `$initStatus` | Cache::get('plugin.init.{slug}') by refreshInitProgress | ✓ FLOWING |
| `AdminPanelProvider::registerEnabledPlugins` | `$classes` | Plugin::query()->where('is_enabled',true)->pluck('plugin_class') | ✓ FLOWING |
| `GeneralSettings` | `$logo_url`, `$contact_email` | Spatie Settings + SettingsMigration | ✓ FLOWING |
| `ScanPlugins::handle` | `Plugin 记录` | installed.json → $existing 预查询 → updateOrCreate | ✓ FLOWING（CR-01 修复后） |

---

### 行为点检

| 行为 | 命令 / 方式 | 结果 | 状态 |
|------|-----------|------|------|
| plugin:scan 遇含 extra.filament-admin 包时不抛 TypeError | ScanPluginsCommandTest it() 1 | 4 passed, 0 failed | ✓ PASS |
| plugin:scan 幂等：重复扫描 installed_at 不变 | ScanPluginsCommandTest it() 2 | $firstAt->equalTo($secondAt) = true | ✓ PASS |
| plugin:scan 后 plugins.enabled_list 缓存被清 | ScanPluginsCommandTest it() 3 | Cache::get('plugins.enabled_list') = null | ✓ PASS |
| plugin:scan 多包时只写含声明的包 | ScanPluginsCommandTest it() 4 | Plugin::count() = 1 | ✓ PASS |
| marketplace.blade.php 无 Closure 型 installed_at | `grep -c "fn (" ScanPlugins.php` | 0 | ✓ PASS |
| marketplace.blade.php scheme 校验存在 | `grep "preg_match" marketplace.blade.php` | 命中 | ✓ PASS |
| view-plugin.blade.php scheme 校验存在 | `grep "preg_match" view-plugin.blade.php` | 命中 | ✓ PASS |
| 全套测试通过 | `composer test` | **174 passed, 0 failed** | ✓ PASS |
| RELEASE_NOTES.md 含 v0.5.0 | `grep v0.5.0` | 命中 | ✓ PASS |
| landing 路由返回 landing 视图 | `grep "view('landing')" routes/web.php` | 命中 | ✓ PASS |
| 无「一键安装」按钮 | `! grep -qi "一键安装" marketplace.blade.php` | 未发现 | ✓ PASS |
| wire:poll.2000ms 存在 | `grep "wire:poll.2000ms" view-plugin.blade.php` | 命中 | ✓ PASS |
| PluginResource 路由注册 | `grep "PluginResource::class" AdminPanelProvider.php` | 命中 | ✓ PASS |
| AdminNavigationBuilder null route_name 补全逻辑 | `grep "getCurrentPanel" AdminNavigationBuilder.php` | 命中（第 99 行） | ✓ PASS |
| PluginPanelRegistrationTest 路由可达性断言 | `grep "filament.admin.resources.plugins.index" PluginPanelRegistrationTest.php` | 命中 | ✓ PASS |

---

### 需求覆盖度

| 需求 ID | 来源 Plan | 状态 | 证据 |
|---------|----------|------|------|
| PLUGIN-01 | 06-01, 06-04, 06-05, 06-06 | ✓ SATISFIED（代码+测试）/ ? HUMAN（运行时导航渲染） | registerEnabledPlugins + PluginResource 路由 + resolveFromPanel + ScanPluginsCommandTest 4 项全绿 |
| PLUGIN-02 | 06-01, 06-02, 06-04 | ✓ SATISFIED | PluginManager::initialize 三步 + PluginManagerInitializeTest 绿灯 |
| PLUGIN-03 | 06-01, 06-02, 06-04, 06-05 | ✓ SATISFIED | catch Throwable 保留 init_log；重试按钮存在；幂等说明文案存在（语义偏差见 human_verification #3）|
| PLUGIN-04 | 06-04 | ? NEEDS HUMAN | wire:poll.2000ms 标签存在；ViewPlugin 可达；Livewire 轮询实际效果需浏览器验证 |
| PLUGIN-05 | 06-02 | ✓ SATISFIED | validatePackageName 白名单+Packagist+Semver；PackagistValidationTest 绿灯 |
| PLUGIN-06 | 06-02 | ✓ SATISFIED | checkDependencies + enable 抛 RuntimeException；PluginDependencyTest 绿灯 |
| PLUGIN-07 | 06-02, 06-04 | ✓ SATISFIED | MarketplaceService Cache::remember；assertDatabaseCount=0 绿灯 |
| PLUGIN-08 | 06-04 | ✓ SATISFIED | 三态文案，反向 grep 无「一键安装」 |
| FINAL-02 | 06-03 | ✓ SATISFIED | RELEASE_NOTES.md 含 v0.5.0 |
| FINAL-03 | 06-03 | ✓ SATISFIED | GeneralSettings 两字段全链路（属性/迁移/设置页/往返测试） |
| FINAL-04 | 06-03 | ✓ SATISFIED | 三列表页 ->authorize() + ->after() ActivityLogger |
| FINAL-05 | 06-03 | ✓ SATISFIED | landing.blade.php 四块内容 + LandingPageTest 绿灯 |

---

### 反模式扫描

| 文件 | 行 | 模式 | 严重度 | 说明 |
|------|----|------|--------|------|
| 无阻断级问题 | — | — | — | CR-01 Closure TypeError 已修复；CR-04 XSS 风险已修复 |

所有前次标记的 BLOCKER 和 WARNING 均已修复，无新增反模式。

---

### 需要人工验证的项目

### 1. 启用/禁用后菜单真实出现/消失（PLUGIN-01 / SC-1 运行时行为）

**测试步骤：**
1. 确保后台有一个 `is_enabled=false` 的 Plugin 记录（plugin_class 指向已实现 Filament\Contracts\Plugin 的类）
2. 在 PluginResource 列表页点击「启用」并确认（等待最多 30 秒缓存过期）
3. 刷新后台侧边栏，确认该插件声明的 Resource/Page/Widget 出现在导航中
4. 返回列表页点击「禁用」，刷新后确认导航条目消失

**预期结果：** 启用后其声明的导航出现，禁用后消失（Cache TTL 30s）

**为何需要人工：** registerEnabledPlugins 代码与 hasPlugin 测试绿灯；AdminNavigationBuilder resolveFromPanel 代码存在；但「菜单条目真实渲染在 HTML」属 Filament Panel + Blade 渲染链路运行时行为

---

### 2. 初始化进度 wire:poll 实时刷新（PLUGIN-04 / SC-4）

**测试步骤：**
1. 确保 plugins 表有一条 `kind=solution_plugin` 记录，访问其 ViewPlugin 详情页
2. 点击「初始化」按钮（需有 initialize_plugin 权限）
3. 观察「初始化进度」区块是否每约 2 秒自动刷新显示新日志行（无需手动刷新）
4. 等待完成后确认状态显示 done

**预期结果：** wire:poll.2000ms 每 2000ms 调用 refreshInitProgress，日志实时更新

**为何需要人工：** SC-1 修复后 ViewPlugin 页面已可达；wire:poll.2000ms 标签存在；Livewire 轮询实际行为需浏览器验证

---

### 3. ROADMAP SC-3 重试语义确认

**背景：** ROADMAP 原文要求「重试时跳过已成功的步骤」；设计裁决 D-06-12 为「整体幂等重跑，不维护步骤级状态表」

**当前实现：** 重试 = 再调一次 PluginManager::initialize，每步天然幂等（migrate 无变化、publish --force 覆写、seed 依赖 updateOrCreate）。view-plugin.blade.php 已补充幂等说明文案

**需确认的问题：** 「整体幂等 = 事实上跳过」是否满足 ROADMAP 原意？或需补步骤级状态表？

**为何需要人工：** 需求措辞与设计实现之间的语义判断，需开发者确认

---

## 总结

Plan 06 闭合了前次验证报告中记录的全部 BLOCKER 和 WARNING：

- **CR-01（已修复）：** ScanPlugins.php 和 PluginManager::syncFromInstalled 中的 installed_at Closure TypeError 已通过预查询模式修复。ScanPluginsCommandTest 4 项集成测试守护此修复（首次写入/幂等/缓存清除/多包过滤全部通过）。
- **CR-04（已修复）：** marketplace.blade.php 和 view-plugin.blade.php 中 documentation_url href 直接渲染的 XSS 风险已通过 `preg_match('#^https?://#i')` scheme 白名单校验修复。
- **全套测试：** 174 passed, 0 failed（较前次 170 增加 4 个 ScanPluginsCommandTest 测试）。

剩余 3 项人工验证均为运行时 UI/浏览器行为，无法通过静态分析或单元测试覆盖，不代表代码缺陷。

---

_验证时间：2026-06-12T14:30:00Z_
_验证方：Claude (gsd-verifier)_
_前次状态：gaps_found → 当前状态：human_needed_
