---
phase: 06-plugin-marketplace-launch
reviewed: 2026-06-12T04:02:00Z
depth: standard
files_reviewed: 34
files_reviewed_list:
  - app/Console/Commands/ScanPlugins.php
  - app/Filament/Pages/Marketplace/MarketplacePage.php
  - app/Filament/Resources/PluginResource.php
  - app/Filament/Resources/PluginResource/Pages/ListPlugins.php
  - app/Filament/Resources/PluginResource/Pages/ViewPlugin.php
  - app/Models/Plugin.php
  - app/Policies/PluginPolicy.php
  - app/Providers/Filament/AdminPanelProvider.php
  - app/Services/MarketplaceService.php
  - app/Services/PluginManager.php
  - database/factories/PluginFactory.php
  - database/migrations/2026_06_11_000001_create_plugins_table.php
  - packages/filament-admin/database/migrations/2026_06_11_000002_add_logo_url_contact_email_to_general_settings.php
  - packages/filament-admin/database/seeders/AdminFoundationMenuSeeder.php
  - packages/filament-admin/database/seeders/AdminFoundationPermissionSeeder.php
  - packages/filament-admin/src/Filament/Pages/Settings/GeneralSettingsPage.php
  - packages/filament-admin/src/Filament/Resources/AdminUsers/Pages/ListAdminUsers.php
  - packages/filament-admin/src/Filament/Resources/Departments/Pages/ListDepartments.php
  - packages/filament-admin/src/Filament/Resources/LoginLogs/Pages/ListLoginLogs.php
  - packages/filament-admin/src/Services/AdminNavigationBuilder.php
  - packages/filament-admin/src/Settings/GeneralSettings.php
  - resources/views/filament/pages/marketplace.blade.php
  - resources/views/filament/resources/plugin-resource/pages/view-plugin.blade.php
  - resources/views/landing.blade.php
  - routes/console.php
  - routes/web.php
  - tests/Feature/Exporters/ExporterAuthorizationTest.php
  - tests/Feature/Plugins/LandingPageTest.php
  - tests/Feature/Plugins/PluginManagerInitializeTest.php
  - tests/Feature/Plugins/PluginPanelRegistrationTest.php
  - tests/Feature/Plugins/ScanPluginsCommandTest.php
  - tests/Feature/Settings/GeneralSettingsTest.php
  - tests/Stubs/FakeFilamentPlugin.php
  - tests/Unit/Plugins/MarketplaceServiceTest.php
  - tests/Unit/Plugins/PackagistValidationTest.php
  - tests/Unit/Plugins/PluginDependencyTest.php
findings:
  critical: 3
  warning: 6
  info: 4
  total: 13
status: issues_found
---

# Phase 06: Code Review Report

**Reviewed:** 2026-06-12T04:02:00Z
**Depth:** standard
**Files Reviewed:** 34
**Status:** issues_found

## Summary

审查了 Phase 06 插件市场启动功能的全部源文件（含 06-06 Gap 修复后的最新版本）。

**已修复（本轮不再列为 BLOCKER）：**
- CR-01：`updateOrCreate` 中 `installed_at` 传入 Closure 导致 TypeError — 两处均已改为预查询 `$existing?->installed_at ?? now()`，修复正确。
- CR-04：`documentation_url` 未校验 scheme 存在 XSS — 两个 Blade 文件均已补充 `preg_match('#^https?://#i', $docUrl)` 白名单校验，修复正确。

**仍存在的 BLOCKER（3 项）：**

1. `MarketplaceService::fetchIndex` 对 HTTP 4xx/5xx 响应不触发兜底，空数组被缓存 300 秒（CR-02 未修复）。
2. `ViewPlugin::initialize()` Livewire 方法无授权守卫，`wire:click="initialize"` 绕过 Action 的权限检查（CR-03 未修复）。
3. `PluginPolicy` 未在任何显式映射中注册，授权安全依赖约定自动发现（CR-05 未修复）。

**仍存在的 WARNING（6 项）：**

`syncFromInstalled` 与 `ScanPlugins::handle` 重复实现（前者为死代码）、`runPublish` 误用 Filament Plugin 类作 `--provider` 参数、slug 降级逻辑存在唯一约束冲突风险、`PackagistValidationTest` 中死代码 Http::fake、`PluginPanelRegistrationTest` 直接 DROP 表破坏测试隔离、`landing.blade.php` 引入第三方 CDN Tailwind、以及新发现的 `ScanPlugins` 命令双重注册问题。

---

## Critical Issues

### CR-01: `MarketplaceService::fetchIndex` HTTP 错误不触发兜底，空结果被缓存 300 秒

**File:** `app/Services/MarketplaceService.php:25-35`
**Issue:** 代码意图是「网络失败时兜底本地配置」，但实现有根本性缺陷：

