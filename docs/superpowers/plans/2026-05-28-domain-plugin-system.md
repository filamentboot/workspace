# 插件系统 实现计划

> 修订记录：2026-05-29 根据审查问题清单修复 15 项问题（保留完整范围，含远程市场）。

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现完整的插件系统：本地 `plugins/` 目录存放插件源码（每个插件含 `plugin.json` 清单），数据库 `plugins` 表记录运行时状态，远程市场支持浏览/下载/安装插件包。

**Architecture（双轨 source-of-truth 明确划分）:**

- **`plugins/{vendor}/{name}/plugin.json`**：插件作者声明（不可变，随插件包发行）。包含 name / slug / version / namespace / plugin_class / requires / description 等元数据。
- **数据库 `plugins` 表**：运行时状态（is_enabled / installed_version / installed_at / config_overrides / source 来源 等）。
- **同步规则**：`php artisan plugin:scan` 命令扫描 `plugins/` 目录，对比 `plugin.json` 与 DB 记录，**新增/更新** DB 行（以 `plugin.json` 的元数据为准刷新 name/version/plugin_class/requires/description，但**保留 DB 的 is_enabled / config_overrides** 等运行时字段）。启停操作只改 DB，**不改 plugin.json**。

**Tech Stack:** Pest 4 / Filament 5 / Spatie Permission / Shield

**Tag：** 完成后打 `v0.8.0-插件系统`（全文统一）。

---

## Task 0: 配置与目录约定

- [ ] 在 `config/filament-admin.php` 中新增配置 key（不存在则创建该配置文件）：

```php
<?php

return [
    /**
     * 插件相关配置
     */
    'plugins' => [
        /** 插件根目录（相对 base_path） */
        'path' => base_path('plugins'),
        /** 插件命名空间前缀，作者必须遵循 Plugins\{Vendor}\{Name}\ */
        'namespace_prefix' => 'Plugins\\',
    ],

    /**
     * 远程插件市场配置
     */
    'marketplace' => [
        /** 市场 API base url，例如 https://market.example.com/api */
        'url' => env('FILAMENT_MARKETPLACE_URL', 'https://market.example.com/api'),
        /** 请求超时秒数 */
        'timeout' => 10,
        /** 列表缓存时长（秒） */
        'cache_ttl' => 300,
    ],
];
```

- [ ] 在项目根新建目录：
  - `plugins/`（提交进 git，存放本地已安装插件）
  - `storage/app/plugins/downloads/`（远程下载临时目录，加入 `.gitignore`）

- [ ] **命名空间冲突方案（选定）**：每个插件 namespace 必须以 `Plugins\{Vendor}\{Name}\` 开头（在 `plugin.json` 的 `namespace` 字段声明）。**实现方式**：在 `composer.json` 的 `autoload.psr-4` 静态注入一行 `"Plugins\\": "plugins/"`（项目初始化时一次性写入），各插件按目录结构 `plugins/{vendor}/{name}/src/...` 自动遵循 PSR-4。**不**在运行时动态修改 `composer.json` 或调用 `composer dump-autoload`，避免并发与权限问题。如果某插件的 `vendor/name` 与目录大小写不一致，由 PluginManager 在 `enable` 时通过 `spl_autoload_register` 注册兜底加载器。

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/",
            "Plugins\\": "plugins/"
        }
    }
}
```

- [ ] **bootstrap 动态 providers 方案（选定）**：**不**修改 `bootstrap/providers.php`（git 跟踪文件，并发写入危险）。新建 `bootstrap/plugin-providers.php`，由 PluginManager 维护，加入 `.gitignore`：

```php
// bootstrap/plugin-providers.php （由 PluginManager 自动生成，请勿手动编辑）
<?php

return [
    // Plugins\Acme\Blog\BlogServiceProvider::class,
];
```

并在 `bootstrap/app.php` 引入：

```php
->withProviders([
    ...require __DIR__.'/plugin-providers.php',
])
```

`.gitignore` 加入：

