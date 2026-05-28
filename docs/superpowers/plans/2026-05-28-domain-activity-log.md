# 操作日志 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现 opt-in 操作日志系统，基于 Spatie ActivityLog，只有显式加了 LogsActivity Trait 的 Model 才记录操作日志，提供只读管理界面和定期清理机制。

**Architecture:** 使用 spatie/laravel-activitylog 记录 Model 的 created/updated/deleted 事件。ActivityLogResource 提供只读查询界面，包含操作人、操作对象、操作类型、时间列，以及 JSON diff 详情查看。CleanActivityLog 命令读取 GeneralSettings 中的 log_retention_days 配置，通过 Laravel Scheduler 每日执行。opt-in 策略确保只有显式使用 LogsActivity Trait 的模型才会产生日志记录。

**Tech Stack:** spatie/laravel-activitylog ^4.0, pxlrbt/filament-activity-log ^2.0（若不兼容 Filament 5 则降级为手动实现 ViewAction JSON diff），Pest

---

## 文件结构

| 文件 | 操作 | 职责 |
|------|------|------|
| `composer.json` | 修改 | 添加 spatie/laravel-activitylog 依赖 |
| `config/activitylog.php` | 新建（发布后修改） | 配置默认日志名、enabled、数据库连接 |
| `database/migrations/xxxx_create_activity_log_table.php` | 新建（vendor:publish） | 创建 activity_log 表 |
| `app/Models/AdminUser.php` | 修改 | 添加 LogsActivity + CausesActivity Trait |
| `app/Filament/Resources/ActivityLogResource.php` | 新建 | 只读 Filament Resource，展示操作日志列表 |
| `app/Filament/Resources/ActivityLogResource/Pages/ListActivityLogs.php` | 新建 | 日志列表页，含 ViewAction JSON diff |
| `app/Console/Commands/CleanActivityLog.php` | 新建 | 清理超期操作日志命令 |
| `app/Console/Commands/CleanLoginLogs.php` | 新建 | 清理超期登录日志命令 |
| `routes/console.php` | 修改 | 注册每日清理调度任务 |
| `tests/Feature/ActivityLog/ActivityLogTest.php` | 新建 | 核心路径测试 |
| `docs/features/activity-log.md` | 新建 | 功能使用文档 |
| `docs/development/conventions.md` | 新建/修改 | 补充"如何为业务 Model 启用日志"段落 |

---

## Task 1: 安装 spatie/laravel-activitylog 并发布配置

**Files:**
- Modify: `composer.json`（通过 composer require）
- Create: `config/activitylog.php`（vendor:publish 后修改）
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

预期：`config/activitylog.php` 被创建。

- [ ] **Step 4: 修改 `config/activitylog.php`**

打开 `config/activitylog.php`，将关键配置修改为以下内容：

```php
<?php

return [
    /*
     * 是否启用操作日志。设置为 false 时，所有日志记录均被禁用。
     */
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    /*
     * 删除旧日志时，使用此方法。
     * 可用值: 'delete'（物理删除）、'forceDelete'（强制删除）
     */
    'delete_records_older_than_days' => 365,

    /*
     * 默认日志名称
     */
    'default_log_name' => 'default',

    /*
     * 存储 Activity 的模型类
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * 数据库连接（null 表示使用默认连接）
     */
    'database_connection' => env('ACTIVITY_LOG_DB_CONNECTION', null),
];
```

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

## Task 3: 添加"未加 Trait 的 Model 不产生日志"测试

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

- [ ] **Step 2: 确认 `app/Models/User.php` 不包含 LogsActivity**

```bash
grep -n "LogsActivity" /home/john/projects/personal/filament-admin/app/Models/User.php
```

预期：无输出（User 不使用 LogsActivity）。若 User 没有 factory，先检查：

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

## Task 4: 创建 ActivityLogResource（只读管理界面）

**Files:**
- Create: `app/Filament/Resources/ActivityLogResource.php`
- Create: `app/Filament/Resources/ActivityLogResource/Pages/ListActivityLogs.php`