Laravel HTTP Client **默认不对 4xx/5xx 响应抛异常**（需显式链式调用 `->throw()`）。当远程服务器返回 HTTP 500（或 404/503 等），`Http::retry(2, 100)->timeout(10)->get($url)` 不抛出任何异常，`catch (\Throwable)` 分支**永远不会执行**，`->json('entries', [])` 在非 JSON 响应体下直接返回 `[]`。结果：

1. 兜底 `config('official-market.entries', [])` 永远不被返回
2. 空 `[]` 被 `Cache::remember('market.index', 300, ...)` 缓存 5 分钟
3. 即使远程服务立即恢复，市场页面仍显示「暂无市场数据」长达 5 分钟

`Http::retry(2, 100)` 仅对连接级异常（`ConnectionException`）重试，对 5xx 无效。测试 `MarketplaceServiceTest` 中「fetchIndex HTTP 失败时返回本地兜底配置」断言期望 6 条记录，但实际运行时该测试**会失败**（实际返回 0 条），表明此 bug 未被测试真正覆盖。

**Fix:**

```php
public function fetchIndex(): array
{
    $url = config('plugin-platform.official_market_index_url');

    try {
        return Cache::remember('market.index', 300, function () use ($url): array {
            return Http::retry(2, 100)
                ->timeout(10)
                ->throw()           // 使 4xx/5xx 进入 catch 分支
                ->get($url)
                ->json('entries', []);
        });
    } catch (\Throwable) {
        // 网络/HTTP 错误：返回本地兜底，不写入缓存，下次请求仍会回源
        return config('official-market.entries', []);
    }
}
```

注意：兜底结果不应被 `Cache::remember` 缓存，否则一次故障污染 300 秒。

---

### CR-02: `ViewPlugin::initialize()` Livewire 方法无授权守卫，绕过 Action 的权限检查

**File:** `app/Filament/Resources/PluginResource/Pages/ViewPlugin.php:50-54`, `resources/views/filament/resources/plugin-resource/pages/view-plugin.blade.php:82`
**Issue:** `view-plugin.blade.php:82` 中的「重试初始化」按钮使用 `wire:click="initialize"` 直接调用 Livewire 方法：

```blade
<x-filament::button wire:click="initialize" color="warning">
    重试初始化
</x-filament::button>
```

而 `ViewPlugin::initialize()` 方法是公开方法，无任何授权检查：

```php
public function initialize(): void
{
    app(PluginManager::class)->initialize($this->record);
    $this->refreshInitProgress();
}
```

Filament Header Action 的 `->authorize('initialize_plugin')`（第 63 行）**仅保护通过 Filament Action 派发机制点击「初始化」按钮的路径**。`wire:click="initialize"` 直接触发 Livewire 方法，完全绕过该授权检查。任何具有查看插件详情页权限（`view_plugin`）的用户，都可以通过构造 Livewire 请求触发初始化操作，无需 `initialize_plugin` 权限。初始化操作会执行 `migrate --force`、`vendor:publish --force` 和 `db:seed --force`，影响范围极大。

**Fix:** 在方法头部添加显式授权检查：

```php
public function initialize(): void
{
    $this->authorize('initialize', $this->record); // 走 PluginPolicy::initialize()
    // 或等价写法：
    // abort_unless(auth('admin')->user()?->can('initialize_plugin'), 403);

    app(PluginManager::class)->initialize($this->record);
    $this->refreshInitProgress();
}
```

---

### CR-03: `PluginPolicy` 未在任何 `$policies` 映射中显式注册，授权依赖约定自动发现

**File:** `app/Policies/PluginPolicy.php:15`, `packages/filament-admin/src/FilamentAdminServiceProvider.php:39-46`
**Issue:** 主包 `FilamentAdminServiceProvider` 的 `$policies` 数组显式注册了 6 个 Policy（`AdminUser`、`LoginLog`、`Menu`、`Department`、`Activity`、`Role`），唯独缺失 `Plugin::class => PluginPolicy::class`。当前能工作仅因 `App\Models\Plugin` + `App\Policies\PluginPolicy` 命中 Laravel 约定自动发现。但：

- 与项目「全部显式注册」风格不一致，维护者看不到完整的 Policy 注册表
- 若 Plugin 模型后续迁移到主包命名空间（`FilamentAdmin\Models\Plugin`），自动发现失效
- 若 `Gate::guessPolicyNamesUsing()` 被自定义（如 Shield 修改），授权**静默失效**
- Filament 在找不到 Policy 时默认放行 `viewAny`，静默失效等于对所有登录用户开放插件管理

**Fix:** 在 `AppServiceProvider::boot()` 中显式注册：