```
bootstrap/plugin-providers.php
storage/app/plugins/
```

---

## Task 1: plugins 表迁移和 Plugin 模型

- [ ] 创建迁移 `database/migrations/xxxx_xx_xx_create_plugins_table.php`：

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
            $table->string('slug')->unique()->comment('插件唯一标识，对应 plugin.json.slug');
            $table->string('vendor')->comment('插件作者/组织');
            $table->string('namespace')->comment('PSR-4 命名空间，必须以 Plugins\\ 开头');
            $table->string('plugin_class')->nullable()->comment('Filament Plugin 类全限定名');
            $table->string('installed_version')->nullable();
            $table->text('description')->nullable();
            $table->json('requires')->nullable()->comment('依赖的其他插件 slug 列表');
            $table->json('config_overrides')->nullable()->comment('运行时配置覆盖');
            $table->string('source')->default('local')->comment('local | marketplace');
            $table->string('source_hash', 64)->nullable()->comment('远程下载时的 sha256');
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
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

- [ ] 创建 `app/Models/Plugin.php`：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 插件运行时状态模型
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $vendor
 * @property string $namespace
 * @property string|null $plugin_class
 * @property string|null $installed_version
 * @property string|null $description
 * @property array|null $requires
 * @property array|null $config_overrides
 * @property string $source
 * @property string|null $source_hash
 * @property bool $is_enabled
 * @property \Illuminate\Support\Carbon|null $installed_at
 * @property \Illuminate\Support\Carbon|null $enabled_at
 */
class Plugin extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'vendor',
        'namespace',
        'plugin_class',
        'installed_version',
        'description',
        'requires',
        'config_overrides',
        'source',
        'source_hash',
        'is_enabled',
        'installed_at',
        'enabled_at',
    ];

    protected $casts = [
        'requires'         => 'array',
        'config_overrides' => 'array',
        'is_enabled'       => 'boolean',
        'installed_at'     => 'datetime',
        'enabled_at'       => 'datetime',
    ];

    /**
     * 已启用插件
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 插件源码绝对路径
     */
    public function getPath(): string
    {
        return rtrim(config('filament-admin.plugins.path'), '/') . "/{$this->vendor}/{$this->slug}";
    }
}
```

- [ ] 执行 `php artisan migrate`

---

## Task 2: plugin.json 规范与示例

- [ ] 在 `docs/plugins/plugin-json-spec.md` 中明确 `plugin.json` 字段规范：

```json
{
    "name": "博客插件",
    "slug": "blog",
    "vendor": "acme",
    "version": "1.0.0",
    "description": "提供文章发布与分类管理",
    "namespace": "Plugins\\Acme\\Blog\\",
    "plugin_class": "Plugins\\Acme\\Blog\\BlogPlugin",
    "service_provider": "Plugins\\Acme\\Blog\\BlogServiceProvider",
    "requires": [],
    "min_admin_version": "1.0.0"
}
```

- [ ] 目录结构约定：

```
plugins/
  acme/
    blog/
      plugin.json
      src/
        BlogPlugin.php
        BlogServiceProvider.php
        Filament/
          Resources/...
      database/
        migrations/...
      routes/
        web.php
```

- [ ] **声明明确**：
  - `plugin.json` = 作者声明（不可变）
  - 数据库 `plugins` 表 = 运行时状态（is_enabled / config_overrides / installed_at 可变）
  - `plugin:scan` 命令是唯一的"声明 → 状态"同步入口

---

## Task 3: PluginManager 服务

- [ ] 创建 `app/Services/PluginManager.php`：

```php
<?php

namespace App\Services;

use App\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * 插件生命周期管理服务
 */
