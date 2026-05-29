# 菜单管理 实现计划

> 修订记录：2026-05-29 根据审查问题清单修复 8 项（含决策：第一版不加菜单缓存）。

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现动态菜单管理，支持树形结构、权限绑定和基于数据库的 Filament 导航生成。

**Architecture:** `menus` 表存储菜单树（支持二级父子结构，字段含 `parent_id` 与 `permission_name`），MenuResource 提供 Filament 管理界面。AdminPanelProvider 通过 `navigationItems()` 注册一个返回数组的闭包，闭包内取 `Auth::guard('admin')->user()`，按当前用户权限过滤后返回 `NavigationItem` 列表。

**缓存决策（第一版）：** 不加任何应用层缓存。菜单/导航查询直接走数据库 + Eloquent 关联，权限校验依赖 Spatie Permission 自带的 permission cache 即可。后续如出现性能瓶颈再加导航层缓存。

**Tech Stack:** Laravel Eloquent, Filament 5 navigationItems, spatie/laravel-permission, Pest

---

## 文件结构

**新建文件：**
- `database/migrations/xxxx_create_menus_table.php` — menus 表结构
- `app/Models/Menu.php` — 菜单 Eloquent 模型，含父子关联
- `app/Policies/MenuPolicy.php` — 菜单授权策略，继承 BasePolicy
- `app/Filament/Resources/MenuResource.php` — Filament 资源（表单 + 表格）
- `app/Filament/Resources/MenuResource/Pages/ListMenus.php` — 列表页
- `app/Filament/Resources/MenuResource/Pages/CreateMenu.php` — 新建页
- `app/Filament/Resources/MenuResource/Pages/EditMenu.php` — 编辑页
- `tests/Feature/Menu/MenuTest.php` — 核心路径 Pest 测试
- `docs/features/menu.md` — 功能说明文档

**修改文件：**
- `app/Providers/Filament/AdminPanelProvider.php` — 添加 `navigationItems()` 配置，接入动态导航
- `app/Providers/AuthServiceProvider.php` — 在 `$policies` 中注册 `Menu => MenuPolicy`

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
        $table->string('permission_name')->nullable()->comment('关联 Spatie Permission 的 name (guard=admin)，为空则所有人可见');
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
 * @property string|null $permission_name 关联 Spatie Permission 的 name（guard=admin），空则所有人可见
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
    protected $fillable = ['parent_id', 'name', 'icon', 'route', 'sort', 'permission_name', 'is_active'];

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
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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
     * 表单结构定义（Filament 5：Schemas\Schema + ->components([])）
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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

            Forms\Components\Select::make('permission_name')
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

**说明：** Filament 5 的 `Panel::navigationItems()` 接收 `array|Closure`，闭包签名为 `fn (): array => [...]`，**不接收** `$user` 参数。当前登录用户需在闭包内通过 `Auth::guard('admin')->user()` 获取。第一版**不加缓存**，直接查询数据库 + Eloquent 关联。

- [ ] **Step 1: 在 AdminPanelProvider 顶部添加 use 语句**

打开 `app/Providers/Filament/AdminPanelProvider.php`，在已有 `use` 块末尾追加：

```php
use App\Models\Menu;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;
```

- [ ] **Step 2: 在 panel() 方法中追加 navigationItems() 配置**

在 `->widgets([...])` 之后、`->middleware([...])` 之前插入：

```php
->navigationItems(fn (): array => collect(self::loadAdminMenus())
    ->flatMap(fn (Menu $menu) => self::buildNavigationItems($menu))
    ->all())
```

并在类内补充两个静态辅助方法：

```php
/**
 * 加载当前 admin 用户可见的顶级菜单（含子菜单），按权限过滤
 *
 * 第一版不加缓存：依赖 Spatie Permission 自带的 permission cache 已足够；
 * 后续如出现性能瓶颈再加导航层缓存。
 *
 * @return \Illuminate\Support\Collection<int, \App\Models\Menu>
 */
protected static function loadAdminMenus(): \Illuminate\Support\Collection
{
    /** @var \App\Models\AdminUser|null $user */
    $user = Auth::guard('admin')->user();

    if (! $user) {
        return collect();
    }

    return Menu::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort')
        ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort')])
        ->get()
        ->filter(fn (Menu $menu) => ! $menu->permission_name
            || $user->hasPermissionTo($menu->permission_name, 'admin'));
}

/**
 * 将单个 Menu 转换为 NavigationItem（含子菜单展开为同级项目）
 *
 * @return list<\Filament\Navigation\NavigationItem>
 */
protected static function buildNavigationItems(Menu $menu): array
{
    /** @var \App\Models\AdminUser $user */
    $user = Auth::guard('admin')->user();

    $items = [
        NavigationItem::make($menu->name)
            ->icon($menu->icon ?? 'heroicon-o-link')
            ->url($menu->route ? url($menu->route) : '#'),
    ];

    foreach ($menu->children as $child) {
        if ($child->permission_name && ! $user->hasPermissionTo($child->permission_name, 'admin')) {
            continue;
        }

        $items[] = NavigationItem::make($child->name)
            ->group($menu->name)
            ->icon($child->icon ?? 'heroicon-o-link')
            ->url($child->route ? url($child->route) : '#');
    }

    return $items;
}
```