```php
use App\Models\Plugin;
use App\Policies\PluginPolicy;

Gate::policy(Plugin::class, PluginPolicy::class);
```

或在主包 `FilamentAdminServiceProvider::$policies` 中添加（需添加 Plugin 模型依赖）。

---

## Warnings

### WR-01: `PluginManager::syncFromInstalled` 与 `ScanPlugins::handle` 重复实现，前者为死代码

**File:** `app/Services/PluginManager.php:56-101`, `app/Console/Commands/ScanPlugins.php:27-82`
**Issue:** `syncFromInstalled()` 与 `ScanPlugins::handle()` 逐行重复了 `installed.json` 解析 + `Plugin::updateOrCreate` 同步逻辑。生产路径（ListPlugins 页头按钮 `Artisan::call('plugin:scan')`）走命令内联实现；`syncFromInstalled()` 在生产代码路径中未被任何调用方引用。两份实现随时间漂移：一处修 bug 另一处不会同步，测试覆盖的是 service 方法而非真正运行的命令路径。

**Fix:** 让 `ScanPlugins::handle()` 委托 `app(PluginManager::class)->syncFromInstalled()`，删除命令内的重复逻辑，并调整测试以覆盖命令路径。

---

### WR-02: `runPublish` 用 Filament Plugin 类作 `--provider`，`vendor:publish` 无法识别

**File:** `app/Services/PluginManager.php:166-175`
**Issue:** 初始化链路中：

```php
Artisan::call('vendor:publish', [
    '--provider' => $plugin->plugin_class,
    '--force'    => true,
]);
```

`vendor:publish --provider` 期望接收继承 `Illuminate\Support\ServiceProvider` 的类。`plugin_class` 字段存储的是实现 `Filament\Contracts\Plugin` 接口的 Filament 插件类，不是 ServiceProvider。Laravel 的 `PublishCommand` 查找 provider 时**静默跳过**非 ServiceProvider 类，不发布任何文件，也不抛出错误，仅产生空输出写入日志。开发者无法感知资源发布步骤被跳过，且会误认为 `init_status=done` 意味着资源已正常发布。

**Fix:** 在 `extra.filament-admin` 声明中分开存储 `plugin_class`（Filament Plugin）和 `service_provider`（ServiceProvider，用于 vendor:publish）；或在 `runPublish` 中验证类是否继承 ServiceProvider，否则跳过并记录说明。

---

### WR-03: 两个不同 vendor 的包共享相同末段时 slug 冲突导致 DB 唯一约束违反

**File:** `app/Console/Commands/ScanPlugins.php:52`, `app/Services/PluginManager.php:77`
**Issue:** slug 降级逻辑：

```php
$slug = $meta['slug'] ?? str($packageName)->afterLast('/')->value();
```

若 `vendor-a/my-plugin` 与 `vendor-b/my-plugin` 均未在 `extra.filament-admin` 中声明 `slug`，两者 slug 均为 `my-plugin`。`plugins.slug` 列有唯一约束，第二个 `updateOrCreate` 触发 `Integrity constraint violation: 1062 Duplicate entry`，扫描命令报错中止，后续插件均无法同步。

**Fix:** 降级 slug 应包含 vendor 前缀以确保唯一性：

```php
$slug = $meta['slug'] ?? str($packageName)->replace('/', '-')->value();
```

---

### WR-04: `PackagistValidationTest` 内第一个 `Http::fake` 被第二个覆盖，形成死代码

**File:** `tests/Unit/Plugins/PackagistValidationTest.php:41-50`
**Issue:** 「semver 版本约束不满足时阻断安装」测试在同一个 `it()` 块内连续调用两次 `Http::fake()`。第一次（第 41 行）为 `some-vendor/some-package` 伪造 200 响应，但第二次（第 56 行）完全替换了 Http fake 配置，第一次的伪造从未被用到。实际断言的是 `empty-vendor/empty-package` 返回空版本列表 → `false` 的分支，与测试名称描述的场景不符。真正的 semver 约束不满足分支（有版本、有约束、版本不满足约束）没有被任何测试覆盖。

**Fix:** 删除第一个死代码 `Http::fake` 块（第 41-52 行），并补充一个真正测试 semver 不满足场景的用例（需要在 config 中预设约束，或 mock `getCompatibilityConstraint`）。

---

### WR-05: `PluginPanelRegistrationTest` 直接执行 `Schema::dropIfExists('plugins')` 破坏测试隔离

**File:** `tests/Feature/Plugins/PluginPanelRegistrationTest.php:93`
**Issue:** 测试通过 `Schema::dropIfExists('plugins')` 模拟表不存在场景。在 MySQL 下，DDL 语句（`DROP TABLE`）会**自动提交**当前事务，无法被 `RefreshDatabase` 的事务回滚撤销。若后续测试依赖 `plugins` 表存在，且该测试末尾的 `Artisan::call('migrate')` 因任意原因失败，将导致测试套件中多个测试因表不存在而崩溃。

