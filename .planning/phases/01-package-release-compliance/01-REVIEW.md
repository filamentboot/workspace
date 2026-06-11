---
phase: 01-package-release-compliance
reviewed: 2026-06-10T00:00:00Z
depth: standard
files_reviewed: 15
files_reviewed_list:
  - packages/filament-admin/.gitignore
  - packages/filament-admin/composer.json
  - packages/filament-admin/composer.lock
  - packages/filament-admin/phpstan.neon
  - packages/filament-admin/phpunit.xml.dist
  - packages/filament-admin/pint.json
  - packages/filament-admin/resources/lang/en/.gitkeep
  - packages/filament-admin/resources/lang/zh_CN/.gitkeep
  - packages/filament-admin/src/Commands/PublishCommand.php
  - packages/filament-admin/src/FilamentAdminServiceProvider.php
  - packages/filament-admin/stubs/FeatureTest.stub
  - packages/filament-admin/stubs/Resource.stub
  - packages/filament-admin/tests/Unit/PackageMetadataTest.php
  - packages/filament-admin/tests/Unit/PublishCommandTest.php
  - packages/filament-admin/tests/Unit/ServiceProviderPublishesTest.php
findings:
  critical: 3
  warning: 8
  info: 4
  total: 15
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-06-10T00:00:00Z
**Depth:** standard
**Files Reviewed:** 15
**Status:** issues_found

## Summary

本阶段（包发布合规 COMPLY-01~05）核心使命是"用户 `composer require` 后能 publish 资源、PublishCommand 可用、生成产物能开箱运行"。审查覆盖 `PublishCommand`、`FilamentAdminServiceProvider` 的 publishes 注册、四个 stub 模板、三个单元测试与配置/CI 文件。

本次为复审，沿用上一轮仍然成立的发现并新增若干本轮捕获的缺陷。最严重的新发现是 **CR-03**：发布出去的 `Resource.stub` 使用 **Filament 4 的表格 API**（`->actions()` / `->bulkActions()` / `Tables\Actions\*`），而本包锁定 Filament `^5.0` 且包自身真实 Resource 已全部迁移到 Filament 5 的 `->recordActions()` / `->toolbarActions()` + `Filament\Actions\*`——这意味着 `filament-admin:publish --resource=X` 的产物在 Filament 5 下会直接致命错误，恰好命中本阶段最该保证的"publish 能开箱运行"。

合计 3 个 BLOCKER、8 个 WARNING、4 个 INFO。

---

## Critical Issues

### CR-01: FeatureTest.stub 为 AdminUser 生成无效 PHP（重复 use 别名）

**File:** `packages/filament-admin/stubs/FeatureTest.stub:4-5`

**Issue:**
stub 第 4 行硬编码 `use FilamentAdmin\Models\AdminUser;`，第 5 行渲染 `use App\Models\{{ model }};`。当 `{{ model }}` = `AdminUser` 时（即 `--model=AdminUser`、`--all` 发布内置全套均触发此路径），渲染后的文件同时包含：

```php
use FilamentAdmin\Models\AdminUser;
use App\Models\AdminUser;   // 与上一行的最终别名 AdminUser 重复
```

PHP 遇到此文件时直接抛出 `Cannot use FilamentAdmin\Models\AdminUser as AdminUser because the name is already in use`，导致生成出的 `AdminUserResourceTest.php` 完全无法执行。由于 `--all` 必然对 `AdminUser` 调用 `publishFeatureTest`（`PublishCommand.php:124-133, 165-179`），此 Bug 对最常见的使用路径均有影响。

**Fix:**
在 stub 中对包内模型使用别名，与下方渲染的 `App\Models\{{ model }}` 区分开：

```php
use {{ appResourceNamespace }}\{{ resourceNamespace }}\{{ resource }};
use FilamentAdmin\Models\AdminUser as PackageAdminUser;
use App\Models\{{ model }};
```

同时将 stub 正文中引用 `AdminUser` 类型的地方改为 `PackageAdminUser`（`make{{ model }}SuperAdmin` 函数的返回类型声明及 `AdminUser::factory()` 调用）。

---

### CR-02: PublishCommand 导入幽灵类 `FilamentAdminPlugin`，导致 Pint CI 失败

**File:** `packages/filament-admin/src/Commands/PublishCommand.php:5`