- [ ] **Step 1: 创建目录结构**

```bash
mkdir -p /home/john/projects/personal/filament-admin/app/Filament/Resources/ActivityLogResource/Pages
```

- [ ] **Step 2: 创建 `app/Filament/Resources/ActivityLogResource.php`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/**
 * 操作日志 Resource（只读）
 *
 * 展示系统中所有 opt-in 模型的操作记录，不提供创建/编辑功能。
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
     * 禁用表单（只读 Resource 不需要 Form）
     */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
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
     * 不显示"新建"按钮（只读页面）
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

创建目录并新建视图文件 `resources/views/filament/resources/activity-log/detail.blade.php`：

```bash
mkdir -p /home/john/projects/personal/filament-admin/resources/views/filament/resources/activity-log
```

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

- [ ] **Step 5: 在 AdminPanelProvider 中注册 ActivityLogResource**

打开 `app/Providers/Filament/AdminPanelProvider.php`，在 `->resources([...])` 数组中添加：

```php
\App\Filament\Resources\ActivityLogResource::class,
```

若文件中使用 `discoverResources()` 自动发现，则无需手动注册，跳过此步骤。

验证方法：

```bash
grep -n "discoverResources\|ActivityLogResource" app/Providers/Filament/AdminPanelProvider.php
```

- [ ] **Step 6: 验证页面可访问**

```bash
php artisan route:list | grep activity-log
```

预期：看到 `GET admin/activity-logs` 路由。

- [ ] **Step 7: 提交**

```bash
git add app/Filament/Resources/ActivityLogResource.php \
        app/Filament/Resources/ActivityLogResource/ \
        resources/views/filament/resources/activity-log/
git commit -m "feat: 新增只读 ActivityLogResource 操作日志管理界面"
```

---

## Task 5: 创建日志清理命令 CleanActivityLog

**Files:**
- Create: `app/Console/Commands/CleanActivityLog.php`

- [ ] **Step 1: 编写失败测试**

在 `tests/Feature/ActivityLog/ActivityLogTest.php` 末尾追加：

```php
use App\Console\Commands\CleanActivityLog;
use Illuminate\Support\Facades\Artisan;

/**
 * CleanActivityLog 命令删除超期日志
 */
it('CleanActivityLog 命令删除超过保留天数的日志', function () {
    // 制造超期日志（91 天前）
    $old = Activity::factory()->create([
        'created_at' => now()->subDays(91),
    ]);

    // 制造近期日志（1 天前）
    $recent = Activity::factory()->create([
        'created_at' => now()->subDays(1),
    ]);

    // 使用 --days=90 执行清理
    Artisan::call('filament-admin:clean-activity-log', ['--days' => 90]);

    expect(Activity::find($old->id))->toBeNull()
        ->and(Activity::find($recent->id))->not->toBeNull();
});
```

- [ ] **Step 2: 运行测试，确认失败**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --filter="CleanActivityLog 命令删除超过保留天数的日志"
```

预期：FAIL，命令不存在。

注意：Activity 默认没有 factory，需要在下一步确认或手动创建测试数据。若 Activity 无 factory，改用以下方式创建测试数据：

```php
// 不使用 factory，直接写入数据库
$old = \Spatie\Activitylog\Models\Activity::create([
    'log_name'    => 'default',
    'description' => 'created',
    'subject_type' => AdminUser::class,
    'subject_id'  => 1,
    'causer_type' => AdminUser::class,
    'causer_id'   => 1,
    'properties'  => '{}',
    'created_at'  => now()->subDays(91),
    'updated_at'  => now()->subDays(91),
]);
```

- [ ] **Step 3: 创建 `app/Console/Commands/CleanActivityLog.php`**

```php
<?php

namespace App\Console\Commands;

use App\Settings\GeneralSettings;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

/**
 * 清理超期操作日志命令
 *
 * 读取 GeneralSettings::$log_retention_days 作为默认保留天数。
 * 可通过 --days 选项覆盖。
 */
