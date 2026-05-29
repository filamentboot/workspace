# 操作日志 实现计划

> 修订记录：2026-05-29 根据审查问题清单修复 10 项问题（Settings 回退逻辑、配置只改必要 key、Filament 5 Schema API、AdminUser::factory、composer pint、Tag 中文后缀、Spatie 自带清理命令、复用已有 LogAdminLogin、按 causer_type 过滤、ActivityLogPolicy）。

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现 opt-in 操作日志系统，基于 Spatie ActivityLog，只有显式加了 LogsActivity Trait 的 Model 才记录操作日志，提供只读管理界面和定期清理机制。

**Architecture:** 使用 spatie/laravel-activitylog 记录 Model 的 created/updated/deleted 事件。ActivityLogResource 提供只读查询界面，仅展示由 admin guard 用户（`causer_type = App\Models\AdminUser`）触发的日志，包含操作人、操作对象、操作类型、时间列，以及 JSON diff 详情查看。日志清理直接使用 Spatie 自带的 `activitylog:clean` 命令，通过 `routes/console.php` 中的 `Schedule` Facade 每日调度。opt-in 策略确保只有显式使用 LogsActivity Trait 的模型才会产生日志记录。

**Tech Stack:** spatie/laravel-activitylog ^4.0, Filament 5（Schemas\Schema API）, Pest 4

---

## 前置依赖

- **参数配置域（v0.3.0）已先行实现**，提供 `App\Settings\GeneralSettings`，其中包含 `public int $log_retention_days` 字段。
- 本域读取保留天数遵循 **回退顺序**：
  1. 优先尝试 `app(GeneralSettings::class)->log_retention_days`
  2. 若 Settings 实例不可用或字段未设置，回退到 `config('filament-admin.log_retention_days')`
  3. 最终兜底默认值 `90`
- 已有 `app/Listeners/LogAdminLogin.php`（phase-1 通过 Laravel 自动发现机制注册），本域不再重复实现，只在需要时新增 `LogAdminLogout`、`LogFailedAdminLogin`，同样由自动发现处理，**禁止**在 `AppServiceProvider` 手动 `Event::listen`。

---

## 文件结构

| 文件 | 操作 | 职责 |
|------|------|------|
| `composer.json` | 修改 | 添加 spatie/laravel-activitylog 依赖 |
| `config/activitylog.php` | 发布后只改必要 key | 仅修改 `default_log_name`、`subject_returns_soft_deleted_models` 等关键项 |
| `database/migrations/xxxx_create_activity_log_table.php` | 新建（vendor:publish） | 创建 activity_log 表 |
| `app/Models/AdminUser.php` | 修改 | 添加 LogsActivity + CausesActivity Trait |
| `app/Filament/Resources/ActivityLogResource.php` | 新建 | 只读 Filament Resource，按 causer_type 过滤仅展示 admin 来源日志 |
| `app/Filament/Resources/ActivityLogResource/Pages/ListActivityLogs.php` | 新建 | 日志列表页，含 ViewAction JSON diff |
| `app/Policies/ActivityLogPolicy.php` | 新建 | 仅允许超管或拥有 `view_any_activity_log` 权限者查看，禁止任何写操作 |
| `app/Providers/AuthServiceProvider.php` | 修改 | 在 `$policies` 注册 Activity => ActivityLogPolicy |
| `routes/console.php` | 修改 | 通过 `Schedule::command('activitylog:clean')` 每日调度 Spatie 自带清理命令 |
| `tests/Feature/ActivityLog/ActivityLogTest.php` | 新建 | 核心路径测试（使用 AdminUser::factory、actingAs 传 'admin' guard） |
| `docs/features/activity-log.md` | 新建 | 功能使用文档 |
| `docs/development/conventions.md` | 新建/修改 | 补充“如何为业务 Model 启用日志”段落 |

> 说明：Laravel 11+ 已无 `app/Console/Kernel.php`，所有调度统一写在 `routes/console.php`。

---

## Task 1: 安装 spatie/laravel-activitylog 并发布配置

**Files:**
- Modify: `composer.json`（通过 composer require）
- Modify: `config/activitylog.php`（vendor:publish 后**只改必要 key**）
- Create: `database/migrations/xxxx_create_activity_log_table.php`（vendor:publish）