class PluginManager
{
    /**
     * 启用插件（按完整生命周期顺序执行）
     *
     * 顺序：
     *   a) 运行插件 migrations
     *   b) 注册插件 service provider（写入 bootstrap/plugin-providers.php）
     *   c) 注册插件路由（service provider 中 require routes/*.php）
     *   d) 注册插件 Filament 资源（由插件自身的 FilamentPlugin 实现完成）
     *   e) 同步插件权限（admin guard 下 firstOrCreate，必要时调用 Shield generate）
     *   f) 注册插件菜单（写入 menus 表，由各插件自行触发或在 enable 钩子里完成）
     */
    public function enable(Plugin $plugin): void
    {
        $this->assertDependenciesEnabled($plugin);

        // a) migrations
        $migrationsPath = $plugin->getPath() . '/database/migrations';
        if (File::isDirectory($migrationsPath)) {
            Artisan::call('migrate', [
                '--path'  => str_replace(base_path() . '/', '', $migrationsPath),
                '--force' => true,
            ]);
        }

        // b) provider 注册
        if ($providerClass = $this->resolveProviderClass($plugin)) {
            $this->addProviderToBootstrap($providerClass);
        }

        // c) 路由：在 provider 的 boot() 里 require routes/web.php

        // d) Filament 资源：由 AdminPanelProvider 启动时根据 plugin_class 注册

        // e) 权限
        $this->syncPermissions($plugin);

        // f) 菜单：交给插件自身在 enable hook 中注册

        $plugin->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    /**
     * 禁用插件（不回滚 migration，避免数据丢失）
     *
     * - 标记 is_enabled = false
     * - 从 bootstrap/plugin-providers.php 移除 provider
     * - 权限和菜单仅标记禁用，**不删除**
     * - 如需彻底移除请调用 uninstall()
     */
    public function disable(Plugin $plugin): void
    {
        $this->assertNoEnabledDependents($plugin);

        if ($providerClass = $this->resolveProviderClass($plugin)) {
            $this->removeProviderFromBootstrap($providerClass);
        }

        $plugin->update([
            'is_enabled' => false,
            'enabled_at' => null,
        ]);
    }

    /**
     * 卸载插件（区别于 disable）
     *
     * - 必须先 disable
     * - 回滚 migration
     * - 删除 plugins/{vendor}/{slug} 目录
     * - 删除 DB 行
     */
    public function uninstall(Plugin $plugin): void
    {
        if ($plugin->is_enabled) {
            throw new RuntimeException('卸载前请先禁用该插件。');
        }

        $migrationsPath = $plugin->getPath() . '/database/migrations';
        if (File::isDirectory($migrationsPath)) {
            Artisan::call('migrate:rollback', [
                '--path'  => str_replace(base_path() . '/', '', $migrationsPath),
                '--force' => true,
            ]);
        }

        File::deleteDirectory($plugin->getPath());
        $plugin->delete();
    }

    /**
     * 检查所有依赖均已启用
     */
    protected function assertDependenciesEnabled(Plugin $plugin): void
    {
        foreach ($plugin->requires ?? [] as $depSlug) {
            $dep = Plugin::where('slug', $depSlug)->first();
            if (! $dep || ! $dep->is_enabled) {
                throw new RuntimeException("依赖插件 {$depSlug} 未安装或未启用。");
            }
        }
    }

    /**
     * 检查没有已启用插件依赖于此插件
     */
    protected function assertNoEnabledDependents(Plugin $plugin): void
    {
        $dependents = Plugin::enabled()
            ->whereNotNull('requires')
            ->get()
            ->filter(fn (Plugin $p) => in_array($plugin->slug, $p->requires ?? [], true));

        if ($dependents->isNotEmpty()) {
            $names = $dependents->pluck('name')->join('、');
            throw new RuntimeException("以下已启用插件依赖此插件：{$names}");
        }
    }

    /**
     * 解析插件 ServiceProvider 类名（从 plugin.json）
     */
    protected function resolveProviderClass(Plugin $plugin): ?string
    {
        $manifest = $this->readManifest($plugin);

        return $manifest['service_provider'] ?? null;
    }

    /**
     * 读取 plugin.json
     *
     * @return array<string, mixed>
     */
    public function readManifest(Plugin $plugin): array
    {
        $manifestPath = $plugin->getPath() . '/plugin.json';

        if (! File::exists($manifestPath)) {
            throw new RuntimeException("plugin.json 不存在：{$manifestPath}");
        }

        return json_decode(File::get($manifestPath), true) ?? [];
    }

    /**
     * 将 provider 写入 bootstrap/plugin-providers.php
     */
    protected function addProviderToBootstrap(string $providerClass): void
    {
        $file = base_path('bootstrap/plugin-providers.php');
        $providers = File::exists($file) ? (require $file) : [];

        if (! in_array($providerClass, $providers, true)) {
            $providers[] = $providerClass;
            $this->writeBootstrapFile($file, $providers);
        }
    }

    /**
     * 从 bootstrap/plugin-providers.php 移除 provider
     */
    protected function removeProviderFromBootstrap(string $providerClass): void
    {
        $file = base_path('bootstrap/plugin-providers.php');
        if (! File::exists($file)) {
            return;
        }

        $providers = array_values(array_filter(
            require $file,
            fn ($p) => $p !== $providerClass
        ));

        $this->writeBootstrapFile($file, $providers);
    }

    /**
     * 写 bootstrap providers 文件
     *
     * @param  array<int, string>  $providers
     */
    protected function writeBootstrapFile(string $file, array $providers): void
    {
        $lines = array_map(fn ($p) => "    \\{$p}::class,", $providers);
        $body  = implode("\n", $lines);
        $php   = "<?php\n\n// 由 PluginManager 自动生成，请勿手动编辑\nreturn [\n{$body}\n];\n";

        File::put($file, $php);
    }

    /**
     * 同步插件权限到 admin guard
     */
    protected function syncPermissions(Plugin $plugin): void
    {
        $manifest = $this->readManifest($plugin);
        $permissions = $manifest['permissions'] ?? [];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'admin',
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

---

## Task 4: plugin:scan 命令

- [ ] 创建 `app/Console/Commands/ScanPlugins.php`：

```php
<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 扫描 plugins/ 目录，将 plugin.json 同步进 plugins 表
 *
 * 同步规则：
 *  - plugin.json 是 source-of-truth（声明字段：name/version/namespace/plugin_class/requires/description）
 *  - plugins 表保留运行时字段：is_enabled / config_overrides / enabled_at
 *  - 仅刷新声明字段，**不**改动运行时字段
 */
class ScanPlugins extends Command
{
    protected $signature = 'plugin:scan';

    protected $description = '扫描 plugins/ 目录并同步 plugins 表';

    public function handle(): int
    {
        $root = config('filament-admin.plugins.path');

        if (! File::isDirectory($root)) {
            $this->warn("插件目录不存在：{$root}");

            return self::SUCCESS;
        }

        $count = 0;

        foreach (File::directories($root) as $vendorDir) {
            foreach (File::directories($vendorDir) as $pluginDir) {
                $manifestFile = $pluginDir . '/plugin.json';
                if (! File::exists($manifestFile)) {
                    continue;
                }

                $manifest = json_decode(File::get($manifestFile), true);
                if (! $manifest || empty($manifest['slug'])) {
                    $this->warn("跳过非法 plugin.json：{$manifestFile}");

                    continue;
                }

                Plugin::updateOrCreate(
                    ['slug' => $manifest['slug']],
                    [
                        'name'              => $manifest['name'] ?? $manifest['slug'],
                        'vendor'            => $manifest['vendor'] ?? basename($vendorDir),
                        'namespace'         => rtrim($manifest['namespace'] ?? '', '\\') . '\\',
                        'plugin_class'      => $manifest['plugin_class'] ?? null,
                        'installed_version' => $manifest['version'] ?? null,
                        'description'       => $manifest['description'] ?? null,
                        'requires'          => $manifest['requires'] ?? [],
                        'installed_at'      => fn ($p) => $p?->installed_at ?? now(),
                    ]
                );

                $count++;
                $this->line("  [scan] {$manifest['slug']} ({$manifest['version']})");
            }
        }

        $this->info("扫描完成，共 {$count} 个插件。");

        return self::SUCCESS;
    }
}
```

- [ ] 测试：`php artisan plugin:scan`

---

## Task 5: PluginResource（Filament 5 形式）

- [ ] 创建 `app/Filament/Resources/PluginResource.php`：

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PluginResource\Pages;
use App\Models\Plugin;
use App\Services\PluginManager;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('插件名称')->disabled(),
                TextInput::make('slug')->label('标识符')->disabled(),
                TextInput::make('vendor')->label('作者')->disabled(),
                TextInput::make('installed_version')->label('版本')->disabled(),
                Textarea::make('description')->label('描述')->disabled(),
                TextInput::make('plugin_class')->label('Plugin 类')->disabled(),
                Toggle::make('is_enabled')->label('已启用')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('插件名称')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('vendor')->label('作者'),
                Tables\Columns\TextColumn::make('installed_version')->label('版本')->placeholder('未知'),
                Tables\Columns\IconColumn::make('is_enabled')->label('已启用')->boolean(),
                Tables\Columns\TextColumn::make('source')->label('来源')->badge(),
                Tables\Columns\TextColumn::make('enabled_at')->label('启用时间')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('状态'),
                Tables\Filters\SelectFilter::make('source')
                    ->label('来源')
                    ->options(['local' => '本地', 'marketplace' => '市场']),
            ])
            ->actions([
                Tables\Actions\Action::make('enable')
                    ->label('启用')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Plugin $record) => ! $record->is_enabled)
                    ->action(function (Plugin $record) {
                        try {
                            app(PluginManager::class)->enable($record);
                            Notification::make()->title("插件 {$record->name} 已启用")->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('启用失败')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('disable')
                    ->label('禁用')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Plugin $record) => $record->is_enabled)
                    ->requiresConfirmation()
                    ->action(function (Plugin $record) {
                        try {
                            app(PluginManager::class)->disable($record);
                            Notification::make()->title("插件 {$record->name} 已禁用")->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('禁用失败')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('uninstall')
                    ->label('卸载')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Plugin $record) => ! $record->is_enabled)
                    ->requiresConfirmation()
                    ->modalDescription('卸载将回滚 migration 并删除插件文件，请谨慎操作。')
                    ->action(function (Plugin $record) {
                        try {
                            app(PluginManager::class)->uninstall($record);
                            Notification::make()->title('插件已卸载')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('卸载失败')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlugins::route('/'),
        ];
    }
}
```

- [ ] `app/Filament/Resources/PluginResource/Pages/ListPlugins.php`：

```php
<?php

namespace App\Filament\Resources\PluginResource\Pages;

use App\Filament\Resources\PluginResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scan')
                ->label('扫描插件')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    Artisan::call('plugin:scan');
                })
                ->successNotificationTitle('插件扫描完成'),
        ];
    }
}
```

---

## Task 6: Panel 层动态注册

- [ ] 修改 `app/Providers/Filament/AdminPanelProvider.php`，在 `panel()` 末尾加入：

```php
$this->registerEnabledPlugins($panel);

