# 菜单管理 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现动态菜单管理，支持树形结构、权限绑定和基于数据库的 Filament 导航生成。

**Architecture:** menus 表存储菜单树（支持二级父子结构），MenuResource 提供 Filament 管理界面。AdminPanelProvider 在启动时读取 menus 表，根据当前 admin guard 用户权限过滤后使用 NavigationBuilder 构建导航，结果按用户 ID 缓存到 Redis 5 分钟（key: `navigation:{userId}`）。

**Tech Stack:** Laravel Eloquent, Filament 5 NavigationBuilder, spatie/laravel-permission, Redis Cache, Pest

---

## 文件结构

**新建文件：**
- `database/migrations/xxxx_create_menus_table.php` — menus 表结构
- `app/Models/Menu.php` — 菜单 Eloquent 模型，含父子关联
- `app/Filament/Resources/MenuResource.php` — Filament 资源（表单 + 表格）
- `app/Filament/Resources/MenuResource/Pages/ListMenus.php` — 列表页
- `app/Filament/Resources/MenuResource/Pages/CreateMenu.php` — 新建页
- `app/Filament/Resources/MenuResource/Pages/EditMenu.php` — 编辑页
- `tests/Feature/Menu/MenuTest.php` — 核心路径 Pest 测试
- `docs/features/menu.md` — 功能说明文档

**修改文件：**
- `app/Providers/Filament/AdminPanelProvider.php` — 添加 `navigation()` 配置，接入动态导航

---

## Task 1: 创建 Menu 迁移和模型

**Files:**
- Create: `database/migrations/xxxx_create_menus_table.php`
- Create: `app/Models/Menu.php`

- [ ] **Step 1: 生成迁移文件**

```bash
cd /home/john/projects/personal/filament-admin
php artisan make:migration create_menus_table
```

预期输出：`Created Migration: xxxx_create_menus_table`

- [ ] **Step 2: 填写迁移内容**

找到刚生成的迁移文件（`database/migrations/xxxx_create_menus_table.php`），将 `up()` 方法替换为：

```php
public function up(): void
{
    Schema::create('menus', function (Blueprint $table) {
        $table->id();
        $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
        $table->string('name');
        $table->string('icon')->nullable();
        $table->string('route')->nullable()->comment('路由名称或完整 URL');
        $table->integer('sort')->default(0)->comment('排序，越小越靠前');
        $table->string('permission')->nullable()->comment('绑定的权限点，为空则所有人可见');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('menus');
}
```

`use` 语句需在文件顶部包含：
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
```

- [ ] **Step 3: 运行迁移**

```bash
php artisan migrate
```

预期输出包含：`Running: xxxx_create_menus_table ... DONE`

- [ ] **Step 4: 创建 Menu 模型**

创建 `app/Models/Menu.php`：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 菜单模型
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $icon Heroicon 名称，例如 heroicon-o-home
 * @property string|null $route 路由名称或完整 URL
 * @property int $sort 排序值，越小越靠前
 * @property string|null $permission 绑定的 spatie 权限点，空则所有人可见
 * @property bool $is_active 是否启用
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Menu|null $parent 父菜单
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Menu> $children 子菜单
 */
class Menu extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['parent_id', 'name', 'icon', 'route', 'sort', 'permission', 'is_active'];

    /**
     * 属性类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort'      => 'integer',
        ];
    }

    /** 父菜单关联 */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /** 子菜单关联（按 sort 升序） */
    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort');
    }
}
```

- [ ] **Step 5: 提交**

```bash
git add database/migrations/ app/Models/Menu.php
git commit -m "feat: 新增 menus 表迁移与 Menu 模型"
```

---

## Task 2: 创建 MenuResource

**Files:**
- Create: `app/Filament/Resources/MenuResource.php`
- Create: `app/Filament/Resources/MenuResource/Pages/ListMenus.php`
- Create: `app/Filament/Resources/MenuResource/Pages/CreateMenu.php`
- Create: `app/Filament/Resources/MenuResource/Pages/EditMenu.php`

- [ ] **Step 1: 创建 MenuResource.php**

创建 `app/Filament/Resources/MenuResource.php`：

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

