# 插件系统 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现双轨制插件系统（工具型 package + 业务模块型 module），包含插件扫描、后台启停管理、Panel 层动态注册和远程插件市场浏览。

**Architecture:** plugins 表记录所有已安装插件的状态。scan-plugins 命令扫描 Composer 包（extra.filament-admin 字段）和 Modules/ 目录，写入 plugins 表。AdminPanelProvider 启动时根据 plugins.is_enabled 动态注册插件。远程市场从 GitHub Raw 读取 JSON 索引，网络失败时静默降级。

**Tech Stack:** nwidart/laravel-modules ^11.0, Pest

---

## Task 1: plugins 表迁移和 Plugin 模型

- [ ] 创建迁移文件 `database/migrations/xxxx_xx_xx_create_plugins_table.php`：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['package', 'module'])->default('package');
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('plugin_class')->nullable()->comment('Filament Plugin 类全限定名');
            $table->string('settings_class')->nullable()->comment('Settings 类全限定名');
            $table->json('requires')->nullable()->comment('依赖的其他插件 slug 列表');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
```

- [ ] 创建 Plugin 模型 `app/Models/Plugin.php`：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * 插件模型
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type  package|module
 * @property string|null $version
 * @property string|null $description
 * @property bool $is_enabled
 * @property string|null $plugin_class
 * @property string|null $settings_class
 * @property array|null $requires
 */
class Plugin extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'version',
        'description',
        'is_enabled',
        'plugin_class',
        'settings_class',
        'requires',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'requires'   => 'array',
    ];

    /**
     * 获取所有已启用的插件
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 获取所有 package 类型插件
     */
    public function scopePackages($query)
    {
        return $query->where('type', 'package');
    }

    /**
     * 获取所有 module 类型插件
     */
    public function scopeModules($query)
    {
        return $query->where('type', 'module');
    }
}
```

- [ ] 运行迁移：`php artisan migrate`

---

## Task 2: nwidart/laravel-modules 集成

- [ ] 安装依赖：

```bash
composer require nwidart/laravel-modules:^11.0
```

- [ ] 发布配置：

```bash
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"
```

- [ ] 在 `composer.json` 中添加模块自动加载（`psr-4` 部分）：

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Modules\\": "Modules/"
        }
    }
}
```

- [ ] 运行 `composer dump-autoload`

- [ ] 创建示例模块（用于验证）：

```bash
php artisan module:make Example
```

- [ ] `modules_statuses.json` 说明：
  - 该文件位于项目根目录，由 nwidart/laravel-modules 自动维护
  - 格式：`{"Example": true}` 表示 Example 模块已启用
  - **不要手动编辑**，统一通过 ScanPlugins 命令和 PluginResource 管理

- [ ] 创建 ModuleFilamentPlugin 基类 `app/Base/ModuleFilamentPlugin.php`：

```php
<?php

namespace App\Base;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Nwidart\Modules\Module;

/**
 * 模块型 Filament 插件基类
 *
 * 封装了 discoverResources/discoverPages/discoverWidgets 的自动注册逻辑，
 * 业务模块只需继承此类并实现 getModuleName() 即可。
 */
abstract class ModuleFilamentPlugin implements Plugin
{
    /**
     * 返回模块名称（对应 Modules/ 下的目录名）
     */
    abstract public function getModuleName(): string;

    public function getId(): string
    {
        return str($this->getModuleName())->snake()->value();
    }

    public function register(Panel $panel): void
    {
        $module = app('modules')->find($this->getModuleName());
        if (! $module instanceof Module) {
            return;
        }

        $basePath = $module->getPath();
        $baseNamespace = "Modules\\{$this->getModuleName()}";

        $panel
            ->discoverResources(
                in: "{$basePath}/app/Filament/Resources",
                for: "{$baseNamespace}\\Filament\\Resources"
            )
            ->discoverPages(
                in: "{$basePath}/app/Filament/Pages",
                for: "{$baseNamespace}\\Filament\\Pages"
            )
            ->discoverWidgets(
                in: "{$basePath}/app/Filament/Widgets",
                for: "{$baseNamespace}\\Filament\\Widgets"
            );
    }

    public function boot(Panel $panel): void {}
}
```

---

## Task 3: scan-plugins 命令

- [ ] 创建命令 `app/Console/Commands/ScanPlugins.php`：

```php
<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 扫描并同步插件信息到数据库
 */
class ScanPlugins extends Command
{
    protected $signature = 'plugins:scan {--force : 强制更新所有插件信息}';