**Fix:** 改用 Mock/Spy 手段模拟 QueryException 而不实际 DROP 表，避免直接操作 schema：

```php
// 通过依赖注入或 spy，让 Plugin::query() 抛出 QueryException
$this->mock(Plugin::class)->shouldReceive('query')
    ->andThrow(new \Illuminate\Database\QueryException(...));
```

---

### WR-06: `landing.blade.php` 生产页引入 `cdn.tailwindcss.com`（供应链风险）

**File:** `resources/views/landing.blade.php:7`
**Issue:** 着陆页通过 `<script src="https://cdn.tailwindcss.com"></script>` 加载 Tailwind。该 CDN 官方明确标注「仅供开发，不要用于生产」：在浏览器端实时编译（性能差、FOUC），且对第三方 CDN 产生运行时依赖。CDN 被劫持即可向公开着陆页注入任意脚本（供应链 XSS）。这是对外展示的公网页面。

**Fix:** 改用项目已有的 Vite + `@tailwindcss/vite` 构建链产出本地 CSS，在 `<head>` 中使用 `@vite('resources/css/app.css')` 替换 CDN script 标签。

---

### WR-07: `ScanPlugins` 命令在 `routes/console.php` 中重复注册

**File:** `routes/console.php:16`
**Issue:** `ScanPlugins` 类位于 `app/Console/Commands/ScanPlugins.php`，Laravel 13 默认通过 `withCommands` 自动发现 `app/Console/Commands/` 目录下的全部命令类。同时，`routes/console.php` 第 16 行又显式调用 `Artisan::registerCommand(new ScanPlugins())`。同一命令被注册两次，后者覆盖前者（Symfony Console 对同名命令静默替换）。虽然功能上无错误，但冗余注册增加启动开销，且命令实例被 `new` 直接构造而非从容器解析，绕过了依赖注入，若 `ScanPlugins` 未来需要构造函数注入将引发回归。

**Fix:** 删除 `routes/console.php` 第 16 行的 `Artisan::registerCommand(new ScanPlugins())`，依赖自动发现即可：

```php
// 删除以下两行
use App\Console\Commands\ScanPlugins;
Artisan::registerCommand(new ScanPlugins());
```

---

## Info

### IN-01: `getCurrentEnvironmentVersions` 含冗余重复键

**File:** `app/Services/PluginManager.php:313-319`
**Issue:** 返回数组同时含 `'filament'` 和 `'filament/filament'`（解析相同包），以及 `'laravel'` 和 `'laravel/framework'`（相同版本）。属冗余，建议统一为 `vendor/package` 标准键，或在注释说明为兼容两种 compatibility 声明写法而刻意保留。

---

### IN-02: `json_decode` 失败时静默当作「无插件」处理，扫描结果误导

**File:** `app/Console/Commands/ScanPlugins.php:38`, `app/Services/PluginManager.php:65`
**Issue:** `json_decode(file_get_contents($installedJson), true) ?? []` 在 JSON 损坏时返回 `[]`，扫描以「扫描完成，共 0 个插件」结束。建议显式检查 `json_last_error()` 并输出 WARNING 或返回 `self::FAILURE`，区分「无插件」和「文件损坏」两种情况。

---

### IN-03: 初始化失败后 `is_enabled` 未回滚，可能注册一个初始化未完成的插件

**File:** `app/Services/PluginManager.php:110-143`
**Issue:** `initialize()` 失败后将 `init_status` 置为 `failed`，但若插件此前 `is_enabled=true`，失败后仍处于启用态，会被 `registerEnabledPlugins` 注册到面板，加载一个迁移/seed 可能未完成的插件。建议明确：初始化失败时是否应自动禁用；或在 `enable()` 中加前置检查要求 `init_status=done`。

---

### IN-04: `validatePackageName` 未接入生产流程，属规划残留

**File:** `app/Services/PluginManager.php:208-246`
**Issue:** 该 typosquatting 缓解方法仅被单元测试调用，扫描/安装实际路径均未调用。由于 D-06-15 已决定无一键安装，此方法连同 `getCompatibilityConstraint` 的远程分支属于规划残留代码。建议删除或在注释中明确标注「保留供未来一键安装」，避免读者误认为扫描时已做包名校验。

另：`validatePackageName` 在包名不含 `/` 时（如 `tiptap`），`[$vendor, $name] = explode('/', $packageName, 2)` 导致 `$name = ''`（PHP Warning: Undefined array key 1），若未来接入生产应先添加格式校验。

---

_Reviewed: 2026-06-12T04:02:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