return $panel;
```

并新增方法：

```php
/**
 * 动态注册所有已启用插件的 FilamentPlugin
 */
protected function registerEnabledPlugins(\Filament\Panel $panel): void
{
    try {
        \App\Models\Plugin::query()
            ->where('is_enabled', true)
            ->whereNotNull('plugin_class')
            ->get()
            ->each(function (\App\Models\Plugin $plugin) use ($panel) {
                $class = $plugin->plugin_class;
                if (! class_exists($class)) {
                    return;
                }
                $panel->plugin(app($class));
            });
    } catch (\Throwable) {
        // 首次 migrate 之前 plugins 表不存在，静默跳过
    }
}
```

---

## Task 7: 远程市场（Filament Page）

> Filament 5 不允许 Resource 没有 model，因此市场用 **`Filament\Pages\Page`** 实现，路由 `/admin/marketplace`。

- [ ] 创建 DTO `app/Data/MarketplacePlugin.php`：

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
        public string $vendor,
        public string $version,
        public string $description,
        public string $downloadUrl,
        public string $sha256,
        public string $source = 'community',
    ) {}
}
```

- [ ] 创建服务 `app/Services/MarketplaceService.php`：

```php
<?php

namespace App\Services;

use App\Data\MarketplacePlugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

/**
 * 远程插件市场服务
 *
 * - 列表：HTTP 调用带 timeout(10) + retry(2, 100)，失败降级返回空集合
 * - 列表结果按页缓存 300 秒
 * - 下载：校验 sha256 hash（v1 仅 hash，签名机制 v2 引入）
 */
class MarketplaceService
{
    /**
     * 拉取市场插件列表（分页）
     *
     * @return Collection<int, MarketplacePlugin>
     */
    public function list(int $page = 1, ?string $keyword = null): Collection
    {
        $cacheKey = 'marketplace.list.' . $page . '.' . md5((string) $keyword);
        $ttl      = (int) config('filament-admin.marketplace.cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($page, $keyword) {
            try {
                $response = Http::baseUrl(config('filament-admin.marketplace.url'))
                    ->timeout((int) config('filament-admin.marketplace.timeout', 10))
                    ->retry(2, 100)
                    ->get('/plugins', array_filter([
                        'page' => $page,
                        'q'    => $keyword,
                    ]));

                if (! $response->successful()) {
                    return collect();
                }

                return collect($response->json('data', []))->map(fn (array $item) => new MarketplacePlugin(
                    name:        $item['name'],
                    slug:        $item['slug'],
                    vendor:      $item['vendor'],
                    version:     $item['version'],
                    description: $item['description'] ?? '',
                    downloadUrl: $item['download_url'],
                    sha256:      $item['sha256'],
                    source:      $item['source'] ?? 'community',
                ));
            } catch (\Throwable) {
                return collect();
            }
        });
    }

    /**
     * 下载并安装远程插件
     *
     * 流程：
     *  1) 下载到 storage/app/plugins/downloads/{slug}-{version}.zip
     *  2) 校验 sha256
     *  3) 校验 zip 结构必须包含 plugin.json 且 slug 一致
     *  4) 解压到 plugins/{vendor}/{slug}
     *  5) 调用 plugin:scan 写入 DB
     */
    public function downloadAndInstall(MarketplacePlugin $plugin): void
    {
        $downloadDir = storage_path('app/plugins/downloads');
        File::ensureDirectoryExists($downloadDir);

        $zipPath = "{$downloadDir}/{$plugin->slug}-{$plugin->version}.zip";

        $response = Http::timeout((int) config('filament-admin.marketplace.timeout', 10))
            ->retry(2, 100)
            ->sink($zipPath)
            ->get($plugin->downloadUrl);

        if (! $response->successful()) {
            throw new RuntimeException('插件下载失败。');
        }

        // 校验 hash
        $actualHash = hash_file('sha256', $zipPath);
        if (! hash_equals($plugin->sha256, $actualHash)) {
            File::delete($zipPath);
            throw new RuntimeException('插件包 sha256 校验失败，已拒绝安装。');
        }

        // 校验 zip 结构
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('插件包无法打开。');
        }

        $manifestRaw = $zip->getFromName('plugin.json');
        if ($manifestRaw === false) {
            $zip->close();
            throw new RuntimeException('插件包缺少 plugin.json。');
        }

        $manifest = json_decode($manifestRaw, true) ?? [];
        if (($manifest['slug'] ?? null) !== $plugin->slug) {
            $zip->close();
            throw new RuntimeException('plugin.json 中的 slug 与请求不一致。');
        }

        // 解压
        $target = config('filament-admin.plugins.path') . "/{$plugin->vendor}/{$plugin->slug}";
        File::ensureDirectoryExists($target);
        $zip->extractTo($target);
        $zip->close();

        // 同步进 DB
        \Illuminate\Support\Facades\Artisan::call('plugin:scan');

        \App\Models\Plugin::where('slug', $plugin->slug)->update([
            'source'      => 'marketplace',
            'source_hash' => $plugin->sha256,
        ]);
    }
}
```