- [ ] **Step 1: 安装 Composer 依赖**

```bash
composer require spatie/laravel-activitylog:"^4.0"
```

预期输出：包含 `spatie/laravel-activitylog` 安装成功信息。

- [ ] **Step 2: 发布迁移文件**

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```

预期：`database/migrations/xxxx_create_activity_log_table.php` 被创建。

- [ ] **Step 3: 发布配置文件**

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```

预期：`config/activitylog.php` 被创建（保持 Spatie 默认配置）。

- [ ] **Step 4: 只修改 `config/activitylog.php` 中必要的 key**

**不要重写整个文件**，仅对以下 key 做差异化修改（其他字段保持 Spatie 默认即可）：

```php
// 默认日志名称改为业务标识
'default_log_name' => 'filament-admin',

// 软删除模型仍可作为 subject 返回，便于审计追溯
'subject_returns_soft_deleted_models' => true,
```

修改方式：用 Edit 工具在已发布的 `config/activitylog.php` 中定位上述两个 key 的默认值，原地替换。**禁止用整段 PHP 数组覆盖整个文件**。

- [ ] **Step 5: 执行迁移，创建 activity_log 表**

```bash
php artisan migrate
```

预期：`activity_log` 表创建成功，无报错。

- [ ] **Step 6: 提交**

```bash
git add composer.json composer.lock config/activitylog.php database/migrations/
git commit -m "feat: 安装并配置 spatie/laravel-activitylog"
```

---

## Task 2: 为 AdminUser 启用操作日志

**Files:**
- Modify: `app/Models/AdminUser.php`

- [ ] **Step 1: 编写失败测试**

新建 `tests/Feature/ActivityLog/ActivityLogTest.php`：

```php
<?php

use App\Models\AdminUser;
use Spatie\Activitylog\Models\Activity;

/**
 * 操作日志核心路径测试
 */

/**
 * 更新 AdminUser 后，自动生成操作日志记录
 */
it('更新 AdminUser 后自动生成操作日志记录', function () {
    $user = AdminUser::factory()->create(['name' => '张三']);

    $user->update(['name' => '李四']);

    expect(Activity::all())->toHaveCount(2) // created + updated
        ->and(Activity::latest()->first()->description)->toBe('updated')
        ->and(Activity::latest()->first()->subject_type)->toBe(AdminUser::class)
        ->and(Activity::latest()->first()->subject_id)->toBe($user->id);
});
```

- [ ] **Step 2: 运行测试，确认失败**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --filter="更新 AdminUser 后自动生成操作日志记录"
```

预期：FAIL，因为 AdminUser 尚未使用 LogsActivity Trait。

- [ ] **Step 3: 修改 `app/Models/AdminUser.php`，添加 LogsActivity 和 CausesActivity**

在文件顶部 `use` 区块中添加：

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
```

在类声明中添加 Trait：

```php
class AdminUser extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory;
    use SoftDeletes;
    use TwoFactorAuthenticatable;
    use LogsActivity;    // 记录本模型的操作日志
    use CausesActivity;  // 允许此模型作为操作人
```

在类末尾 `loginLogs()` 方法之后添加：

```php
    /**
     * 操作日志配置
     *
     * 记录除敏感字段外的所有字段变更。
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->dontLogIfAttributesChangedOnly([
                'password',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'remember_token',
            ])
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }
```

- [ ] **Step 4: 运行测试，确认通过**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --filter="更新 AdminUser 后自动生成操作日志记录"
```

预期：PASS。

- [ ] **Step 5: 提交**

```bash
git add app/Models/AdminUser.php tests/Feature/ActivityLog/ActivityLogTest.php
git commit -m "feat: 为 AdminUser 启用操作日志（LogsActivity + CausesActivity）"
```

---

## Task 3: 添加“未加 Trait 的 Model 不产生日志”测试

**Files:**
- Modify: `tests/Feature/ActivityLog/ActivityLogTest.php`

- [ ] **Step 1: 在测试文件中追加测试用例**

在 `tests/Feature/ActivityLog/ActivityLogTest.php` 末尾追加：

```php
/**
 * 未加 LogsActivity Trait 的 User 模型不产生日志
 */