/**
 * 菜单 Filament 资源
 *
 * 提供菜单的增删改查界面，支持父子级选择、图标、路由、权限绑定。
 */
class MenuResource extends Resource
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = '菜单管理';

    protected static ?string $modelLabel = '菜单';

    protected static ?string $pluralModelLabel = '菜单列表';

    protected static ?int $navigationSort = 90;

    /**
     * 表单结构定义
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('菜单名称')
                ->required()
                ->maxLength(100),

            Forms\Components\Select::make('parent_id')
                ->label('父菜单')
                ->placeholder('— 顶级菜单 —')
                ->options(
                    Menu::whereNull('parent_id')
                        ->orderBy('sort')
                        ->pluck('name', 'id')
                )
                ->nullable()
                ->searchable(),

            Forms\Components\TextInput::make('icon')
                ->label('图标')
                ->placeholder('heroicon-o-home')
                ->helperText('填写 Heroicon 名称，例如 heroicon-o-home')
                ->nullable()
                ->maxLength(100),

            Forms\Components\TextInput::make('route')
                ->label('路由/URL')
                ->placeholder('/admin/xxx 或路由名称')
                ->nullable()
                ->maxLength(255),

            Forms\Components\TextInput::make('sort')
                ->label('排序')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('数值越小越靠前'),

            Forms\Components\Select::make('permission')
                ->label('绑定权限')
                ->placeholder('— 无需权限（所有人可见）—')
                ->options(
                    Permission::where('guard_name', 'admin')
                        ->orderBy('name')
                        ->pluck('name', 'name')
                )
                ->nullable()
                ->searchable(),

            Forms\Components\Toggle::make('is_active')
                ->label('启用')
                ->default(true),
        ]);
    }

    /**
     * 表格结构定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('菜单名称')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('父菜单')
                    ->default('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort')
                    ->label('排序')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * 页面路由注册
     *
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit'   => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 2: 创建 Pages 目录与三个页面类**

创建目录：
```bash
mkdir -p app/Filament/Resources/MenuResource/Pages
```

创建 `app/Filament/Resources/MenuResource/Pages/ListMenus.php`：

```php
<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * 菜单列表页
 */
class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    /**
     * 顶部操作按钮
     *
     * @return list<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

创建 `app/Filament/Resources/MenuResource/Pages/CreateMenu.php`：

```php
<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * 菜单新建页
 */
class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;
}
```

创建 `app/Filament/Resources/MenuResource/Pages/EditMenu.php`：

```php
<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * 菜单编辑页
 */
class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    /**
     * 顶部操作按钮
     *
     * @return list<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 3: 验证 Filament 能发现资源（无报错）**

```bash
php artisan filament:check-translations 2>&1 | head -5
# 或者更直接地：
php artisan route:list --path=admin/menus
```

预期：能看到 `/admin/menus` 相关路由。

- [ ] **Step 4: 提交**

```bash
git add app/Filament/Resources/MenuResource.php \
        app/Filament/Resources/MenuResource/
git commit -m "feat: 新增 MenuResource 及管理页面"
```

---

## Task 3: AdminPanelProvider 接入动态导航

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] **Step 1: 在 AdminPanelProvider 顶部添加 use 语句**

打开 `app/Providers/Filament/AdminPanelProvider.php`，在已有 `use` 块末尾追加：

```php
use App\Models\Menu;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Cache;
```

- [ ] **Step 2: 在 panel() 方法末尾追加 navigation() 配置**

在 `->authMiddleware([...]);` 之前插入以下链式调用（紧接在 `->widgets([...])` 之后、`->middleware([...])` 之前均可）：

```php
->navigation(function (NavigationBuilder $builder): NavigationBuilder {
    /** @var \App\Models\AdminUser|null $user */
    $user = auth('admin')->user();

    if (! $user) {
        return $builder;
    }

    $cacheKey = "navigation:{$user->id}";

    /** @var list<\Filament\Navigation\NavigationItem> $items */
    $items = Cache::remember($cacheKey, 300, function () use ($user): array {
        return Menu::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort')])
            ->get()
            ->filter(fn (Menu $menu) => ! $menu->permission || $user->can($menu->permission))
            ->map(fn (Menu $menu) => NavigationItem::make($menu->name)
                ->icon($menu->icon ?? 'heroicon-o-link')
                ->url($menu->route ? url($menu->route) : '#')
                ->childItems(
                    $menu->children
                        ->filter(fn (Menu $child) => ! $child->permission || $user->can($child->permission))
                        ->map(fn (Menu $child) => NavigationItem::make($child->name)
                            ->icon($child->icon ?? 'heroicon-o-link')
                            ->url($child->route ? url($child->route) : '#'))
                        ->values()
                        ->all()
                ))
            ->values()
            ->all();
    });

    return $builder->items($items);
})
```

最终 `AdminPanelProvider.php` 的完整文件如下（替换整个文件）：

```php
<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Menu;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationPlugin;

