# 权限体系 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现完整的角色权限系统，包含 Spatie Permission 集成、Filament Shield 自动权限、RoleResource、BasePolicy 和超级管理员机制。

**Architecture:** 使用 spatie/laravel-permission 作为底层权限存储，bezhansalleh/filament-shield 自动为每个 Filament Resource 生成 CRUD 权限点。超级管理员通过 Gate::before() 绕过所有权限检查。所有 Policy 继承 BasePolicy 抽象基类。

**Tech Stack:** spatie/laravel-permission ^6.0, bezhansalleh/filament-shield ^3.0, Pest

---

### Task 1: 安装 Spatie Permission

**Files:** `composer.json`, `config/permission.php`, `app/Models/AdminUser.php`

- [ ] 运行：`composer require spatie/laravel-permission`，预期：包安装成功
- [ ] 发布迁移：`php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- [ ] 修改 `config/permission.php`：将 `'guard_name' => 'web'` 默认值说明改为 admin guard 使用说明（注释）
- [ ] 修改 `app/Models/AdminUser.php`，加入 `use Spatie\Permission\Traits\HasRoles;` 和 `use HasRoles;`，完整代码如下（展示 use 声明部分）
- [ ] 运行迁移：`php artisan migrate`
- [ ] 提交：`git commit -m "feat: 集成 spatie/laravel-permission"`

---

### Task 2: 超级管理员机制

**Files:** `config/filament-admin.php`, `app/Providers/AuthServiceProvider.php`, `bootstrap/providers.php`, `database/seeders/SuperAdminSeeder.php`, `tests/Feature/Permissions/SuperAdminTest.php`

- [ ] 修改 `config/filament-admin.php`，添加 `'super_admin_role' => env('SUPER_ADMIN_ROLE', 'super_admin')`
- [ ] 创建 `app/Providers/AuthServiceProvider.php`，内容：
  ```php
  <?php

  namespace App\Providers;

  use Illuminate\Support\Facades\Gate;
  use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

  class AuthServiceProvider extends ServiceProvider
  {
      protected $policies = [];

      public function boot(): void
      {
          $superAdminRole = config('filament-admin.super_admin_role', 'super_admin');
          
          Gate::before(function ($user, $ability) use ($superAdminRole) {
              return $user->hasRole($superAdminRole, 'admin') ? true : null;
          });
      }
  }
  ```
- [ ] 在 `bootstrap/providers.php` 中添加 `App\Providers\AuthServiceProvider::class`
- [ ] 创建 `database/seeders/SuperAdminSeeder.php`：
  ```php
  <?php

  namespace Database\Seeders;

  use App\Models\AdminUser;
  use Illuminate\Database\Seeder;
  use Spatie\Permission\Models\Role;

  class SuperAdminSeeder extends Seeder
  {
      public function run(): void
      {
          $role = Role::firstOrCreate([
              'name'       => config('filament-admin.super_admin_role', 'super_admin'),
              'guard_name' => 'admin',
          ]);

          $admin = AdminUser::firstOrCreate(
              ['email' => 'admin@example.com'],
              [
                  'username' => 'admin',
                  'name'     => '超级管理员',
                  'password' => bcrypt('password'),
              ]
          );

          $admin->assignRole($role);
      }
  }
  ```
- [ ] 运行 Seeder：`php artisan db:seed --class=SuperAdminSeeder`
- [ ] 写测试文件 `tests/Feature/Permissions/SuperAdminTest.php`：
  ```php
  <?php

  use App\Models\AdminUser;
  use Illuminate\Support\Facades\Gate;
  use Spatie\Permission\Models\Role;

  it('超级管理员绕过所有权限检查', function () {
      $role = Role::create(['name' => 'super_admin', 'guard_name' => 'admin']);
      $admin = AdminUser::factory()->create();
      $admin->assignRole($role);

      $this->actingAs($admin, 'admin');

      expect(Gate::allows('viewAny', AdminUser::class))->toBeTrue()
          ->and(Gate::allows('create', AdminUser::class))->toBeTrue()
          ->and(Gate::allows('delete', new AdminUser()))->toBeTrue();
  });

  it('普通用户没有任何权限', function () {
      $admin = AdminUser::factory()->create();

      $this->actingAs($admin, 'admin');

      expect(Gate::allows('viewAny', AdminUser::class))->toBeFalse();
  });
  ```
- [ ] 运行测试：`php artisan test tests/Feature/Permissions/SuperAdminTest.php`，预期：2 tests passed
- [ ] 提交：`git commit -m "feat: 实现超级管理员 Gate::before() 机制"`

---

### Task 3: 安装 Filament Shield

**Files:** `composer.json`, `config/filament-shield.php`, `app/Providers/Filament/AdminPanelProvider.php`

- [ ] 运行：`composer require bezhansalleh/filament-shield`
- [ ] 发布配置：`php artisan vendor:publish --tag=filament-shield-config`
- [ ] 修改 `config/filament-shield.php`：设置 `'auth_provider_guard' => 'admin'`
- [ ] 运行：`php artisan shield:install --fresh`（生成权限表数据）
- [ ] 修改 `app/Providers/Filament/AdminPanelProvider.php`，添加：
  ```php
  use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
  // ...
  ->plugin(FilamentShieldPlugin::make())
  ```
- [ ] 运行：`php artisan shield:generate --all`（为所有已有 Resource 生成权限点）
- [ ] 提交：`git commit -m "feat: 集成 filament-shield，自动生成 Resource 权限点"`

---

### Task 4: BasePolicy 和 AdminUserPolicy

**Files:** `app/Policies/BasePolicy.php`, `app/Policies/AdminUserPolicy.php`, `app/Providers/AuthServiceProvider.php`, `tests/Feature/Permissions/PolicyTest.php`

- [ ] 创建 `app/Policies/BasePolicy.php`：
  ```php
  <?php

  namespace App\Policies;

  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Foundation\Auth\User as Authenticatable;

  /**
   * 基础 Policy 抽象类
   *
   * 所有 Resource Policy 继承此类，默认实现检查 Spatie Permission 权限点。
   * 权限点格式：{action}_{resource_snake_case}，例如 view_admin_user
   */
  abstract class BasePolicy
  {
      /**
       * 获取资源名称（用于权限点生成）
       * 子类可覆盖此方法自定义权限点前缀
       */
      protected function resourceName(): string
      {
          // 从类名推断，如 AdminUserPolicy -> admin_user
          $class = class_basename(static::class);
          return str($class)->replaceLast('Policy', '')->snake()->value();
      }

      public function viewAny(Authenticatable $user): bool
      {
          return $user->can("view_any_{$this->resourceName()}");
      }

      public function view(Authenticatable $user, Model $model): bool
      {
          return $user->can("view_{$this->resourceName()}");
      }

      public function create(Authenticatable $user): bool
      {
          return $user->can("create_{$this->resourceName()}");
      }

      public function update(Authenticatable $user, Model $model): bool
      {
          return $user->can("update_{$this->resourceName()}");
      }

      public function delete(Authenticatable $user, Model $model): bool
      {
          return $user->can("delete_{$this->resourceName()}");
      }

      public function restore(Authenticatable $user, Model $model): bool
      {
          return $user->can("restore_{$this->resourceName()}");
      }

      public function forceDelete(Authenticatable $user, Model $model): bool
      {
          return $user->can("force_delete_{$this->resourceName()}");
      }
  }
  ```
- [ ] 创建 `app/Policies/AdminUserPolicy.php`：
  ```php
  <?php

  namespace App\Policies;

  use App\Models\AdminUser;

  /**
   * 管理员用户 Policy
   */
  class AdminUserPolicy extends BasePolicy
  {
      // 权限点前缀为 admin_user（由 BasePolicy::resourceName() 自动推断）
  }
  ```
- [ ] 修改 `app/Providers/AuthServiceProvider.php`，在 `$policies` 数组中添加：
  ```php
  protected $policies = [
      \App\Models\AdminUser::class => \App\Policies\AdminUserPolicy::class,
  ];
  ```
- [ ] 写测试 `tests/Feature/Permissions/PolicyTest.php`：
  ```php
  <?php

  use App\Models\AdminUser;
  use Spatie\Permission\Models\Permission;
  use Spatie\Permission\Models\Role;

  it('拥有 view_any_admin_user 权限的角色可以列表查看管理员', function () {
      $permission = Permission::firstOrCreate([
          'name'       => 'view_any_admin_user',
          'guard_name' => 'admin',
      ]);
      $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
      $role->givePermissionTo($permission);

      $admin = AdminUser::factory()->create();
      $admin->assignRole($role);

      $this->actingAs($admin, 'admin');

      expect($admin->can('viewAny', AdminUser::class))->toBeTrue();
  });

  it('没有权限的用户无法创建管理员', function () {
      $admin = AdminUser::factory()->create();
      $this->actingAs($admin, 'admin');

      expect($admin->can('create', AdminUser::class))->toBeFalse();
  });
  ```
- [ ] 运行测试：`php artisan test tests/Feature/Permissions/`，预期：4 tests passed
- [ ] 提交：`git commit -m "feat: 创建 BasePolicy 和 AdminUserPolicy"`

---

### Task 5: RoleResource

**Files:** `app/Filament/Resources/RoleResource.php`, `app/Filament/Resources/RoleResource/Pages/ListRoles.php`, `app/Filament/Resources/RoleResource/Pages/CreateRole.php`, `app/Filament/Resources/RoleResource/Pages/EditRole.php`

- [ ] 运行：`php artisan make:filament-resource Role --generate`（或手动创建）
- [ ] 修改 `app/Filament/Resources/RoleResource.php`，完整实现：
  ```php
  <?php

  namespace App\Filament\Resources;

  use App\Filament\Resources\RoleResource\Pages;
  use Filament\Forms\Components\CheckboxList;
  use Filament\Forms\Components\TextInput;
  use Filament\Forms\Form;
  use Filament\Resources\Resource;
  use Filament\Tables\Columns\TextColumn;
  use Filament\Tables\Table;
  use Spatie\Permission\Models\Permission;
  use Spatie\Permission\Models\Role;

  /**
   * 角色管理资源
   */
  class RoleResource extends Resource
  {
      protected static ?string $model = Role::class;
      protected static ?string $navigationIcon = 'heroicon-o-shield-check';
      protected static ?string $navigationLabel = '角色管理';
      protected static ?string $label = '角色';
      protected static ?string $pluralLabel = '角色';
      protected static ?string $navigationGroup = '系统管理';
      protected static ?int $navigationSort = 2;

      public static function form(Form $form): Form
      {
          return $form->schema([
              TextInput::make('name')
                  ->label('角色名称')
                  ->required()
                  ->unique(ignoreRecord: true)
                  ->maxLength(255),

              CheckboxList::make('permissions')
                  ->label('权限分配')
                  ->relationship('permissions', 'name')
                  ->searchable()
                  ->columns(3)
                  ->gridDirection('row')
                  ->getOptionLabelFromRecordUsing(
                      fn (Permission $record) => str($record->name)->replace('_', ' ')->title()
                  ),
          ]);
      }

      public static function table(Table $table): Table
      {
          return $table
              ->columns([
                  TextColumn::make('name')->label('角色名称')->searchable(),
                  TextColumn::make('permissions_count')
                      ->label('权限数')
                      ->counts('permissions'),
                  TextColumn::make('users_count')
                      ->label('成员数')
                      ->counts('users'),
                  TextColumn::make('created_at')->label('创建时间')->dateTime()->sortable(),
              ]);
      }

      public static function getPages(): array
      {
          return [
              'index'  => Pages\ListRoles::route('/'),
              'create' => Pages\CreateRole::route('/create'),
              'edit'   => Pages\EditRole::route('/{record}/edit'),
          ];
      }
  }
  ```
- [ ] 创建 Pages 文件（`app/Filament/Resources/RoleResource/Pages/ListRoles.php`、`CreateRole.php`、`EditRole.php`），标准模板内容
- [ ] 运行：`php artisan shield:generate --resource=RoleResource`（生成 Role 的权限点）
- [ ] 提交：`git commit -m "feat: 创建 RoleResource 角色管理界面"`

---

### Task 6: 功能文档

**Files:** `docs/features/permissions.md`, `docs/development/custom-permissions.md`

- [ ] 创建 `docs/features/permissions.md`（内容：权限体系架构说明、super_admin 机制、Shield 自动生成权限点、如何为新 Resource 生成权限、Policy 编写规范）
- [ ] 创建 `docs/development/custom-permissions.md`（开发者指南：5 步为新 Resource 添加权限）
- [ ] 提交：`git commit -m "docs: 添加权限体系功能文档"`

---

### Task 7: 收尾

**Files:** 无新文件

- [ ] 运行：`./vendor/bin/pint`，确保格式通过
- [ ] 运行：`./vendor/bin/phpstan analyse --level=6`，确保静态分析通过
- [ ] 运行所有测试：`php artisan test`，确保全部通过
- [ ] 打 Tag：`git tag v0.2.0-权限体系 && git push origin v0.2.0-权限体系`