it('未加 LogsActivity Trait 的 User 模型不产生操作日志', function () {
    // App\Models\User 未使用 LogsActivity Trait
    $user = \App\Models\User::factory()->create(['name' => '普通用户']);
    $user->update(['name' => '普通用户改名']);

    // activity_log 表中不应有 subject_type 为 User 的记录
    expect(
        Activity::where('subject_type', \App\Models\User::class)->count()
    )->toBe(0);
});
```

> 说明：此处使用 `\App\Models\User::factory()` 是**有意为之**（验证默认 users 表模型未启用日志），与“后台业务必须用 `AdminUser::factory()`” 的约定不冲突。

- [ ] **Step 2: 确认 `app/Models/User.php` 不包含 LogsActivity**

```bash
grep -n "LogsActivity" /home/john/projects/personal/filament-admin/app/Models/User.php
```

预期：无输出（User 不使用 LogsActivity）。若 User 没有 factory，先创建：

```bash
php artisan make:factory UserFactory --model=User
```

- [ ] **Step 3: 运行测试**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --filter="未加 LogsActivity Trait"
```

预期：PASS。

- [ ] **Step 4: 提交**

```bash
git add tests/Feature/ActivityLog/ActivityLogTest.php
git commit -m "test: 验证未加 LogsActivity Trait 的 Model 不产生日志"
```

---

## Task 4: 创建 ActivityLogPolicy（只读访问控制）

**Files:**
- Create: `app/Policies/ActivityLogPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`

- [ ] **Step 1: 编写失败测试**

在 `tests/Feature/ActivityLog/ActivityLogTest.php` 末尾追加：

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // 避免 Spatie Permission 缓存跨测试污染
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('普通管理员无 view_any_activity_log 权限时不能访问日志列表', function () {
    $user = AdminUser::factory()->create();

    actingAs($user, 'admin');

    expect($user->can('viewAny', Activity::class))->toBeFalse();
});

it('拥有 view_any_activity_log 权限的管理员可访问日志列表', function () {
    Permission::create(['name' => 'view_any_activity_log', 'guard_name' => 'admin']);

    $user = AdminUser::factory()->create();
    $user->givePermissionTo('view_any_activity_log');

    actingAs($user, 'admin');

    expect($user->can('viewAny', Activity::class))->toBeTrue();
});

it('任何管理员都不可更新或删除操作日志', function () {
    Permission::create(['name' => 'view_any_activity_log', 'guard_name' => 'admin']);

    $user = AdminUser::factory()->create();
    $user->givePermissionTo('view_any_activity_log');

    actingAs($user, 'admin');

    $activity = Activity::create([
        'log_name'    => 'filament-admin',
        'description' => 'created',
        'subject_type' => AdminUser::class,
        'subject_id'  => $user->id,
        'causer_type' => AdminUser::class,
        'causer_id'   => $user->id,
        'properties'  => '{}',
    ]);

    expect($user->can('update', $activity))->toBeFalse()
        ->and($user->can('delete', $activity))->toBeFalse()
        ->and($user->can('restore', $activity))->toBeFalse()
        ->and($user->can('forceDelete', $activity))->toBeFalse();
});
```

- [ ] **Step 2: 运行测试，确认失败**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --filter="管理员"
```

预期：FAIL（Policy 未注册）。

- [ ] **Step 3: 创建 `app/Policies/ActivityLogPolicy.php`**