/**
 * 管理员面板服务提供者
 *
 * 配置 Filament 管理员面板，使用自定义登录页和 admin guard。
 * 动态导航从 menus 表读取，按用户权限过滤，结果缓存 Redis 5 分钟。
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->authGuard('admin')
            ->authPasswordBroker('admin_users')
            ->plugin(
                TwoFactorAuthenticationPlugin::make()
                    ->enableTwoFactorAuthentication()
                    ->addTwoFactorMenuItem()
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                /** @var \App\Models\AdminUser|null $user */
                $user = auth('admin')->user();

                if (! $user) {
                    return $builder;
                }

                $cacheKey = "navigation:{$user->id}";

                /** @var list<\Filament\Navigation\NavigationItem> $items */
                $items = Cache::remember($cacheKey, 300, function () use ($user): array {
                    return Menu::whereNull('parent_id')
                        ->where('is_active', true)
                        ->orderBy('sort')
                        ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort')])
                        ->get()
                        ->filter(fn (Menu $menu) => ! $menu->permission || $user->can($menu->permission))
                        ->map(fn (Menu $menu) => NavigationItem::make($menu->name)
                            ->icon($menu->icon ?? 'heroicon-o-link')
                            ->url($menu->route ? url($menu->route) : '#')
                            ->childItems(
                                $menu->children
                                    ->filter(fn (Menu $child) => ! $child->permission || $user->can($child->permission))
                                    ->map(fn (Menu $child) => NavigationItem::make($child->name)
                                        ->icon($child->icon ?? 'heroicon-o-link')
                                        ->url($child->route ? url($child->route) : '#'))
                                    ->values()
                                    ->all()
                            ))
                        ->values()
                        ->all();
                });

                return $builder->items($items);
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

- [ ] **Step 3: 检查语法无误**

```bash
php artisan config:clear && php artisan route:list --path=admin --columns=uri,name 2>&1 | head -20
```

预期：无报错，能看到 `/admin` 路由列表。

- [ ] **Step 4: 提交**

```bash
git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: AdminPanelProvider 接入基于数据库的动态导航（Redis 缓存 5 分钟）"
```

---

## Task 4: 编写核心路径测试

**Files:**
- Create: `tests/Feature/Menu/MenuTest.php`

- [ ] **Step 1: 创建测试目录**

```bash
mkdir -p tests/Feature/Menu
```

- [ ] **Step 2: 创建测试文件**

创建 `tests/Feature/Menu/MenuTest.php`：