- [ ] 创建 `app/Filament/Pages/Marketplace.php`：

```php
<?php

namespace App\Filament\Pages;

use App\Data\MarketplacePlugin;
use App\Services\MarketplaceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * 插件市场页面（不绑定模型）
 */
class Marketplace extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = '插件市场';

    protected static ?string $navigationGroup = '系统管理';

    protected static ?string $slug = 'marketplace';

    protected static string $view = 'filament.pages.marketplace';

    public string $keyword = '';

    public int $page = 1;

    public ?string $errorMessage = null;

    /** @var Collection<int, MarketplacePlugin> */
    public Collection $plugins;

    public function mount(): void
    {
        $this->plugins = collect();
        $this->load();
    }

    public function load(): void
    {
        $this->plugins = app(MarketplaceService::class)->list($this->page, $this->keyword ?: null);

        $this->errorMessage = $this->plugins->isEmpty() && ! $this->keyword
            ? '市场暂不可用，请稍后重试。'
            : null;
    }

    public function install(string $slug): void
    {
        $plugin = $this->plugins->firstWhere('slug', $slug);
        if (! $plugin) {
            Notification::make()->title('插件未找到')->danger()->send();

            return;
        }

        try {
            app(MarketplaceService::class)->downloadAndInstall($plugin);
            Notification::make()->title("插件 {$plugin->name} 已下载安装，请前往插件管理启用。")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('安装失败')->body($e->getMessage())->danger()->send();
        }
    }
}
```