```php
<?php

namespace App\Policies;

use App\Models\AdminUser;
use Spatie\Activitylog\Models\Activity;

/**
 * 操作日志策略
 *
 * 日志为只读资源：
 * - 仅超管或拥有 `view_any_activity_log` 权限的管理员可查看
 * - 禁止任何写操作（更新/删除/恢复/强制删除），清理交由 `activitylog:clean` 命令
 *
 * 继承 BasePolicy 以复用超管绕过逻辑。
 */
class ActivityLogPolicy extends BasePolicy
{
    /**
     * 是否可查看日志列表
     */
    public function viewAny(AdminUser $user): bool
    {
        return $user->can('view_any_activity_log');
    }

    /**
     * 是否可查看单条日志详情
     */
    public function view(AdminUser $user, Activity $activity): bool
    {
        return $user->can('view_any_activity_log');
    }

    /**
     * 禁止创建（日志由系统自动写入）
     */
    public function create(AdminUser $user): bool
    {
        return false;
    }

    /**
     * 禁止更新
     */
    public function update(AdminUser $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * 禁止删除（清理交由调度命令）
     */
    public function delete(AdminUser $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * 禁止恢复
     */
    public function restore(AdminUser $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * 禁止强制删除
     */
    public function forceDelete(AdminUser $user, Activity $activity): bool
    {
        return false;
    }
}
```

- [ ] **Step 4: 在 `AuthServiceProvider` 注册 Policy**

打开 `app/Providers/AuthServiceProvider.php`（Laravel 11+ 无框架基类，需 `extends Illuminate\Support\ServiceProvider`，在 `boot()` 内调用 `Gate::policy()`）。

在 `$policies` 数组（若以数组形式集中管理）中添加：

```php
use App\Policies\ActivityLogPolicy;
use Spatie\Activitylog\Models\Activity;

protected array $policies = [
    // ...其他 policy...
    Activity::class => ActivityLogPolicy::class,
];

/**
 * 启动应用服务
 */
public function boot(): void
{
    foreach ($this->policies as $model => $policy) {
        Gate::policy($model, $policy);
    }
}
```

若项目已有不同的注册风格，沿用现有风格，**确保 `Activity::class => ActivityLogPolicy::class` 被 `Gate::policy()` 注册**。

- [ ] **Step 5: 运行测试，确认通过**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --filter="管理员"
```

预期：PASS。

- [ ] **Step 6: 提交**

```bash
git add app/Policies/ActivityLogPolicy.php app/Providers/AuthServiceProvider.php tests/Feature/ActivityLog/ActivityLogTest.php
git commit -m "feat: 新增 ActivityLogPolicy（只读访问控制，禁止任何写操作）"
```

---

## Task 5: 创建 ActivityLogResource（只读管理界面）

**Files:**
- Create: `app/Filament/Resources/ActivityLogResource.php`
- Create: `app/Filament/Resources/ActivityLogResource/Pages/ListActivityLogs.php`
- Create: `resources/views/filament/resources/activity-log/detail.blade.php`

> Filament 5 API 注意事项：表单使用 `Filament\Schemas\Schema` + `->components([])`，**不要**用 Filament 3.x 的 `Forms\Form` + `->schema([])`。参考 `app/Filament/Pages/Auth/Login.php`。

- [ ] **Step 1: 创建目录结构**

```bash
mkdir -p /home/john/projects/personal/filament-admin/app/Filament/Resources/ActivityLogResource/Pages
mkdir -p /home/john/projects/personal/filament-admin/resources/views/filament/resources/activity-log
```

- [ ] **Step 2: 创建 `app/Filament/Resources/ActivityLogResource.php`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Models\AdminUser;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * 操作日志 Resource（只读）
 *
 * 展示系统中所有 opt-in 模型的操作记录，仅显示由 admin guard 用户
 * （causer_type = App\Models\AdminUser）触发的日志，不提供创建/编辑功能。
 */
class ActivityLogResource extends Resource
{
    /** @var class-string<Activity> */
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = '操作日志';

    protected static ?string $modelLabel = '操作日志';

    protected static ?string $pluralModelLabel = '操作日志';

    protected static ?string $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 90;

    /**
     * Filament 5 Schema API：只读 Resource 无需表单字段
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * 全局查询：按 causer_type 过滤，只展示后台管理员触发的日志
     *
     * 多态字段 causer_type 在系统中可能存在多种值（如未来扩展前台用户），
     * 本 Resource 只展示 admin guard 来源的日志。
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('causer_type', AdminUser::class);
    }

    /**
     * 表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('causer.name')
                    ->label('操作人')
                    ->searchable()
                    ->sortable()
                    ->default('系统'),

                TextColumn::make('subject_type')
                    ->label('操作对象类型')
                    ->formatStateUsing(fn (?string $state) => $state
                        ? class_basename($state)
                        : '—')
                    ->sortable(),

                TextColumn::make('subject_id')
                    ->label('对象 ID')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('操作类型')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default    => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('操作时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('description')
                    ->label('操作类型')
                    ->options([
                        'created' => '创建',
                        'updated' => '更新',
                        'deleted' => '删除',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('查看详情')
                    ->modalHeading('操作日志详情')
                    ->modalContent(fn (Activity $record) => view(
                        'filament.resources.activity-log.detail',
                        ['record' => $record]
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    /**
     * 只注册列表页，禁用 Create/Edit/View 路由
     *
     * @return array<string, class-string>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
```