- [ ] **Step 3: 最终 AdminPanelProvider 完整文件参考**

```php
<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Menu;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationPlugin;

/**
 * 管理员面板服务提供者
 *
 * 配置 Filament 管理员面板，使用自定义登录页和 admin guard。
 * 动态导航从 menus 表读取，按用户权限过滤；第一版不加应用层缓存。
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
            ->navigationItems(fn (): array => self::loadAdminMenus()
                ->flatMap(fn (Menu $menu) => self::buildNavigationItems($menu))
                ->all())
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

    /**
     * 加载当前 admin 用户可见的顶级菜单（含子菜单），按权限过滤
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Menu>
     */
    protected static function loadAdminMenus(): Collection
    {
        /** @var \App\Models\AdminUser|null $user */
        $user = Auth::guard('admin')->user();

        if (! $user) {
            return collect();
        }

        return Menu::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort')])
            ->get()
            ->filter(fn (Menu $menu) => ! $menu->permission_name
                || $user->hasPermissionTo($menu->permission_name, 'admin'));
    }

    /**
     * 将单个 Menu 转换为 NavigationItem 列表（含子菜单按 group 归组）
     *
     * @return list<\Filament\Navigation\NavigationItem>
     */
    protected static function buildNavigationItems(Menu $menu): array
    {
        /** @var \App\Models\AdminUser $user */
        $user = Auth::guard('admin')->user();

        $items = [
            NavigationItem::make($menu->name)
                ->icon($menu->icon ?? 'heroicon-o-link')
                ->url($menu->route ? url($menu->route) : '#'),
        ];

        foreach ($menu->children as $child) {
            if ($child->permission_name && ! $user->hasPermissionTo($child->permission_name, 'admin')) {
                continue;
            }

            $items[] = NavigationItem::make($child->name)
                ->group($menu->name)
                ->icon($child->icon ?? 'heroicon-o-link')
                ->url($child->route ? url($child->route) : '#');
        }

        return $items;
    }
}
```

- [ ] **Step 4: 检查语法无误**

```bash
php artisan config:clear && php artisan route:list --path=admin --columns=uri,name 2>&1 | head -20
```

预期：无报错，能看到 `/admin` 路由列表。

- [ ] **Step 5: 提交**

```bash
git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: AdminPanelProvider 接入基于数据库的动态导航（第一版无缓存）"
```

---

## Task 4: 编写 MenuPolicy 与核心路径测试

**Files:**
- Create: `app/Policies/MenuPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Create: `tests/Feature/Menu/MenuTest.php`

- [ ] **Step 1: 创建 MenuPolicy（继承项目已有 BasePolicy）**

创建 `app/Policies/MenuPolicy.php`：

```php
<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Menu;

/**
 * 菜单策略
 *
 * 继承 BasePolicy，复用其超级管理员绕过与 admin guard 权限校验逻辑。
 * 权限名约定：viewAny-menu / view-menu / create-menu / update-menu / delete-menu。
 */
class MenuPolicy extends BasePolicy
{
    /** @var string 资源标识，BasePolicy 据此拼接权限名 */
    protected string $resource = 'menu';

    public function viewAny(AdminUser $user): bool
    {
        return $this->checkPermission($user, 'viewAny');
    }

    public function view(AdminUser $user, Menu $menu): bool
    {
        return $this->checkPermission($user, 'view');
    }

    public function create(AdminUser $user): bool
    {
        return $this->checkPermission($user, 'create');
    }

    public function update(AdminUser $user, Menu $menu): bool
    {
        return $this->checkPermission($user, 'update');
    }

