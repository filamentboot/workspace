---
phase: 06-plugin-marketplace-launch
fixed_at: 2026-06-12T05:30:00Z
review_path: .planning/phases/06-plugin-marketplace-launch/06-REVIEW.md
iteration: 1
findings_in_scope: 9
fixed: 9
skipped: 0
status: all_fixed
---

# Phase 06: Code Review Fix Report

**Fixed at:** 2026-06-12T05:30:00Z
**Source review:** .planning/phases/06-plugin-marketplace-launch/06-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 9（3 Critical + 6 Warning）
- Fixed: 9
- Skipped: 0

## Fixed Issues

### CR-01: MarketplaceService::fetchIndex HTTP 错误不触发兜底

**Files modified:** `app/Services/MarketplaceService.php`
**Commit:** `2ffa94e`
**Applied fix:** 将 `try/catch` 包裹到 `Cache::remember` 外层，并在 HTTP 调用链中加入 `->throw()`，使 4xx/5xx 响应进入 catch 分支；兜底结果不写入缓存，避免故障期间空结果污染 300 秒缓存。

---

### CR-02: ViewPlugin::initialize() Livewire 方法无授权守卫

**Files modified:** `app/Filament/Resources/PluginResource/Pages/ViewPlugin.php`
**Commit:** `ab13b78`
**Applied fix:** 在 `initialize()` 方法头部添加 `$this->authorize('initialize', $this->record)`，确保 `wire:click` 直接触发的 Livewire 路径同样受 `PluginPolicy::initialize()` 约束，防止绕过 Filament Action 的权限检查。
**注意：** 此修复属逻辑授权，建议人工验证 403 响应行为正确。

---

### CR-03: PluginPolicy 未在任何显式映射中注册

**Files modified:** `app/Providers/AppServiceProvider.php`
**Commit:** `e2d3f1e`
**Applied fix:** 在 `AppServiceProvider::boot()` 中通过 `Gate::policy(Plugin::class, PluginPolicy::class)` 显式注册，导入 `App\Models\Plugin`、`App\Policies\PluginPolicy`、`Illuminate\Support\Facades\Gate`，与项目全局显式注册风格保持一致。

---

### WR-01: PluginManager::syncFromInstalled 与 ScanPlugins::handle 重复实现

**Files modified:** `app/Console/Commands/ScanPlugins.php`
**Commit:** `989b283`
**Applied fix:** 删除命令内联的全部扫描逻辑（~50 行），改为通过方法注入接收 `PluginManager` 并调用 `syncFromInstalled()`。命令仅保留 CLI 层的文件存在判断和输出，业务逻辑唯一入口在 Service。

---

### WR-02: runPublish 误用 Filament Plugin 类作 --provider 参数

**Files modified:** `app/Services/PluginManager.php`、`app/Models/Plugin.php`、`database/migrations/2026_06_12_000001_add_service_provider_to_plugins_table.php`
**Commit:** `2836df4`
**Applied fix:** 新增 `service_provider` 数据库字段（迁移文件），Plugin 模型 `$fillable` 和 PHPDoc `@property` 同步更新；`runPublish` 改用 `$plugin->service_provider` 字段，并加 `is_subclass_of` 守卫校验其为合法的 ServiceProvider 子类；`syncFromInstalled` 同步读取 `extra.filament-admin.service_provider`。
**注意：** 下游已安装插件的 `extra.filament-admin` 声明需补充 `service_provider` 字段才能触发资源发布，建议更新文档说明。

---

### WR-03: slug 冲突导致 DB 唯一约束违反

**Files modified:** `app/Services/PluginManager.php`
**Commit:** `b7306ca`
**Applied fix:** `syncFromInstalled` 中 slug 降级逻辑由 `str($packageName)->afterLast('/')` 改为 `str($packageName)->replace('/', '-')`，确保同末段不同 vendor 的包生成唯一 slug（如 `vendor-a-my-plugin` / `vendor-b-my-plugin`）。

---

### WR-04: PackagistValidationTest 内第一个 Http::fake 为死代码

**Files modified:** `tests/Unit/Plugins/PackagistValidationTest.php`
**Commit:** `9849cc5`
**Applied fix:** 删除测试块开头第一个从未生效的 `Http::fake` 块（`some-vendor/some-package` 响应），保留并更名实际生效的空版本列表场景测试，测试名称改为「Packagist 返回空版本列表时阻断安装」以准确描述意图。

---

### WR-05: PluginPanelRegistrationTest 直接 DROP 表破坏测试隔离

**Files modified:** `tests/Feature/Plugins/PluginPanelRegistrationTest.php`
**Commit:** `a11bb96`
**Applied fix:** 删除 `Schema::dropIfExists('plugins')` 和 `Artisan::call('migrate')` 语句；改用 `Cache::shouldReceive('remember')->andThrow(QueryException)` 在不触碰数据库 Schema 的情况下模拟 plugins 表不存在的场景，验证 `registerEnabledPlugins` 的 try/catch 静默分支。

---

### WR-06: landing.blade.php 引入第三方 CDN Tailwind

**Files modified:** `resources/views/landing.blade.php`
**Commit:** `fab5acb`
**Applied fix:** 将 `<script src="https://cdn.tailwindcss.com"></script>` 替换为 `@vite('resources/css/app.css')`，使用项目已有的 Vite + `@tailwindcss/vite` 构建链，消除供应链 XSS 风险和 CDN 运行时依赖。

---

### WR-07: ScanPlugins 命令在 routes/console.php 中重复注册

**Files modified:** `routes/console.php`
**Commit:** `b58f0ac`
**Applied fix:** 删除 `use App\Console\Commands\ScanPlugins` 和 `Artisan::registerCommand(new ScanPlugins())` 两行，依赖 Laravel 13 对 `app/Console/Commands/` 目录的自动发现，消除双重注册和绕过容器注入的问题。

---

_Fixed: 2026-06-12T05:30:00Z_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