**Issue:**
```php
use FilamentAdmin\FilamentAdminPlugin;
```
该 `use` 引入的类名在整个文件中从未在 PHP 语义层面被引用，唯一出现处是第 553 行的字符串字面量 `$this->line('    FilamentAdminPlugin::make()');`，字符串内容不触发类解析。`pint.json` 显式启用了 `no_unused_imports: true`，CI 中的 `composer pint:test`（`ci.yml:41-42`，且该步骤未对 8.4 设置 continue-on-error，见 WR-05）会因未使用导入以非零退出码失败，阻塞流水线。

**Fix:** 删除第 5 行 `use FilamentAdmin\FilamentAdminPlugin;`。`printBindingExample()` 仅输出提示字符串，删除后逻辑不变。

---

### CR-03: Resource.stub 使用 Filament 4 表格 API，发布到 Filament 5 项目后无法运行

**File:** `packages/filament-admin/stubs/Resource.stub:6-11, 76-84`

**Issue:**
该 stub 的 `table()` 使用 Filament 4 API：

```php
use Filament\Tables;                       // 行内 Tables\Actions\EditAction
...
->actions([
    Tables\Actions\EditAction::make(),
    Tables\Actions\DeleteAction::make(),
])
->bulkActions([
    BulkActionGroup::make([ DeleteBulkAction::make() ]),
]);
```

但 `composer.json:26` 锁定 `filament/filament: ^5.0`，且包内真实 Resource 已全部迁移到 Filament 5（实证）：
- `src/Filament/Resources/AdminUsers/AdminUserResource.php:6-7`：`use Filament\Actions\DeleteAction; use Filament\Actions\EditAction;`
- `AdminUserResource.php:149`、`DepartmentResource.php:128`、`MenuResource.php:208`、`LoginLogResource.php:136` 均使用 `->recordActions([...])`（而非 `->actions()`）。

Filament 5 中 `Tables\Actions\EditAction` / `Tables\Actions\DeleteAction` 已迁移到 `Filament\Actions\`，`Table` 上的行/工具栏动作方法也改为 `recordActions()` / `toolbarActions()`。因此 `filament-admin:publish --resource=Product` 生成的文件在用户 Filament 5 项目中加载时会因类不存在 / 方法不存在而致命错误，直接违背本阶段"publish 能开箱运行"的核心目标。stub 顶部已 import `Filament\Actions\BulkActionGroup` 与 `Filament\Actions\DeleteBulkAction`（Filament 5 路径）却在 `actions()` 内混用 `Tables\Actions\*`，明确暴露该 stub 未完成迁移。现有 `PublishCommandTest` 仅断言文件存在与占位符字符串，未实际加载/编译生成的 Resource，无法捕获此缺陷。

**Fix:** 将 stub 的 `table()` 改为与包内真实 Resource 一致的 Filament 5 API：

```php
use {{ modelNamespace }}\{{ model }};
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
...
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('创建时间')->dateTime('Y-m-d H:i')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([ DeleteBulkAction::make() ]),
            ]);
```

并新增测试对生成的 Resource 做 `php -l` 或 `require` 加载，防止回归到旧 API。

---

## Warnings

### WR-01: `renderStub` 在包 stub 文件缺失时静默生成空文件

**File:** `packages/filament-admin/src/Commands/PublishCommand.php:303-305`

**Issue:**
```php
$stubPath = file_exists($userStub) ? $userStub : $packageStub;
$content  = (string) file_get_contents($stubPath);
```
当 `$packageStub` 不存在时，`file_get_contents` 返回 `false`，强转后得到空字符串，随后 `writeFile` 写入空文件并返回 `SUCCESS`，用户无任何报错，不知道产物已损坏。

**Fix:**
```php
if (! file_exists($stubPath)) {
    $this->error("Stub 文件不存在，无法渲染：{$stubPath}");

    return '';
}
$content = (string) file_get_contents($stubPath);
```
并在调用方检查 `renderStub` 返回空字符串时跳过写入并返回 false。

---

### WR-02: `Model.stub` 顶级 `use` 导入对应被注释的 trait，违反 `no_unused_imports`

**File:** `packages/filament-admin/stubs/Model.stub:7-9`

**Issue:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
```
对应的 trait/类在类体内均被注释掉（`// use SoftDeletes;`、`// use LogsActivity;`）。用户发布后在自己项目运行 Pint 时会被判违规，需手动清理，降低开箱可用性。