    public function delete(AdminUser $user, Menu $menu): bool
    {
        return $this->checkPermission($user, 'delete');
    }
}
```

> 注意：`BasePolicy` 的具体方法名/属性需与项目现有实现对齐。若 `BasePolicy` 已有 `before()` 处理超级管理员且通过 `$resource` 自动派发，这里的逐方法实现可简化。请先 `Read` 现有 BasePolicy 确认 API 后再调整。

- [ ] **Step 2: 在 AuthServiceProvider 注册 Policy**

打开 `app/Providers/AuthServiceProvider.php`，在 `$policies` 数组中追加一行（Laravel 13 中 `AuthServiceProvider` 继承 `Illuminate\Support\ServiceProvider`，在 `boot()` 内通过 `Gate::policy()` 注册，按现有项目实现追加即可）：

```php
protected $policies = [
    // ... 已有映射
    \App\Models\Menu::class => \App\Policies\MenuPolicy::class,
];
```

- [ ] **Step 3: 创建测试目录**

```bash
mkdir -p tests/Feature/Menu
```

- [ ] **Step 4: 创建测试文件**

创建 `tests/Feature/Menu/MenuTest.php`：

```php
<?php

use App\Models\AdminUser;
use App\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// tests/Pest.php 已在 Feature 套件自动 apply RefreshDatabase，这里无需重复 uses()。

beforeEach(function (): void {
    // 集成 Spatie Permission 后必须清缓存，避免跨测试污染
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

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
    Permission::create(['name' => 'view-secret', 'guard_name' => 'admin']);

    Menu::create([
        'name'            => '机密菜单',
        'sort'            => 1,
        'is_active'       => true,
        'permission_name' => 'view-secret',
    ]);

    Menu::create([
        'name'      => '公开菜单',
        'sort'      => 2,
        'is_active' => true,
        // permission_name 为 null，所有人可见
    ]);

    $user = loginAdmin();

    $visibleMenus = Menu::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort')
        ->get()
        ->filter(fn (Menu $menu) => ! $menu->permission_name
            || $user->hasPermissionTo($menu->permission_name, 'admin'));

    $names = $visibleMenus->pluck('name')->all();
    expect($names)->not->toContain('机密菜单');
    expect($names)->toContain('公开菜单');
});

// ──────────────────────────────────────────────
// 测试 2：拥有所有权限的用户可以看到所有激活的菜单
// ──────────────────────────────────────────────

it('拥有所有权限的用户可以看到所有激活的菜单', function (): void {
    Permission::create(['name' => 'view-reports', 'guard_name' => 'admin']);
    Permission::create(['name' => 'view-settings', 'guard_name' => 'admin']);

    Menu::create(['name' => '报表中心', 'sort' => 1, 'is_active' => true, 'permission_name' => 'view-reports']);
    Menu::create(['name' => '系统设置', 'sort' => 2, 'is_active' => true, 'permission_name' => 'view-settings']);
    Menu::create(['name' => '已禁用菜单', 'sort' => 3, 'is_active' => false]);

    $user = loginAdmin();
    $user->givePermissionTo(['view-reports', 'view-settings']);

    $visibleMenus = Menu::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort')
        ->get()
        ->filter(fn (Menu $menu) => ! $menu->permission_name
            || $user->hasPermissionTo($menu->permission_name, 'admin'));

    $names = $visibleMenus->pluck('name')->all();

    expect($names)->toContain('报表中心');
    expect($names)->toContain('系统设置');
    expect($names)->not->toContain('已禁用菜单');
});

// ──────────────────────────────────────────────
// 测试 3：sort 字段影响显示顺序
// ──────────────────────────────────────────────

it('菜单按 sort 字段升序排列', function (): void {
    Menu::create(['name' => '排序C', 'sort' => 30, 'is_active' => true]);
    Menu::create(['name' => '排序A', 'sort' => 10, 'is_active' => true]);
    Menu::create(['name' => '排序B', 'sort' => 20, 'is_active' => true]);

    $menus = Menu::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort')
        ->get();

    expect($menus->pluck('name')->all())->toBe(['排序A', '排序B', '排序C']);
});

// ──────────────────────────────────────────────
// 测试 4：子菜单独立按权限过滤
// ──────────────────────────────────────────────