```php
<?php

use App\Models\AdminUser;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * 辅助函数：创建 admin guard 的 AdminUser 并登录
 *
 * @param  array<string, mixed>  $attributes
 */
function loginAdmin(array $attributes = []): AdminUser
{
    $user = AdminUser::factory()->create($attributes);
    actingAs($user, 'admin');

    return $user;
}

// ──────────────────────────────────────────────
// 测试 1：无权限用户看不到绑定了权限的菜单
// ──────────────────────────────────────────────

it('无权限用户看不到绑定了权限的菜单', function (): void {
    // 准备：创建需要特定权限才能显示的菜单
    $permission = Permission::create(['name' => 'view-secret', 'guard_name' => 'admin']);

    $secretMenu = Menu::create([
        'name'       => '机密菜单',
        'sort'       => 1,
        'is_active'  => true,
        'permission' => 'view-secret',
    ]);

    $publicMenu = Menu::create([
        'name'      => '公开菜单',
        'sort'      => 2,
        'is_active' => true,
        // permission 为 null，所有人可见
    ]);

    // 创建没有 view-secret 权限的用户
    $user = loginAdmin();

    // 执行：获取该用户可见的导航菜单
    $visibleMenus = Menu::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort')
        ->get()
        ->filter(fn (Menu $menu) => ! $menu->permission || $user->can($menu->permission));

    // 断言：应看不到机密菜单，但能看到公开菜单
    $names = $visibleMenus->pluck('name')->all();
    expect($names)->not->toContain('机密菜单');
    expect($names)->toContain('公开菜单');
});

// ──────────────────────────────────────────────
// 测试 2：超级管理员能看到所有激活的菜单
// ──────────────────────────────────────────────

it('拥有所有权限的用户可以看到所有激活的菜单', function (): void {
    // 准备：创建多个需要不同权限的菜单
    Permission::create(['name' => 'view-reports', 'guard_name' => 'admin']);
    Permission::create(['name' => 'view-settings', 'guard_name' => 'admin']);

    Menu::create(['name' => '报表中心', 'sort' => 1, 'is_active' => true, 'permission' => 'view-reports']);
    Menu::create(['name' => '系统设置', 'sort' => 2, 'is_active' => true, 'permission' => 'view-settings']);
    Menu::create(['name' => '已禁用菜单', 'sort' => 3, 'is_active' => false]);

    // 创建拥有所有权限的超级管理员
    $superAdmin = loginAdmin();
    $superAdmin->givePermissionTo(['view-reports', 'view-settings']);

    // 执行：获取该用户可见的激活菜单
    $visibleMenus = Menu::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort')
        ->get()
        ->filter(fn (Menu $menu) => ! $menu->permission || $superAdmin->can($menu->permission));

    $names = $visibleMenus->pluck('name')->all();

    // 断言：能看到所有激活的有权限菜单，但看不到禁用的菜单
    expect($names)->toContain('报表中心');
    expect($names)->toContain('系统设置');
    expect($names)->not->toContain('已禁用菜单');
});

// ──────────────────────────────────────────────
// 测试 3：sort 字段影响显示顺序
// ──────────────────────────────────────────────

it('菜单按 sort 字段升序排列', function (): void {
    // 准备：乱序创建三个菜单
    Menu::create(['name' => '排序C', 'sort' => 30, 'is_active' => true]);
    Menu::create(['name' => '排序A', 'sort' => 10, 'is_active' => true]);
    Menu::create(['name' => '排序B', 'sort' => 20, 'is_active' => true]);

    // 执行：按 sort 升序查询
    $menus = Menu::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort')
        ->get();

    $names = $menus->pluck('name')->all();

    // 断言：顺序应为 A、B、C
    expect($names)->toBe(['排序A', '排序B', '排序C']);
});

// ──────────────────────────────────────────────
// 测试 4：导航缓存按用户 ID 隔离
// ──────────────────────────────────────────────

it('动态导航结果按用户 ID 分别缓存', function (): void {
    Cache::flush();

    $userA = AdminUser::factory()->create();
    $userB = AdminUser::factory()->create();

    $keyA = "navigation:{$userA->id}";
    $keyB = "navigation:{$userB->id}";

    // 两个缓存 key 不应相同
    expect($keyA)->not->toBe($keyB);

    // 写入不同缓存值
    Cache::put($keyA, ['菜单A'], 300);
    Cache::put($keyB, ['菜单B'], 300);

    // 断言：各自读取互不干扰
    expect(Cache::get($keyA))->toBe(['菜单A']);
    expect(Cache::get($keyB))->toBe(['菜单B']);
});
```

- [ ] **Step 3: 运行测试，确认全部通过**

```bash
php artisan test tests/Feature/Menu/MenuTest.php --colors=always
```

预期输出：4 个测试全部 PASS，无 FAIL。

若出现 `AdminUser::factory()` 未找到，需确认 `app/Models/AdminUser.php` 中使用了 `HasFactory` trait，且 `database/factories/AdminUserFactory.php` 已存在。

- [ ] **Step 4: 提交**

```bash
git add tests/Feature/Menu/MenuTest.php
git commit -m "test: 新增菜单管理核心路径测试（权限过滤、排序、缓存隔离）"
```