**Fix:** 将三行顶级 `use` 也注释掉，与类体一致。

---

### WR-03: `ServiceProviderPublishesTest` 对 `filament-admin-lang` tag 的断言不完整

**File:** `packages/filament-admin/tests/Unit/ServiceProviderPublishesTest.php:109-132`

**Issue:**
`registerPublishes()` 对 `filament-admin-lang` 注册了 `en` 与 `zh_CN` 两条映射，但测试只检查"至少有一个路径包含 `resources/lang`"。若 `zh_CN` 映射被删除，测试仍通过，无法作为回归保护。

**Fix:**
```php
$sources = array_keys($paths);
self::assertTrue((bool) array_filter($sources, fn ($s) => str_ends_with($s, '/lang/en')), '缺 lang/en 映射');
self::assertTrue((bool) array_filter($sources, fn ($s) => str_ends_with($s, '/lang/zh_CN')), '缺 lang/zh_CN 映射');
```

---

### WR-04: `publishResource` 向 `renderStub` 传入 stub 中不存在的参数键 `resourceNamespace`

**File:** `packages/filament-admin/src/Commands/PublishCommand.php:215-223`

**Issue:**
`vars` 含 `'resourceNamespace' => $pluralName`，但 `Resource.stub` 无 `{{ resourceNamespace }}` 占位符（实际用 `{{ namespace }}` 等）。无运行时错误，但会误导维护者，并与 `{{ namespace }}` 造成混淆。

**Fix:** 从 `renderStub('Resource', [...])` 删除 `'resourceNamespace' => $pluralName` 键。

---

### WR-05: CI 对 PHP 8.4 的 `pint:test` 未设 `continue-on-error`，与其他步骤容错政策不一致

**File:** `packages/filament-admin/.github/workflows/ci.yml:41-42`

**Issue:**
`composer test`（:35）与 `composer phpstan`（:39）均设 `continue-on-error: ${{ matrix.php == '8.4' }}`，明确允许 8.4 暂时失败；但 `Pint 代码风格检查` 步骤未设置该开关，对 8.4 一视同仁，与项目 8.4"软支持"政策矛盾——一旦 8.4 解析差异触发 Pint 行为差异即会阻塞整个 8.4 构建。

**Fix:**
```yaml
- name: Pint 代码风格检查
  run: composer pint:test
  continue-on-error: ${{ matrix.php == '8.4' }}
```

---

### WR-06: 非默认 `--path` 下 FeatureTest 导入的 Resource 命名空间与实际生成位置不一致

**File:** `packages/filament-admin/src/Commands/PublishCommand.php:377-389, 422-436`

**Issue:**
`deriveResourceNamespace()` 与 `deriveAppResourceNamespace()` 对同一 `--path` 推导出不同命名空间，导致 `--all` 模式（会生成 FeatureTest）下测试 `use` 一个不存在的类。

以 `--path=app/Filament/Reseller --resource=Product --all` 为例：
- Resource 实际写入命名空间（`deriveResourceNamespace`）：`App\Filament\Reseller\Products`（不含 `Resources` 段）。
- FeatureTest 导入命名空间（`deriveAppResourceNamespace` 在末段非 `Resources` 时追加 `Resources`）：`App\Filament\Reseller\Resources`，最终拼成 `App\Filament\Reseller\Resources\Products\ProductResource`。

二者不一致：生成的 `ProductResourceTest.php` 顶部 `use ...Resources\Products\ProductResource;` 指向不存在的类。默认路径 `app/Filament/Resources` 下两者恰好一致，故现有测试覆盖不到该分支。

**Fix:** 让 FeatureTest 的导入直接复用 `deriveResourceNamespace($name)` 的结果，删除分歧的 `deriveAppResourceNamespace()`，保证两者同源。

---

### WR-07: FeatureTest.stub 硬编码 `use App\Models\{{ model }}`，与 `--with-models` 子目录冲突

**File:** `packages/filament-admin/stubs/FeatureTest.stub:5` 配合 `PublishCommand.php:196-198, 357-366`