class CleanActivityLog extends Command
{
    /** @var string */
    protected $signature = 'filament-admin:clean-activity-log
                            {--days= : 保留天数，默认读取系统配置（GeneralSettings::log_retention_days）}';

    /** @var string */
    protected $description = '清理超过保留天数的操作日志';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? app(GeneralSettings::class)->log_retention_days ?? 90);

        if ($days <= 0) {
            $this->error('保留天数必须大于 0');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $count  = Activity::where('created_at', '<', $cutoff)->delete();

        $this->info("已清理 {$count} 条操作日志（保留 {$days} 天内的记录，截止时间：{$cutoff->toDateTimeString()}）");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: 运行测试，确认通过**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --filter="CleanActivityLog 命令删除超过保留天数的日志"
```

预期：PASS。

- [ ] **Step 5: 提交**

```bash
git add app/Console/Commands/CleanActivityLog.php tests/Feature/ActivityLog/ActivityLogTest.php
git commit -m "feat: 新增 filament-admin:clean-activity-log 操作日志清理命令"
```

---

## Task 6: 创建登录日志清理命令 CleanLoginLogs

**Files:**
- Create: `app/Console/Commands/CleanLoginLogs.php`

- [ ] **Step 1: 确认 login_logs 表结构**

```bash
php artisan db:show --table=login_logs 2>/dev/null || grep -r "login_logs\|LoginLog" database/migrations/ | head -10
```

预期：确认 login_logs 表有 `created_at` 字段。

- [ ] **Step 2: 创建 `app/Console/Commands/CleanLoginLogs.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\LoginLog;
use App\Settings\GeneralSettings;
use Illuminate\Console\Command;

/**
 * 清理超期登录日志命令
 *
 * 读取 GeneralSettings::$log_retention_days 作为默认保留天数。
 * 可通过 --days 选项覆盖。
 */
class CleanLoginLogs extends Command
{
    /** @var string */
    protected $signature = 'filament-admin:clean-login-logs
                            {--days= : 保留天数，默认读取系统配置（GeneralSettings::log_retention_days）}';

    /** @var string */
    protected $description = '清理超过保留天数的登录日志';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? app(GeneralSettings::class)->log_retention_days ?? 90);

        if ($days <= 0) {
            $this->error('保留天数必须大于 0');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $count  = LoginLog::where('created_at', '<', $cutoff)->delete();

        $this->info("已清理 {$count} 条登录日志（保留 {$days} 天内的记录，截止时间：{$cutoff->toDateTimeString()}）");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 3: 手动验证命令可执行**

```bash
php artisan filament-admin:clean-login-logs --days=365
```

预期：输出"已清理 X 条登录日志"，无异常。

- [ ] **Step 4: 提交**

```bash
git add app/Console/Commands/CleanLoginLogs.php
git commit -m "feat: 新增 filament-admin:clean-login-logs 登录日志清理命令"
```

---

## Task 7: 在 routes/console.php 注册每日清理调度

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: 修改 `routes/console.php`，追加调度注册**

打开 `routes/console.php`，在文件末尾追加：

```php
use Illuminate\Support\Facades\Schedule;

/*
 * 每日清理超期日志
 */
Schedule::command('filament-admin:clean-activity-log')->daily();
Schedule::command('filament-admin:clean-login-logs')->daily();
```

注意：若文件顶部已有 `use Illuminate\Support\Facades\Schedule;`，则不需重复导入。

- [ ] **Step 2: 验证调度已注册**

```bash
php artisan schedule:list
```

预期：输出列表中包含 `filament-admin:clean-activity-log` 和 `filament-admin:clean-login-logs`，频率为 `Daily`。

- [ ] **Step 3: 提交**

```bash
git add routes/console.php
git commit -m "feat: 注册操作日志和登录日志每日清理调度任务"
```

---

## Task 8: 补全测试并验证覆盖率

**Files:**
- Modify: `tests/Feature/ActivityLog/ActivityLogTest.php`

- [ ] **Step 1: 运行全部操作日志测试**

```bash
php artisan test tests/Feature/ActivityLog/ActivityLogTest.php --verbose
```

预期：全部 PASS（至少 3 个测试用例）。

- [ ] **Step 2: 确认测试覆盖核心路径**

当前测试文件应包含以下用例：

| 用例描述 | 覆盖路径 |
|---------|---------|
| 更新 AdminUser 后自动生成操作日志记录 | LogsActivity Trait 正常工作 |
| 未加 LogsActivity Trait 的 User 不产生日志 | opt-in 策略验证 |
| CleanActivityLog 命令删除超期日志 | 清理命令核心逻辑 |

若需要补充"创建 AdminUser 产生 created 日志"用例，追加：

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
php artisan test
```

预期：全部通过，无新失败。

- [ ] **Step 4: 提交**

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

点击"查看详情"可查看原始 JSON diff（变更前后的字段值）。

## 日志清理

系统每日自动执行清理，保留天数由**系统配置** → `log_retention_days` 控制（默认 90 天）。

也可手动执行：

```bash
# 使用系统配置的保留天数
php artisan filament-admin:clean-activity-log

# 指定保留天数（覆盖系统配置）
php artisan filament-admin:clean-activity-log --days=30
```

## 注意事项

- 活动日志存储在 `activity_log` 表中
- `subject_type` 记录模型类名（含命名空间），界面展示时自动截取类名
- 操作人（causer）为当前登录的 AdminUser；若由系统触发，则 causer 为 null，界面显示"系统"
```

- [ ] **Step 2: 创建或更新 `docs/development/conventions.md`**

若文件已存在，在文件末尾追加；若不存在，新建：

```bash
mkdir -p /home/john/projects/personal/filament-admin/docs/development
```

内容（追加到文件末尾或作为新文件内容）：

```markdown
## 如何为业务 Model 启用操作日志

系统操作日志采用 **opt-in** 策略，默认不记录任何模型。需为某个 Model 启用日志时，按以下步骤操作：

### 1. 添加 Trait

在 Model 文件中添加 `LogsActivity` Trait：

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class YourModel extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()                 // 记录所有字段变更
            ->dontSubmitEmptyLogs()    // 无变更时不写日志
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }
}
```

### 2. 排除敏感字段（可选）

若 Model 包含密码等敏感字段，使用 `dontLogIfAttributesChangedOnly` 排除：

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

在 `tests/Feature/` 下编写测试，验证 CRUD 操作会产生对应的 `created`/`updated`/`deleted` 日志记录。
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
php artisan test
```

预期：全部 PASS。

- [ ] **Step 2: 确认无未提交变更**

```bash
git status
```

预期：工作区干净（nothing to commit）。

- [ ] **Step 3: 打 Tag**

```bash
git tag -a v0.7.0-操作日志 -m "v0.7.0: 实现操作日志功能（spatie/laravel-activitylog，opt-in 策略，只读管理界面，定期清理）"
```

- [ ] **Step 4: 验证 Tag**

```bash
git tag -l | grep v0.7.0
git show v0.7.0-操作日志 --stat
```

预期：Tag 存在，显示包含此次功能相关提交的摘要。

---

## 附录：兼容性说明

### pxlrbt/filament-activity-log

本计划中 ViewAction 使用自定义 Blade 视图实现 JSON diff 展示，未直接依赖 `pxlrbt/filament-activity-log`，原因如下：

1. `pxlrbt/filament-activity-log ^2.0` 官方对 Filament 5 的支持状态需执行时确认
2. 如需使用该包，先执行：

```bash
composer require pxlrbt/filament-activity-log
```

若安装成功且与 Filament 5 兼容，可将 Task 4 中的 ViewAction 替换为该包提供的组件，并删除手写的 Blade 视图。

3. 若不兼容，使用本计划中的自定义 Blade 视图方案即可满足需求。