- [ ] 创建视图 `resources/views/filament/pages/marketplace.blade.php`：展示搜索框、卡片列表、安装按钮，并在 `$errorMessage` 非空时显示提示但不让页面崩溃。

---

## Task 8: Policy 与权限

- [ ] 创建 `app/Policies/PluginPolicy.php`，继承 `App\Policies\BasePolicy`（已有约定）：

```php
<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Plugin;

/**
 * 插件策略
 */
class PluginPolicy extends BasePolicy
{
    public function viewAny(AdminUser $user): bool
    {
        return $user->can('plugin.view');
    }

    public function enable(AdminUser $user, Plugin $plugin): bool
    {
        return $user->can('plugin.manage');
    }

    public function disable(AdminUser $user, Plugin $plugin): bool
    {
        return $user->can('plugin.manage');
    }

    public function uninstall(AdminUser $user, Plugin $plugin): bool
    {
        return $user->can('plugin.manage');
    }
}
```

- [ ] 创建 `app/Policies/MarketplacePolicy.php`（继承 `BasePolicy`，方法 `view`、`install` 均要求 `marketplace.manage` 权限）。

- [ ] 在 `app/Providers/AuthServiceProvider.php`（**注意：Laravel 11+ 已移除框架自带基类，必须 `extends Illuminate\Support\ServiceProvider`**，在 `boot()` 中调用 `Gate::policy()`）注册：