    protected $description = '扫描 Composer 包和 Modules/ 目录，将插件信息写入 plugins 表';

    public function handle(): int
    {
        $this->info('开始扫描插件...');

        $scanned = 0;

        $scanned += $this->scanComposerPackages();
        $scanned += $this->scanModules();

        $this->info("扫描完成，共发现 {$scanned} 个插件。");

        return self::SUCCESS;
    }

    /**
     * 扫描 composer.lock 中含 extra.filament-admin 字段的包
     */
    private function scanComposerPackages(): int
    {
        $lockFile = base_path('composer.lock');

        if (! File::exists($lockFile)) {
            $this->warn('未找到 composer.lock，跳过 Composer 包扫描。');
            return 0;
        }

        $lock = json_decode(File::get($lockFile), true);
        $packages = array_merge(
            $lock['packages'] ?? [],
            $lock['packages-dev'] ?? []
        );

        $count = 0;

        foreach ($packages as $package) {
            $extra = $package['extra']['filament-admin'] ?? null;
            if ($extra === null) {
                continue;
            }

            $slug = str($package['name'])->slug()->value();

            Plugin::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'         => $extra['name'] ?? $package['name'],
                    'type'         => 'package',
                    'version'      => $package['version'] ?? null,
                    'description'  => $package['description'] ?? $extra['description'] ?? null,
                    'plugin_class' => $extra['plugin_class'] ?? null,
                    'settings_class' => $extra['settings_class'] ?? null,
                    'requires'     => $extra['requires'] ?? null,
                ]
            );