---

## Task 5: 功能文档与版本标签

**Files:**
- Create: `docs/features/menu.md`

- [ ] **Step 1: 创建文档目录（如不存在）**

```bash
mkdir -p docs/features
```

- [ ] **Step 2: 创建功能说明文档**

创建 `docs/features/menu.md`：

```markdown
# 菜单管理

## 概述

菜单管理模块基于数据库存储，支持二级父子树形结构。后台管理员可通过 **菜单管理** 界面对菜单进行增删改查，每条菜单记录可绑定 spatie/laravel-permission 权限点，实现按权限动态显示导航。

## 数据结构

| 字段         | 类型      | 说明                                      |
|--------------|-----------|-------------------------------------------|
| `id`         | bigint    | 主键                                      |
| `parent_id`  | bigint?   | 父菜单 ID，NULL 表示顶级菜单              |
| `name`       | string    | 菜单显示名称                              |
| `icon`       | string?   | Heroicon 名称，例如 `heroicon-o-home`     |
| `route`      | string?   | 路由名称或完整 URL（`/admin/xxx`）        |
| `sort`       | integer   | 排序值，越小越靠前，默认 0               |
| `permission` | string?   | 绑定的权限点（`guard_name = admin`）      |
| `is_active`  | boolean   | 是否启用                                  |

## 动态导航机制

`AdminPanelProvider::panel()` 中通过 `->navigation()` 回调接管 Filament 导航构建：

1. 读取当前 `auth('admin')` 用户。
2. 以 `navigation:{userId}` 为 key 查询 Redis 缓存（TTL 5 分钟）。
3. 若缓存未命中，则查询 `menus` 表（顶级菜单 + 子菜单预加载），过滤条件：
   - `is_active = true`
   - `permission` 为空，**或** 当前用户拥有该权限（`$user->can($permission)`）
4. 将过滤结果映射为 `NavigationItem` 集合，写入缓存后返回给 `NavigationBuilder`。

**缓存失效：** 目前缓存仅自动过期（5 分钟）。若需修改菜单后立即生效，可在 MenuResource 的 `saved` / `deleted` 事件中调用 `Cache::flush()` 或 `Cache::forget("navigation:{$userId}")` 来主动清除。

## 权限绑定说明

- 权限点必须事先在 `permissions` 表中创建（`guard_name = admin`）。
- MenuResource 的 **绑定权限** 下拉框会自动列出所有 `guard_name = admin` 的权限。
- `permission` 字段为空时，该菜单对所有已登录的 admin 用户可见。
- 子菜单的权限独立于父菜单：父菜单可见不代表子菜单必然可见。

## 如何在插件/模块中注册菜单

推荐通过数据库 Seeder 的方式预填菜单，而非在代码中硬编码：

```php
// database/seeders/MenuSeeder.php
Menu::firstOrCreate(
    ['name' => '用户管理', 'parent_id' => null],
    ['icon' => 'heroicon-o-users', 'route' => '/admin/admin-users', 'sort' => 10, 'is_active' => true]
);
```

若需在 Filament Plugin 中动态注册，可在 Plugin 的 `boot()` 方法内监听 `Filament::serving()` 事件，然后写入数据库并清除导航缓存：

```php
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

Filament::serving(function () {
    Menu::firstOrCreate(['name' => '我的插件'], ['route' => '/admin/my-plugin', 'sort' => 50, 'is_active' => true]);
    // 清除所有导航缓存（简单粗暴，适合低频场景）
    Cache::flush();
});
```
```

- [ ] **Step 3: 提交文档**

```bash
git add docs/features/menu.md
git commit -m "docs: 新增菜单管理功能说明文档"
```

- [ ] **Step 4: 打版本标签**

```bash
git tag v0.6.0-菜单管理
git log --oneline -6
```

预期：最新 4 个提交均与菜单管理相关，tag 已创建。

---

## 完成检查清单

完成所有 Task 后，运行以下验证命令：

```bash
# 1. 全量测试（确保没有回归）
php artisan test --colors=always

# 2. 确认菜单路由存在
php artisan route:list --path=admin/menus

# 3. 确认 tag 已创建
git tag | grep 菜单
```

全部通过即完成。