**Issue:**
`publishModel()` 在 `--with-models` 且 `--path` 非默认时，将 Model 写到 `App\Models\{PanelPrefix}` 子命名空间（`deriveModelNamespace()` 返回 `App\Models\Reseller`），文件落在 `app/Models/Reseller/Product.php`。但 `FeatureTest.stub:5` 写死 `use App\Models\{{ model }};`（无对应占位符），渲染后仍是 `use App\Models\Product;`，指向不存在的类。该 use 语句顺序也不符合 Pint `laravel` 预设字母序。

**Fix:** 为 stub 增加 `{{ appModelNamespace }}` 占位符，由 `publishFeatureTest()` 用 `deriveModelNamespace()` 注入，并调整 use 顺序。

---

### WR-08: `validatePath` 未限制 `--path` 必须位于 `app/` 内，可写出任意工程内目录

**File:** `packages/filament-admin/src/Commands/PublishCommand.php:340-349`

**Issue:**
`validatePath()` 拒绝 `..`、绝对路径、Windows 盘符，挡住越权写到项目外；但不限制 `--path` 必须在 `app/` 之内。诸如 `--path=storage/x`、`--path=routes`、`--path=config` 都会通过校验，随后 `base_path($path.'/...')` 直接创建目录并写文件（配合 `--force` 可覆盖），且 `deriveResourceNamespace()` 会产出 `Storage\X\...` 这类非 PSR-4 命名空间。对面向公开发布的 artisan 命令属输入校验不足。

**Fix:** 在 `validatePath()` 增加白名单：要求 `--path` 以 `app/` 开头（或更严格 `app/Filament/`），否则报错返回 FAILURE。

---

## Info

### IN-01: `publishFeatureTest` 向 `renderStub` 传入 stub 中未使用的 `namespace` 参数

**File:** `packages/filament-admin/src/Commands/PublishCommand.php:276`

**Issue:** 传入 `'namespace' => 'Tests\\Feature'`，但 `FeatureTest.stub` 是 Pest 文件无 `namespace` 声明，无 `{{ namespace }}` 占位符，该键值对无效。

**Fix:** 删除 `'namespace' => 'Tests\\Feature',` 键值对。

---

### IN-02: `phpunit.xml.dist` 缺少严格性配置，无断言测试可静默通过；套件命名与缓存目录不对齐

**File:** `packages/filament-admin/phpunit.xml.dist`

**Issue:**
配置仅有最简 `testsuites` 块，缺 `failOnRisky="true"`，遗漏断言的 `test_*` 会被标记 Risky 但不失败，掩盖质量问题。此外 `PublishCommandTest` / `ServiceProviderPublishesTest` 继承 `Orchestra\Testbench\TestCase`、启动完整应用、读写临时目录，实为集成/Feature 级测试，却放在 `tests/Unit` 并由唯一的 "Unit" 套件运行，与 CLAUDE.md 测试金字塔命名不符；`.gitignore` 忽略 `/.phpunit.cache` 但 xml 未设 `cacheDirectory=".phpunit.cache"`。

**Fix:**
```xml
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         cacheDirectory=".phpunit.cache"
         beStrictAboutOutputDuringTests="true">
```
并增设 Feature 套件目录使命名与金字塔对齐。

---

### IN-03: `phpstan.neon` 分析路径包含 `tests/`，可能产生 Testbench 相关误报

**File:** `packages/filament-admin/phpstan.neon`

**Issue:** `paths` 同时列 `src` 与 `tests`。Testbench 魔术方法（`artisan()`、`getPackageProviders()` 等）在 Larastan 扩展未完全覆盖时可能误报，使 `composer phpstan` 在 `tests/` 正确时也报类型错误。

**Fix:** 视实际误报情况，将 `paths` 收敛为仅 `src`，或为 `tests` 配置针对性 `ignoreErrors`。

---

### IN-04: `pluralize()` 简单追加 `s`，对以 `y`/`x` 等结尾的模型生成错误复数

**File:** `packages/filament-admin/src/Commands/PublishCommand.php:446-449`

**Issue:** `--model=Category` 会生成 `Categorys` 而非 `Categories`，进而产出错误的目录名、表名与命名空间。代码注释已承认此限制（延后到 Phase 3）。Laravel 自带 `Illuminate\Support\Str::plural()`，无需引入新依赖即可立即修正大部分情况。

**Fix:**
```php
protected function pluralize(string $name): string
{
    return \Illuminate\Support\Str::plural($name);
}
```

---

_Reviewed: 2026-06-10T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