            $this->line("  [package] {$package['name']} ({$package['version']})");
            $count++;
        }

        return $count;
    }

    /**
     * 扫描 Modules/ 目录下的模块
     */
    private function scanModules(): int
    {
        $modulesPath = base_path('Modules');

        if (! File::isDirectory($modulesPath)) {
            $this->warn('未找到 Modules/ 目录，跳过模块扫描。');
            return 0;
        }

        $moduleStatuses = $this->getModuleStatuses();
        $count = 0;

        foreach (File::directories($modulesPath) as $moduleDir) {
            $moduleName = basename($moduleDir);
            $composerJson = $moduleDir . '/composer.json';

            $meta = File::exists($composerJson)
                ? json_decode(File::get($composerJson), true)
                : [];

            $slug = str($moduleName)->snake()->replace('_', '-')->value();

            Plugin::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'         => $meta['extra']['filament-admin']['name'] ?? $moduleName,
                    'type'         => 'module',
                    'version'      => $meta['version'] ?? null,
                    'description'  => $meta['description'] ?? null,
                    'plugin_class' => $meta['extra']['filament-admin']['plugin_class'] ?? null,
                    'settings_class' => $meta['extra']['filament-admin']['settings_class'] ?? null,
                    'requires'     => $meta['extra']['filament-admin']['requires'] ?? null,
                    'is_enabled'   => $moduleStatuses[$moduleName] ?? false,
                ]
            );

            $this->line("  [module] {$moduleName}");
            $count++;
        }

        return $count;
    }

    /**
     * 读取 modules_statuses.json
     */
    private function getModuleStatuses(): array
    {
        $statusFile = base_path('modules_statuses.json');

        if (! File::exists($statusFile)) {
            return [];
        }

        return json_decode(File::get($statusFile), true) ?? [];
    }
}
```

- [ ] 注册命令（Laravel 11+ 自动发现，无需手动注册，确认 `app/Console/Commands/` 在自动扫描路径中）

- [ ] 测试命令：`php artisan plugins:scan`

---

## Task 4: PluginResource

- [ ] 创建 `app/Filament/Resources/PluginResource.php`：

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PluginResource\Pages;
use App\Models\Plugin;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;

/**
 * 插件管理 Resource
 */
class PluginResource extends Resource
{
    protected static ?string $model = Plugin::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = '插件管理';

    protected static ?string $modelLabel = '插件';

    protected static ?string $navigationGroup = '系统管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('插件名称')
                    ->required(),
                Forms\Components\TextInput::make('slug')
                    ->label('标识符')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('类型')
                    ->options([
                        'package' => 'Composer 包',
                        'module'  => '业务模块',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('version')
                    ->label('版本'),
                Forms\Components\Textarea::make('description')
                    ->label('描述'),
                Forms\Components\TextInput::make('plugin_class')
                    ->label('Plugin 类'),
                Forms\Components\TextInput::make('settings_class')
                    ->label('Settings 类'),
                Forms\Components\Toggle::make('is_enabled')
                    ->label('已启用'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('插件名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('类型')
                    ->colors([
                        'primary' => 'package',
                        'success' => 'module',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'package' => 'Composer 包',
                        'module'  => '业务模块',
                        default   => $state,
                    }),
                Tables\Columns\TextColumn::make('version')
                    ->label('版本')
                    ->placeholder('未知'),
                Tables\Columns\ToggleColumn::make('is_enabled')
                    ->label('已启用')
                    ->beforeStateUpdated(function (Plugin $record, bool $state): void {
                        // 禁用时检查依赖
                        if ($state === false) {
                            static::checkDependencies($record);
                        }
                    })
                    ->afterStateUpdated(function (Plugin $record, bool $state): void {
                        if ($record->type === 'module') {
                            static::syncModuleStatus($record->name ?? $record->slug, $state);
                        }
                    }),
                Tables\Columns\TextColumn::make('settings_class')
                    ->label('Settings 类')
                    ->placeholder('无')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        'package' => 'Composer 包',
                        'module'  => '业务模块',
                    ]),
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('状态'),
            ])
            ->actions([
                Tables\Actions\Action::make('enable')
                    ->label('启用')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Plugin $record): bool => ! $record->is_enabled)
                    ->action(function (Plugin $record): void {
                        $record->update(['is_enabled' => true]);

                        if ($record->type === 'module') {
                            static::syncModuleStatus($record->name ?? $record->slug, true);
                        }

                        Notification::make()
                            ->title("插件 {$record->name} 已启用")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('disable')
                    ->label('禁用')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Plugin $record): bool => $record->is_enabled)
                    ->requiresConfirmation()
                    ->action(function (Plugin $record): void {
                        try {
                            static::checkDependencies($record);
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('无法禁用')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->update(['is_enabled' => false]);

                        if ($record->type === 'module') {
                            static::syncModuleStatus($record->name ?? $record->slug, false);
                        }

                        Notification::make()
                            ->title("插件 {$record->name} 已禁用")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * 检查是否有其他已启用插件依赖此插件
     *
     * @throws \RuntimeException 若存在依赖则抛出异常
     */
    protected static function checkDependencies(Plugin $plugin): void
    {
        $dependents = Plugin::where('is_enabled', true)
            ->whereNotNull('requires')
            ->get()
            ->filter(fn (Plugin $p): bool => in_array($plugin->slug, $p->requires ?? [], true));

        if ($dependents->isNotEmpty()) {
            $names = $dependents->pluck('name')->join('、');
            throw new \RuntimeException(
                "以下已启用插件依赖于此插件，请先禁用它们：{$names}"
            );
        }
    }

    /**
     * 同步 modules_statuses.json 中模块的启用状态
     */
    protected static function syncModuleStatus(string $moduleName, bool $enabled): void
    {
        $statusFile = base_path('modules_statuses.json');

        $statuses = File::exists($statusFile)
            ? (json_decode(File::get($statusFile), true) ?? [])
            : [];

        $statuses[$moduleName] = $enabled;

        File::put($statusFile, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlugins::route('/'),
            'create' => Pages\CreatePlugin::route('/create'),
            'edit'   => Pages\EditPlugin::route('/{record}/edit'),
        ];
    }
}
```

- [ ] 创建 Pages 目录和对应页面文件：
  - `app/Filament/Resources/PluginResource/Pages/ListPlugins.php`
  - `app/Filament/Resources/PluginResource/Pages/CreatePlugin.php`
  - `app/Filament/Resources/PluginResource/Pages/EditPlugin.php`

```php
<?php
// ListPlugins.php
namespace App\Filament\Resources\PluginResource\Pages;

use App\Filament\Resources\PluginResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan')
                ->label('扫描插件')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => \Artisan::call('plugins:scan'))
                ->successNotificationTitle('插件扫描完成'),
            Actions\CreateAction::make(),
        ];
    }
}
```

---

## Task 5: Panel 层动态注册

- [ ] 修改 `app/Providers/Filament/AdminPanelProvider.php`，在 `panel()` 方法中添加动态插件注册：