- [ ] **Step 3: 创建 `app/Filament/Resources/ActivityLogResource/Pages/ListActivityLogs.php`**

```php
<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * 操作日志列表页
 */
class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    /**
     * 不显示“新建”按钮（只读页面）
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 4: 创建 ViewAction 使用的 Blade 视图**

新建 `resources/views/filament/resources/activity-log/detail.blade.php`：

```blade
<div class="space-y-4 p-4">
    <div>
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">基本信息</h3>
        <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
            <dt class="text-gray-500">操作人</dt>
            <dd>{{ $record->causer?->name ?? '系统' }}</dd>
            <dt class="text-gray-500">对象类型</dt>
            <dd>{{ $record->subject_type ? class_basename($record->subject_type) : '—' }}</dd>
            <dt class="text-gray-500">对象 ID</dt>
            <dd>{{ $record->subject_id ?? '—' }}</dd>
            <dt class="text-gray-500">操作类型</dt>
            <dd>{{ $record->description }}</dd>
            <dt class="text-gray-500">操作时间</dt>
            <dd>{{ $record->created_at->format('Y-m-d H:i:s') }}</dd>
        </dl>
    </div>

    @if($record->properties->isNotEmpty())
        @if($record->properties->has('old'))
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">变更前</h3>
                <pre class="mt-1 overflow-auto rounded bg-gray-100 p-2 text-xs dark:bg-gray-800">{{ json_encode($record->properties->get('old'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif

        @if($record->properties->has('attributes'))
            <div>
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">变更后 / 当前值</h3>
                <pre class="mt-1 overflow-auto rounded bg-gray-100 p-2 text-xs dark:bg-gray-800">{{ json_encode($record->properties->get('attributes'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif
    @else
        <p class="text-sm text-gray-400">无属性变更记录。</p>
    @endif
</div>
```

- [ ] **Step 5: 在 AdminPanelProvider 中确认 Resource 已注册**

打开 `app/Providers/Filament/AdminPanelProvider.php`，确认存在 `->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')` 自动发现；若使用显式 `->resources([...])`，则添加：

```php
\App\Filament\Resources\ActivityLogResource::class,
```

验证方法：

```bash
grep -n "discoverResources\|ActivityLogResource" app/Providers/Filament/AdminPanelProvider.php
```

- [ ] **Step 6: 编写 causer_type 过滤测试**

在 `tests/Feature/ActivityLog/ActivityLogTest.php` 追加：

```php
it('ActivityLogResource 只展示 causer_type 为 AdminUser 的日志', function () {
    $admin = AdminUser::factory()->create();

    // 模拟 admin guard 操作产生的日志
    Activity::create([
        'log_name'    => 'filament-admin',
        'description' => 'updated',
        'subject_type' => AdminUser::class,
        'subject_id'  => $admin->id,
        'causer_type' => AdminUser::class,
        'causer_id'   => $admin->id,
        'properties'  => '{}',
    ]);

    // 模拟其他来源（如前台 User）的日志
    Activity::create([
        'log_name'    => 'frontend',
        'description' => 'created',
        'subject_type' => \App\Models\User::class,
        'subject_id'  => 1,
        'causer_type' => \App\Models\User::class,
        'causer_id'   => 1,
        'properties'  => '{}',
    ]);

    $query = \App\Filament\Resources\ActivityLogResource::getEloquentQuery();

    expect($query->count())->toBe(1)
        ->and($query->first()->causer_type)->toBe(AdminUser::class);
});
```

- [ ] **Step 7: 验证页面可访问**

```bash
php artisan route:list | grep activity-log
```

预期：看到 `GET admin/activity-logs` 路由。

- [ ] **Step 8: 提交**

```bash
git add app/Filament/Resources/ActivityLogResource.php \
        app/Filament/Resources/ActivityLogResource/ \
        resources/views/filament/resources/activity-log/ \
        tests/Feature/ActivityLog/ActivityLogTest.php
git commit -m "feat: 新增只读 ActivityLogResource（Filament 5 Schema API，按 causer_type 过滤）"
```

---

## Task 6: 注册日志清理调度（复用 Spatie 自带命令）

**Files:**
- Modify: `routes/console.php`

> **关键约定**：Spatie 包自带 `activitylog:clean` 命令，**不要自造清理命令**。仅在 `routes/console.php` 中通过 `Schedule` Facade 注册每日调度。Laravel 11+ 已无 `app/Console/Kernel.php`，**严禁**在该位置注册。

> 保留天数读取顺序：Spatie 自带命令读取的是 `config('activitylog.delete_records_older_than_days')`。本项目通过一个轻量服务 Provider 或 boot 钩子在运行时根据 `GeneralSettings` 回填该 config 值，回退顺序：`Settings → config('filament-admin.log_retention_days') → 90`。

- [ ] **Step 1: 在 `AppServiceProvider::boot()` 中按回退顺序设置 activitylog 保留天数**

打开 `app/Providers/AppServiceProvider.php`，在 `boot()` 方法中追加：

```php
use Illuminate\Support\Facades\Config;

/**
 * 启动应用服务
 */
public function boot(): void
{
    // ...其他启动逻辑...

    // 日志保留天数回退顺序：GeneralSettings → config(filament-admin) → 90
    $days = null;

    try {
        $settings = app(\App\Settings\GeneralSettings::class);
        $days     = $settings->log_retention_days ?? null;
    } catch (\Throwable $e) {
        // Settings 尚未发布或数据库不可用时静默回退
        $days = null;
    }

    $days ??= config('filament-admin.log_retention_days', 90);

    Config::set('activitylog.delete_records_older_than_days', (int) $days);
}
```

- [ ] **Step 2: 修改 `routes/console.php`，注册调度**

打开 `routes/console.php`，确保顶部已有：

```php
use Illuminate\Support\Facades\Schedule;
```

在文件末尾追加：

```php
/*
 * 每日清理超期操作日志（使用 Spatie 自带 activitylog:clean 命令）
 *
 * 保留天数由 AppServiceProvider::boot() 按回退顺序写入
 * config('activitylog.delete_records_older_than_days')。
 */
Schedule::command('activitylog:clean')->daily();
```

- [ ] **Step 3: 验证调度已注册**

```bash
php artisan schedule:list
```

预期：输出列表中包含 `activitylog:clean`，频率为 `Daily`。

- [ ] **Step 4: 手动验证命令可执行**

```bash
php artisan activitylog:clean --days=365
```

预期：无异常退出。

- [ ] **Step 5: 提交**

```bash
git add app/Providers/AppServiceProvider.php routes/console.php
git commit -m "feat: 注册 activitylog:clean 每日调度，按回退顺序读取保留天数"
```

---

## Task 7: 扩展登录事件监听（可选，复用自动发现）

**Files:**
- Create（按需）：`app/Listeners/LogAdminLogout.php`
- Create（按需）：`app/Listeners/LogFailedAdminLogin.php`

> **重要前提**：`app/Listeners/LogAdminLogin.php` 已在 phase-1 实现并通过 Laravel **自动发现机制**注册，本域**禁止重复实现登录监听器**，也**禁止**在 `AppServiceProvider` 手动 `Event::listen`。

> 是否新增登出/登录失败监听器视产品需求决定。若新增，遵循同样的自动发现约定：仅创建 Listener 类，无需手动注册。

- [ ] **Step 1: 评估是否需要新增监听器**

若产品规格未要求记录登出/失败登录，**跳过本 Task**。

- [ ] **Step 2（如需要）: 创建 `app/Listeners/LogAdminLogout.php`**

```php
<?php

namespace App\Listeners;

use Filament\Auth\Events\Logout;

/**
 * 记录管理员登出事件
 *
 * 由 Laravel 自动发现机制注册，无需在任何 ServiceProvider 中手动 Event::listen。
 */
class LogAdminLogout
{
    /**
     * 处理事件
     */
    public function handle(Logout $event): void
    {
        // 此处写入 LoginLog 或 activity_log 视团队约定
    }
}
```

- [ ] **Step 3（如需要）: 创建 `app/Listeners/LogFailedAdminLogin.php`**

```php
<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;

/**
 * 记录管理员登录失败事件
 *
 * 由 Laravel 自动发现机制注册。仅处理 guard 为 'admin' 的失败事件。
 */
class LogFailedAdminLogin
{
    /**
     * 处理事件
     */
    public function handle(Failed $event): void
    {
        if ($event->guard !== 'admin') {
            return;
        }

        // 写入 LoginLog 表
    }
}
```

- [ ] **Step 4（如需要）: 提交**

```bash
git add app/Listeners/
git commit -m "feat: 扩展管理员登出/登录失败监听（自动发现注册）"
```

---

## Task 8: 补全测试并验证覆盖率

**Files:**
- Modify: `tests/Feature/ActivityLog/ActivityLogTest.php`

- [ ] **Step 1: 运行全部操作日志测试**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php
```

预期：全部 PASS。

- [ ] **Step 2: 确认测试覆盖核心路径**

当前测试文件应包含以下用例：

| 用例描述 | 覆盖路径 |
|---------|---------|
| 更新 AdminUser 后自动生成操作日志记录 | LogsActivity Trait 正常工作 |
| 未加 LogsActivity Trait 的 User 不产生日志 | opt-in 策略验证 |
| 普通管理员无权限不能访问日志列表 | Policy viewAny 拒绝 |
| 拥有 view_any_activity_log 权限可访问 | Policy viewAny 通过 |
| 任何管理员都不可更新或删除操作日志 | Policy 写操作全部禁止 |
| ActivityLogResource 只展示 admin 来源日志 | causer_type 过滤 |

可选补充：

```php
/**
 * 创建 AdminUser 时产生 created 日志
 */
it('创建 AdminUser 时产生 created 操作日志', function () {
    $user = AdminUser::factory()->create();

    $log = Activity::where('subject_type', AdminUser::class)
        ->where('subject_id', $user->id)
        ->where('description', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('created');
});
```

- [ ] **Step 3: 运行完整测试套件，确认无回归**

```bash
composer test
```

预期：全部通过，无新失败。

- [ ] **Step 4: 运行 Pint 格式化**

```bash
composer pint
```

- [ ] **Step 5: 运行 PHPStan**

```bash
composer phpstan
```

预期：无新增 error。

- [ ] **Step 6: 提交**

```bash
git add tests/Feature/ActivityLog/ActivityLogTest.php
git commit -m "test: 完善操作日志核心路径测试"
```

---

## Task 9: 编写功能文档

**Files:**
- Create: `docs/features/activity-log.md`
- Create/Modify: `docs/development/conventions.md`

- [ ] **Step 1: 创建 `docs/features/activity-log.md`**

```bash
mkdir -p /home/john/projects/personal/filament-admin/docs/features
```

内容如下：

```markdown
# 操作日志功能

## 概述

系统使用 `spatie/laravel-activitylog` 实现操作日志记录。采用 **opt-in 策略**：只有显式在 Model 中使用 `LogsActivity` Trait 的模型，才会记录操作日志。

后台 `ActivityLogResource` 仅展示由 admin guard 用户触发的日志（按 `causer_type = App\Models\AdminUser` 过滤）。

## 已启用日志的模型

| 模型 | 文件 | 说明 |
|------|------|------|
| AdminUser | `app/Models/AdminUser.php` | 记录管理员用户的创建、更新、删除，排除密码等敏感字段 |

## 管理界面

进入 Filament 管理后台 → 系统管理 → 操作日志，可查看：

- 操作人（管理员用户名）
- 操作对象类型和 ID
- 操作类型（created / updated / deleted）
- 操作时间

点击“查看详情”可查看原始 JSON diff（变更前后的字段值）。

访问需 `view_any_activity_log` 权限或超管身份。日志为只读资源，禁止任何更新/删除操作。

## 日志清理

系统每日自动执行 Spatie 自带的 `activitylog:clean` 命令。保留天数按以下顺序回退：

1. `App\Settings\GeneralSettings::$log_retention_days`
2. `config('filament-admin.log_retention_days')`
3. 默认 90 天

也可手动执行：

```bash
# 使用配置中的保留天数
php artisan activitylog:clean

# 指定保留天数（覆盖配置）
php artisan activitylog:clean --days=30
```

## 注意事项

- 活动日志存储在 `activity_log` 表中
- `subject_type` / `causer_type` 记录模型类名（含命名空间），界面展示时自动截取类名
- 操作人（causer）为当前登录的 AdminUser；若由系统触发，则 causer 为 null，界面显示“系统”
```

- [ ] **Step 2: 创建或更新 `docs/development/conventions.md`**

```bash
mkdir -p /home/john/projects/personal/filament-admin/docs/development
```

内容（追加到文件末尾或作为新文件内容）：

```markdown
## 如何为业务 Model 启用操作日志

系统操作日志采用 **opt-in** 策略，默认不记录任何模型。需为某个 Model 启用日志时，按以下步骤操作：

### 1. 添加 Trait

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class YourModel extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }
}
```

### 2. 排除敏感字段（可选）

```php
return LogOptions::defaults()
    ->logAll()
    ->dontLogIfAttributesChangedOnly(['password', 'token'])
    ->dontSubmitEmptyLogs()
    ->setDescriptionForEvent(fn (string $eventName) => $eventName);
```

### 3. 仅记录指定字段（可选）

```php
return LogOptions::defaults()
    ->logOnly(['name', 'email', 'status'])
    ->dontSubmitEmptyLogs()
    ->setDescriptionForEvent(fn (string $eventName) => $eventName);
```

### 4. 验证

在 `tests/Feature/` 下编写 Pest 测试，使用 `AdminUser::factory()` 与 `actingAs($user, 'admin')`，验证 CRUD 操作会产生对应的 `created`/`updated`/`deleted` 日志记录。
```

- [ ] **Step 3: 提交文档**

```bash
git add docs/features/activity-log.md docs/development/conventions.md
git commit -m "docs: 新增操作日志功能文档和 Model 启用日志规范"
```

---

## Task 10: 打版本 Tag

- [ ] **Step 1: 确认所有测试通过**

```bash
composer test
```

预期：全部 PASS。

- [ ] **Step 2: 确认无未提交变更**

```bash
git status
```

预期：工作区干净（nothing to commit）。

- [ ] **Step 3: 打 Tag**

```bash
git tag -a v0.7.0-操作日志 -m "v0.7.0-操作日志: 实现操作日志功能（spatie/laravel-activitylog，opt-in 策略，只读管理界面 + Policy 控制，Spatie 自带命令每日清理）"
```

- [ ] **Step 4: 验证 Tag**

```bash
git tag -l | grep v0.7.0-操作日志
git show v0.7.0-操作日志 --stat
```

预期：Tag 存在，显示包含此次功能相关提交的摘要。

---

## 附录：兼容性与设计说明

### 为何不使用 pxlrbt/filament-activity-log

本计划中 ViewAction 使用自定义 Blade 视图实现 JSON diff 展示，未引入 `pxlrbt/filament-activity-log`，原因：

1. 该包对 Filament 5 的官方兼容性需执行时确认。
2. 自定义 Blade 视图实现简单、可控，避免额外依赖。

### 为何不自造清理命令

Spatie 包已提供 `activitylog:clean` 命令，自动读取 `config('activitylog.delete_records_older_than_days')`。本项目通过 `AppServiceProvider::boot()` 按回退顺序在运行时设置该 config 值，从而让 Spatie 命令读取到正确的保留天数。

### 登录监听复用

`app/Listeners/LogAdminLogin.php` 在 phase-1 已通过 Laravel 自动发现注册，本域不重复实现。如需扩展登出/失败登录监听，同样依赖自动发现，禁止在 ServiceProvider 中手动 `Event::listen`。