```php
public function boot(): void
{
    Gate::policy(\App\Models\Plugin::class, \App\Policies\PluginPolicy::class);
    // MarketplacePolicy 不绑定模型，使用 Gate::define 或在 Page 内手工 authorize
}
```

- [ ] 创建权限：
  - `plugin.view` / `plugin.manage` / `marketplace.view` / `marketplace.manage`（admin guard）

---

## Task 9: 测试

> 本项目 `tests/Pest.php` 已对 Feature & Unit 自动 apply `RefreshDatabase`；admin 操作必须 `actingAs($user, 'admin')`；用 `AdminUser::factory()`，**不要**用 `User::factory()`；`beforeEach` 调用 `forgetCachedPermissions()` 防止 Spatie Permission 缓存污染。

- [ ] 创建 `tests/Feature/PluginSystemTest.php`：

```php
<?php

use App\Models\AdminUser;
use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('plugin:scan 命令能扫描 plugins/ 目录并同步 plugins 表', function () {
    $pluginDir = base_path('plugins/acme/demo');
    File::ensureDirectoryExists($pluginDir);
    File::put($pluginDir . '/plugin.json', json_encode([
        'name'         => 'Demo 插件',
        'slug'         => 'demo',
        'vendor'       => 'acme',
        'version'      => '1.0.0',
        'namespace'    => 'Plugins\\Acme\\Demo\\',
        'plugin_class' => 'Plugins\\Acme\\Demo\\DemoPlugin',
        'description'  => '测试',
        'requires'     => [],
    ]));

    Artisan::call('plugin:scan');

    expect(Plugin::where('slug', 'demo')->exists())->toBeTrue();
    expect(Plugin::where('slug', 'demo')->value('vendor'))->toBe('acme');

    File::deleteDirectory(base_path('plugins/acme'));
});

it('scan 不会覆盖已存在记录的 is_enabled 状态', function () {
    Plugin::create([
        'name'              => 'Demo',
        'slug'              => 'demo',
        'vendor'            => 'acme',
        'namespace'         => 'Plugins\\Acme\\Demo\\',
        'installed_version' => '1.0.0',
        'is_enabled'        => true,
        'installed_at'      => now(),
    ]);

    $pluginDir = base_path('plugins/acme/demo');
    File::ensureDirectoryExists($pluginDir);
    File::put($pluginDir . '/plugin.json', json_encode([
        'name'      => 'Demo',
        'slug'      => 'demo',
        'vendor'    => 'acme',
        'version'   => '1.1.0',
        'namespace' => 'Plugins\\Acme\\Demo\\',
    ]));

    Artisan::call('plugin:scan');

    $p = Plugin::where('slug', 'demo')->first();
    expect($p->is_enabled)->toBeTrue();
    expect($p->installed_version)->toBe('1.1.0');

    File::deleteDirectory(base_path('plugins/acme'));
});

it('依赖检查阻止禁用被依赖的插件', function () {
    $a = Plugin::create([
        'name' => 'A', 'slug' => 'a', 'vendor' => 'acme',
        'namespace' => 'Plugins\\Acme\\A\\', 'is_enabled' => true,
    ]);
    Plugin::create([
        'name' => 'B', 'slug' => 'b', 'vendor' => 'acme',
        'namespace' => 'Plugins\\Acme\\B\\', 'is_enabled' => true,
        'requires' => ['a'],
    ]);

    expect(fn () => app(PluginManager::class)->disable($a))
        ->toThrow(RuntimeException::class);
});

it('admin 用户在 admin guard 下能访问插件管理列表', function () {
    $user = AdminUser::factory()->create();
    $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate([
        'name' => 'super-admin',
        'guard_name' => 'admin',
    ]));

    actingAs($user, 'admin')
        ->get('/admin/plugins')
        ->assertSuccessful();
});

it('市场服务在网络失败时返回空集合且不抛异常', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response(null, 500),
    ]);

    $result = app(\App\Services\MarketplaceService::class)->list(1);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result)->toBeEmpty();
});
```

- [ ] 运行：`php artisan test tests/Feature/PluginSystemTest.php`
- [ ] 代码风格：`composer pint`

---

## Task 10: 文档与发布

- [ ] `docs/plugins/overview.md`：插件系统架构、双轨 source-of-truth、生命周期
- [ ] `docs/plugins/plugin-json-spec.md`：plugin.json 完整字段规范
- [ ] `docs/plugins/develop-plugin.md`：插件开发指南（namespace 约定、ServiceProvider、FilamentPlugin、权限注册）
- [ ] `docs/plugins/marketplace.md`：市场使用与下载安装流程，sha256 校验说明（签名 v2 引入）
- [ ] 打标签并推送：

```bash
git add .
git commit -m "feat: 实现插件系统（本地 plugins/ + 远程市场）"
git tag v0.8.0-插件系统
git push origin feature/phase-1-authentication --tags
```