```php
<?php

namespace App\Providers\Filament;

use App\Models\Plugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ... 现有配置 ...

        // 动态注册已启用的 package 类型插件
        $this->registerEnabledPlugins($panel);

        return $panel;
    }

    /**
     * 查询并注册所有已启用的 package 类型插件
     */
    protected function registerEnabledPlugins(Panel $panel): void
    {
        try {
            $plugins = Plugin::enabled()
                ->packages()
                ->whereNotNull('plugin_class')
                ->get();

            foreach ($plugins as $plugin) {
                $class = $plugin->plugin_class;

                if (! class_exists($class)) {
                    continue;
                }

                /** @var \Filament\Contracts\Plugin $instance */
                $instance = app($class);
                $panel->plugin($instance);
            }
        } catch (\Throwable) {
            // 数据库尚未初始化时静默跳过（如首次 migrate 前）
        }
    }
}
```

---

## Task 6: 远程市场（MarketplaceResource）

- [ ] 在 `config/filament-admin.php` 中添加市场配置：

```php
return [
    // ...
    'marketplace' => [
        'index_url' => env(
            'FILAMENT_MARKETPLACE_URL',
            'https://raw.githubusercontent.com/your-org/filament-marketplace/main/plugins.json'
        ),
        'timeout' => 5,
    ],
];
```

- [ ] 创建 Marketplace 数据传输对象 `app/Data/MarketplacePlugin.php`：

```php
<?php

namespace App\Data;

/**
 * 远程市场插件数据对象
 */
readonly class MarketplacePlugin
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $description,
        public string $version,
        public string $source,       // official | recommended | community
        public string $packageName,  // Composer 包名
        public string $repositoryUrl,
    ) {}

    /**
     * 生成安装命令
     */
    public function installCommand(): string
    {
        return "composer require {$this->packageName}";
    }
}
```

- [ ] 创建市场服务 `app/Services/MarketplaceService.php`：

```php
<?php

namespace App\Services;

use App\Data\MarketplacePlugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * 远程插件市场服务
 */
class MarketplaceService
{
    /**
     * 获取远程插件列表，网络失败时返回空集合
     *
     * @return Collection<int, MarketplacePlugin>
     */
    public function fetchPlugins(): Collection
    {
        $url = config('filament-admin.marketplace.index_url');
        $timeout = config('filament-admin.marketplace.timeout', 5);

        try {
            $response = Http::timeout($timeout)->get($url);

            if (! $response->successful()) {
                return collect();
            }

            return collect($response->json('plugins', []))
                ->map(fn (array $item) => new MarketplacePlugin(
                    name: $item['name'],
                    slug: $item['slug'],
                    description: $item['description'] ?? '',
                    version: $item['version'] ?? 'latest',
                    source: $item['source'] ?? 'community',
                    packageName: $item['package_name'],
                    repositoryUrl: $item['repository_url'] ?? '',
                ));
        } catch (\Throwable) {
            return collect();
        }
    }
}
```

- [ ] 创建 MarketplaceResource（只读，Livewire 组件形式）`app/Filament/Resources/MarketplaceResource.php`：

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceResource\Pages;
use App\Services\MarketplaceService;
use Filament\Resources\Resource;

/**
 * 远程插件市场 Resource（只读，无数据库）
 */
class MarketplaceResource extends Resource
{
    // 不绑定 Eloquent 模型
    protected static ?string $model = null;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = '插件市场';

    protected static ?string $navigationGroup = '系统管理';

    protected static ?string $slug = 'marketplace';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplacePlugins::route('/'),
        ];
    }
}
```

- [ ] 创建市场列表页 `app/Filament/Resources/MarketplaceResource/Pages/ListMarketplacePlugins.php`：

```php
<?php

namespace App\Filament\Resources\MarketplaceResource\Pages;

use App\Filament\Resources\MarketplaceResource;
use App\Services\MarketplaceService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ListMarketplacePlugins extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MarketplaceResource::class;

    protected static string $view = 'filament.resources.marketplace.list';

    public string $search = '';
    public string $source = '';
    public ?string $errorMessage = null;

    /** @var Collection */
    public $plugins;

    public function mount(): void
    {
        $this->loadPlugins();
    }

    public function loadPlugins(): void
    {
        $result = app(MarketplaceService::class)->fetchPlugins();

        if ($result->isEmpty() && ! $this->search) {
            $this->errorMessage = '无法获取插件市场数据，请检查网络连接。';
        } else {
            $this->errorMessage = null;
        }

        $this->plugins = $result
            ->when($this->search, fn ($c) => $c->filter(
                fn ($p) => str_contains(strtolower($p->name), strtolower($this->search))
                    || str_contains(strtolower($p->description), strtolower($this->search))
            ))
            ->when($this->source, fn ($c) => $c->where('source', $this->source));
    }

    public function updatedSearch(): void
    {
        $this->loadPlugins();
    }

    public function updatedSource(): void
    {
        $this->loadPlugins();
    }

    public function copyInstallCommand(string $command): void
    {
        $this->js("navigator.clipboard.writeText('{$command}')");

        Notification::make()
            ->title('安装命令已复制')
            ->success()
            ->send();
    }
}
```

- [ ] 创建视图 `resources/views/filament/resources/marketplace/list.blade.php`（展示搜索框、来源筛选、插件卡片列表和安装命令复制按钮）

---

## Task 7: 测试

- [ ] 创建测试文件 `tests/Feature/PluginSystemTest.php`：

```php
<?php