it('父菜单可见时子菜单仍独立按权限过滤', function (): void {
    Permission::create(['name' => 'view-child', 'guard_name' => 'admin']);

    $parent = Menu::create(['name' => '父菜单', 'sort' => 1, 'is_active' => true]);
    Menu::create([
        'name'            => '受限子项',
        'parent_id'       => $parent->id,
        'sort'            => 1,
        'is_active'       => true,
        'permission_name' => 'view-child',
    ]);
    Menu::create([
        'name'      => '公开子项',
        'parent_id' => $parent->id,
        'sort'      => 2,
        'is_active' => true,
    ]);

    $user = loginAdmin();

    $parent->load('children');
    $visibleChildren = $parent->children->filter(
        fn (Menu $child) => ! $child->permission_name
            || $user->hasPermissionTo($child->permission_name, 'admin')
    );

    $names = $visibleChildren->pluck('name')->all();
    expect($names)->not->toContain('受限子项');
    expect($names)->toContain('公开子项');
});
```

- [ ] **Step 5: 运行测试，确认全部通过**

```bash
php artisan test tests/Feature/Menu/MenuTest.php --colors=always
```

预期输出：4 个测试全部 PASS，无 FAIL。

若出现 `AdminUser::factory()` 未找到，需确认 `app/Models/AdminUser.php` 中使用了 `HasFactory` trait，且 `database/factories/AdminUserFactory.php` 已存在。

- [ ] **Step 6: 提交**

```bash
git add app/Policies/MenuPolicy.php app/Providers/AuthServiceProvider.php tests/Feature/Menu/MenuTest.php
git commit -m "feat: 新增 MenuPolicy 并补充菜单权限过滤与排序测试"
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
| `sort`            | integer   | 排序值，越小越靠前，默认 0                |
| `permission_name` | string?   | 关联 Spatie Permission 的 name（`guard_name = admin`） |
| `is_active`       | boolean   | 是否启用                                  |

## 动态导航机制

`AdminPanelProvider::panel()` 中通过 `->navigationItems(fn (): array => ...)` 接管 Filament 导航构建：

1. 闭包内通过 `Auth::guard('admin')->user()` 读取当前用户（Filament 5 中此回调不接收 `$user` 参数）。
2. 直接查询 `menus` 表（顶级菜单 + 子菜单预加载），**第一版不加应用层缓存**。
3. 过滤条件：
   - `is_active = true`
   - `permission_name` 为空，**或** 当前用户 `hasPermissionTo($menu->permission_name, 'admin')`
4. 将过滤结果映射为 `NavigationItem` 列表返回。

**为什么第一版不加缓存？**
菜单数据通常体量很小（几十到几百条），Spatie Permission 自带 permission cache 已大幅降低权限校验开销。过早引入应用层缓存反而带来失效复杂度（编辑菜单、修改权限、用户角色变更等多处都要 forget）。后续如出现实际性能瓶颈，再在 `AdminPanelProvider::loadAdminMenus()` 内补缓存即可。

## 权限绑定说明

- 权限点必须事先在 `permissions` 表中创建（`guard_name = admin`）。
- MenuResource 的 **绑定权限** 下拉框会自动列出所有 `guard_name = admin` 的权限。
- `permission_name` 字段为空时，该菜单对所有已登录的 admin 用户可见。
- 子菜单的权限独立于父菜单：父菜单可见不代表子菜单必然可见。
- 权限校验统一显式传 guard：`$user->hasPermissionTo($name, 'admin')`。

## 如何在插件/模块中注册菜单

推荐通过数据库 Seeder 的方式预填菜单，而非在代码中硬编码：

```php
// database/seeders/MenuSeeder.php
Menu::firstOrCreate(
    ['name' => '用户管理', 'parent_id' => null],
    ['icon' => 'heroicon-o-users', 'route' => '/admin/admin-users', 'sort' => 10, 'is_active' => true]
);
```

若需在 Filament Plugin 中动态注册，可在 Plugin 的 `boot()` 方法内监听 `Filament::serving()` 事件直接写入数据库即可（第一版无导航缓存，无需额外清缓存动作）。
```

- [ ] **Step 3: 运行 Pint 格式化并提交文档**

```bash
composer pint
git add docs/features/menu.md
git commit -m "docs: 新增菜单管理功能说明文档"
```

- [ ] **Step 4: 打版本标签**

```bash
git tag v0.6.0-菜单管理
git log --oneline -6
```

预期：最新若干提交均与菜单管理相关，tag `v0.6.0-菜单管理` 已创建（衔接 `v0.5.0-API规范`）。

---

## 完成检查清单

完成所有 Task 后，运行以下验证命令：

```bash
# 1. 全量测试（确保没有回归）
php artisan test --colors=always

# 2. 代码风格
composer pint

# 3. 静态分析
composer phpstan

# 4. 确认菜单路由存在
php artisan route:list --path=admin/menus

# 5. 确认 tag 已创建
git tag | grep 菜单管理
```

全部通过即完成。