use App\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * 插件系统核心路径测试
 */
beforeEach(function () {
    // 清空 plugins 表
    Plugin::truncate();
});

it('scan-plugins 命令能扫描 Modules/ 目录并写入 plugins 表', function () {
    // 创建临时模块目录
    $modulePath = base_path('Modules/TestModule');
    File::makeDirectory($modulePath, 0755, true, true);
    File::put("{$modulePath}/composer.json", json_encode([
        'name'        => 'test/module',
        'description' => '测试模块',
        'extra'       => [
            'filament-admin' => [
                'name' => 'Test Module',
            ],
        ],
    ]));

    Artisan::call('plugins:scan');

    expect(Plugin::where('slug', 'test-module')->exists())->toBeTrue();
    expect(Plugin::where('slug', 'test-module')->value('type'))->toBe('module');

    // 清理
    File::deleteDirectory($modulePath);
});

it('禁用插件后 is_enabled 变为 false', function () {
    $plugin = Plugin::create([
        'name'       => '测试插件',
        'slug'       => 'test-plugin',
        'type'       => 'package',
        'is_enabled' => true,
    ]);

    $plugin->update(['is_enabled' => false]);

    expect($plugin->fresh()->is_enabled)->toBeFalse();
});

it('依赖检查阻止禁用被其他启用插件依赖的插件', function () {
    $pluginA = Plugin::create([
        'name'       => '插件A',
        'slug'       => 'plugin-a',
        'type'       => 'package',
        'is_enabled' => true,
    ]);

    Plugin::create([
        'name'       => '插件B',
        'slug'       => 'plugin-b',
        'type'       => 'package',
        'is_enabled' => true,
        'requires'   => ['plugin-a'],
    ]);

    // 检查依赖（模拟 PluginResource 中的逻辑）
    $dependents = Plugin::where('is_enabled', true)
        ->whereNotNull('requires')
        ->get()
        ->filter(fn (Plugin $p) => in_array($pluginA->slug, $p->requires ?? [], true));

    expect($dependents)->toHaveCount(1);
    expect($dependents->first()->slug)->toBe('plugin-b');
});

it('module 类型插件启用后 modules_statuses.json 同步更新', function () {
    $statusFile = base_path('modules_statuses.json');

    // 确保测试前状态文件不存在或为空
    File::put($statusFile, json_encode([]));

    $plugin = Plugin::create([
        'name'       => '示例模块',
        'slug'       => 'example',
        'type'       => 'module',
        'is_enabled' => false,
    ]);

    // 模拟 syncModuleStatus
    $statuses = json_decode(File::get($statusFile), true) ?? [];
    $statuses['Example'] = true;
    File::put($statusFile, json_encode($statuses));

    $statuses = json_decode(File::get($statusFile), true);
    expect($statuses['Example'])->toBeTrue();

    // 清理
    File::delete($statusFile);
});
```

- [ ] 运行测试：`php artisan test tests/Feature/PluginSystemTest.php`

---

## Task 8: 文档

- [ ] 创建 `docs/plugins/overview.md`：插件系统架构概述，双轨制说明
- [ ] 创建 `docs/plugins/using-plugins.md`：如何在后台管理插件（扫描、启用、禁用）
- [ ] 创建 `docs/plugins/develop-package.md`：开发 Composer 包型插件指南（extra.filament-admin 字段规范）
- [ ] 创建 `docs/plugins/develop-module.md`：开发业务模块型插件指南（继承 ModuleFilamentPlugin）
- [ ] 打标签并推送：

```bash
git add .
git commit -m "feat: 实现双轨制插件系统"
git tag v0.8.0-插件系统
git push origin main --tags
```
