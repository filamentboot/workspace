# Phase 1 Admin Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete Phase 1 admin foundation package: admin users, login logs, roles/permissions, menus with dynamic navigation, departments, data scopes, and activity logs.

**Architecture:** Keep Filament Resource routing managed by Filament, but render the left navigation from database-backed menu rules. Use Spatie Permission + Shield for functional permissions, a separate role data scope model for row-level access, and `spatie/laravel-activitylog:^4.12` + `alizharb/filament-activity-log:^1.3` for audit trails on the current PHP 8.3 line. Keep services small: navigation building, department tree traversal, data scope resolution, and audit logging each get one focused class.

**Tech Stack:** Laravel 13, Filament 5, Pest 4, Spatie Permission, Filament Shield, Spatie Activitylog 4.x, AlizHarb Filament Activity Log, MySQL.

---

## Current Baseline

Already present:

- `App\Models\AdminUser` with `admin_users`, `admin` guard, `HasRoles`, `SoftDeletes`, and 2FA.
- `App\Models\LoginLog` with login event listener.
- `App\Policies\BasePolicy`, `AdminUserPolicy`, `LoginLogPolicy`.
- `spatie/laravel-permission` and `bezhansalleh/filament-shield`.
- `super_admin` role and `Gate::before()` in `AuthServiceProvider`.

Missing and built by this plan:

- Filament Resources for admin users, login logs, menus, departments, role data scopes, activity logs.
- Admin status and department assignment.
- Data-driven navigation.
- Department tree and role data scopes.
- Operation audit logging.

## File Map

Create:

- `app/Enums/AdminUserStatus.php` - admin account status values.
- `app/Enums/DataScope.php` - role data scope values.
- `app/Models/Menu.php` - dynamic admin menu model.
- `app/Models/Department.php` - department tree model.
- `app/Models/RoleDataScope.php` - data scope settings for Spatie roles.
- `app/Services/AdminNavigationBuilder.php` - builds Filament navigation from `menus`.
- `app/Services/DepartmentTree.php` - resolves descendants and prevents department cycles.
- `app/Services/DataScopeResolver.php` - resolves row-level data visibility for an admin user.
- `app/Services/ActivityLogger.php` - consistent audit logging wrapper.
- `app/Policies/MenuPolicy.php`
- `app/Policies/DepartmentPolicy.php`
- `app/Policies/RoleDataScopePolicy.php`
- `app/Policies/ActivityLogPolicy.php`
- `app/Filament/Resources/AdminUsers/AdminUserResource.php`
- `app/Filament/Resources/AdminUsers/Pages/ListAdminUsers.php`
- `app/Filament/Resources/AdminUsers/Pages/CreateAdminUser.php`
- `app/Filament/Resources/AdminUsers/Pages/EditAdminUser.php`
- `app/Filament/Resources/AdminUsers/Pages/ViewAdminUser.php`
- `app/Filament/Resources/LoginLogs/LoginLogResource.php`
- `app/Filament/Resources/LoginLogs/Pages/ListLoginLogs.php`
- `app/Filament/Resources/LoginLogs/Pages/ViewLoginLog.php`
- `app/Filament/Resources/Menus/MenuResource.php`
- `app/Filament/Resources/Menus/Pages/ListMenus.php`
- `app/Filament/Resources/Menus/Pages/CreateMenu.php`
- `app/Filament/Resources/Menus/Pages/EditMenu.php`
- `app/Filament/Resources/Departments/DepartmentResource.php`
- `app/Filament/Resources/Departments/Pages/ListDepartments.php`
- `app/Filament/Resources/Departments/Pages/CreateDepartment.php`
- `app/Filament/Resources/Departments/Pages/EditDepartment.php`
- `app/Filament/Resources/RoleDataScopes/RoleDataScopeResource.php`
- `app/Filament/Resources/RoleDataScopes/Pages/ListRoleDataScopes.php`
- `app/Filament/Resources/RoleDataScopes/Pages/EditRoleDataScope.php`
- `app/Filament/Resources/ActivityLogs/ActivityLogResource.php`
- `app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php`
- `app/Filament/Resources/ActivityLogs/Pages/ViewActivityLog.php`
- `app/Console/Commands/CleanLoginLogs.php`
- `app/Console/Commands/CleanActivityLogs.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_add_status_and_department_to_admin_users_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_create_departments_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_create_menus_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_create_role_data_scopes_table.php`
- `database/seeders/AdminFoundationPermissionSeeder.php`
- `database/seeders/AdminFoundationMenuSeeder.php`
- `database/factories/DepartmentFactory.php`
- `database/factories/MenuFactory.php`
- `database/factories/RoleDataScopeFactory.php`
- `tests/Feature/AdminFoundation/AdminUserResourceTest.php`
- `tests/Feature/AdminFoundation/LoginLogResourceTest.php`
- `tests/Feature/AdminFoundation/MenuResourceTest.php`
- `tests/Feature/AdminFoundation/DepartmentResourceTest.php`
- `tests/Feature/AdminFoundation/DataScopeTest.php`
- `tests/Feature/AdminFoundation/ActivityLogResourceTest.php`
- `tests/Unit/Services/AdminNavigationBuilderTest.php`
- `tests/Unit/Services/DepartmentTreeTest.php`
- `tests/Unit/Services/DataScopeResolverTest.php`
- `docs/features/admin-foundation.md`

Modify:

- `composer.json` / `composer.lock` - add `spatie/laravel-activitylog`.
- `app/Models/AdminUser.php` - status cast, department relation, access guard.
- `app/Models/LoginLog.php` - optional scope helpers.
- `app/Providers/AuthServiceProvider.php` - register new policies.
- `app/Providers/Filament/AdminPanelProvider.php` - dynamic navigation builder.
- `database/factories/AdminUserFactory.php` - status and department state helpers.
- `database/seeders/DatabaseSeeder.php` - include foundation seeders.
- `routes/console.php` - schedule optional log cleanup after commands exist.
- `doc/项目开发规划.md` - mark Phase 1 plan link and update after completion.
- `AGENTS.md` - add references only if implementation introduces new hard rules.

---

## Task 1: Install Activitylog Dependencies

> **Implementation note:** On the current PHP 8.3 environment, use `spatie/laravel-activitylog:^4.12` and `alizharb/filament-activity-log:^1.3`. Do not switch this plan to Activitylog 5.x unless the runtime is upgraded to PHP 8.4+.

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `config/activitylog.php`
- Create: `database/migrations/*_create_activity_log_table.php`

- [ ] **Step 1: Install package**

Run:

```bash
composer require spatie/laravel-activitylog:^4.12 alizharb/filament-activity-log:^1.3 --no-interaction
```

Expected: Composer installs both packages and updates `composer.json` / `composer.lock` without upgrading PHP.

- [ ] **Step 2: Publish config and migration**

Run:

```bash
php artisan vendor:publish --tag="activitylog-config" --force
php artisan vendor:publish --tag="activitylog-migrations" --force
php artisan vendor:publish --tag="filament-activity-log-config" --force
```

Expected: `config/activitylog.php`, `config/filament-activity-log.php`, and `activity_log` migrations are created.

- [ ] **Step 3: Run package smoke test**

Run:

```bash
php artisan test tests/Feature/SmokeTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/activitylog.php config/filament-activity-log.php database/migrations
git commit -m "引入操作日志依赖"
```

## Task 2: Add Admin Status, Departments, Menus, and Data Scope Tables

**Files:**
- Create: `app/Enums/AdminUserStatus.php`
- Create: `app/Enums/DataScope.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_departments_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_status_and_department_to_admin_users_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_menus_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_role_data_scopes_table.php`
- Modify: `app/Models/AdminUser.php`
- Modify: `database/factories/AdminUserFactory.php`
- Test: `tests/Feature/AdminFoundation/AdminUserStatusTest.php`

- [ ] **Step 1: Write failing admin status test**

Create `tests/Feature/AdminFoundation/AdminUserStatusTest.php`:

```php
<?php

use App\Enums\AdminUserStatus;
use App\Models\AdminUser;

it('禁用管理员不能访问后台面板', function () {
    $user = AdminUser::factory()->create([
        'status' => AdminUserStatus::Disabled,
    ]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('启用管理员可以访问后台面板', function () {
    $user = AdminUser::factory()->create([
        'status' => AdminUserStatus::Active,
    ]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/AdminUserStatusTest.php
```

Expected: FAIL because `App\Enums\AdminUserStatus` and `status` do not exist.

- [ ] **Step 3: Create enums**

Create `app/Enums/AdminUserStatus.php`:

```php
<?php

namespace App\Enums;

enum AdminUserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active   => '启用',
            self::Disabled => '禁用',
        };
    }
}
```

Create `app/Enums/DataScope.php`:

```php
<?php

namespace App\Enums;

enum DataScope: string
{
    case All = 'all';
    case Department = 'department';
    case DepartmentAndChildren = 'department_and_children';
    case Self = 'self';
    case CustomDepartments = 'custom_departments';

    public function label(): string
    {
        return match ($this) {
            self::All                   => '全部数据',
            self::Department            => '本部门数据',
            self::DepartmentAndChildren => '本部门及下级部门数据',
            self::Self                  => '仅本人数据',
            self::CustomDepartments     => '指定部门数据',
        };
    }
}
```

- [ ] **Step 4: Create migrations**

Create `database/migrations/YYYY_MM_DD_HHMMSS_create_departments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('leader_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
```

Create `database/migrations/YYYY_MM_DD_HHMMSS_add_status_and_department_to_admin_users_table.php`:

```php
<?php

use App\Enums\AdminUserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('status')->default(AdminUserStatus::Active->value)->after('name');
            $table->foreignId('department_id')->nullable()->after('status')->constrained('departments')->nullOnDelete();
            $table->index(['status', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('status');
        });
    }
};
```

Create `database/migrations/YYYY_MM_DD_HHMMSS_create_menus_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('title');
            $table->string('icon')->nullable();
            $table->string('route_name')->nullable();
            $table->string('url')->nullable();
            $table->string('permission_name')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('target')->default('self');
            $table->string('source')->default('core');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort']);
            $table->index(['is_active', 'permission_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
```

Create `database/migrations/YYYY_MM_DD_HHMMSS_create_role_data_scopes_table.php`:

```php
<?php

use App\Enums\DataScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->string('scope')->default(DataScope::Self->value);
            $table->json('department_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_data_scopes');
    }
};
```

- [ ] **Step 5: Update AdminUser model**

In `app/Models/AdminUser.php`, add imports:

```php
use App\Enums\AdminUserStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

Update casts:

```php
protected function casts(): array
{
    return [
        'email_verified_at'       => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password'                => 'hashed',
        'status'                  => AdminUserStatus::class,
    ];
}
```

Update panel access:

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->status === AdminUserStatus::Active && ! $this->trashed();
}
```

Add department relation:

```php
/**
 * 所属主部门
 *
 * @return BelongsTo<Department, $this>
 */
public function department(): BelongsTo
{
    return $this->belongsTo(Department::class);
}
```

- [ ] **Step 6: Update AdminUserFactory**

In `database/factories/AdminUserFactory.php`, default `status` to active and add a disabled state:

```php
use App\Enums\AdminUserStatus;

// inside definition()
'status' => AdminUserStatus::Active,

public function disabled(): static
{
    return $this->state([
        'status' => AdminUserStatus::Disabled,
    ]);
}
```

- [ ] **Step 7: Verify migrations and tests**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/AdminUserStatusTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Enums app/Models/AdminUser.php database/migrations database/factories/AdminUserFactory.php tests/Feature/AdminFoundation/AdminUserStatusTest.php
git commit -m "feat: 新增管理员状态与基础管理表结构"
```

## Task 3: Add Models and Factories

**Files:**
- Create: `app/Models/Department.php`
- Create: `app/Models/Menu.php`
- Create: `app/Models/RoleDataScope.php`
- Create: `database/factories/DepartmentFactory.php`
- Create: `database/factories/MenuFactory.php`
- Create: `database/factories/RoleDataScopeFactory.php`
- Test: `tests/Unit/Models/DepartmentTest.php`
- Test: `tests/Unit/Models/MenuTest.php`
- Test: `tests/Unit/Models/RoleDataScopeTest.php`

- [ ] **Step 1: Write model tests**

Create `tests/Unit/Models/DepartmentTest.php`:

```php
<?php

use App\Models\AdminUser;
use App\Models\Department;

it('部门支持父子级和负责人关系', function () {
    $leader = AdminUser::factory()->create();
    $parent = Department::factory()->create();
    $child  = Department::factory()->create([
        'parent_id'            => $parent->id,
        'leader_admin_user_id' => $leader->id,
    ]);

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children)->toHaveCount(1)
        ->and($child->leader->is($leader))->toBeTrue();
});
```

Create `tests/Unit/Models/MenuTest.php`:

```php
<?php

use App\Models\Menu;

it('菜单支持父子级和启用作用域', function () {
    $parent = Menu::factory()->create(['is_active' => true]);
    Menu::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
    Menu::factory()->create(['is_active' => false]);

    expect($parent->children)->toHaveCount(1)
        ->and(Menu::active()->count())->toBe(2);
});
```

Create `tests/Unit/Models/RoleDataScopeTest.php`:

```php
<?php

use App\Enums\DataScope;
use App\Models\RoleDataScope;
use Spatie\Permission\Models\Role;

it('角色数据权限保存范围和指定部门', function () {
    $role = Role::create(['name' => '运营', 'guard_name' => 'admin']);

    $scope = RoleDataScope::factory()->create([
        'role_id'        => $role->id,
        'scope'          => DataScope::CustomDepartments,
        'department_ids' => [1, 2],
    ]);

    expect($scope->role->is($role))->toBeTrue()
        ->and($scope->scope)->toBe(DataScope::CustomDepartments)
        ->and($scope->department_ids)->toBe([1, 2]);
});
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
php artisan test tests/Unit/Models/DepartmentTest.php tests/Unit/Models/MenuTest.php tests/Unit/Models/RoleDataScopeTest.php
```

Expected: FAIL because models do not exist.

- [ ] **Step 3: Create Department model**

Create `app/Models/Department.php`:

```php
<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'leader_admin_user_id');
    }

    public function adminUsers(): HasMany
    {
        return $this->hasMany(AdminUser::class);
    }
}
```

- [ ] **Step 4: Create Menu model**

Create `app/Models/Menu.php`:

```php
<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 5: Create RoleDataScope model**

Create `app/Models/RoleDataScope.php`:

```php
<?php

namespace App\Models;

use App\Enums\DataScope;
use Database\Factories\RoleDataScopeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class RoleDataScope extends Model
{
    /** @use HasFactory<RoleDataScopeFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope'          => DataScope::class,
            'department_ids' => 'array',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
```

- [ ] **Step 6: Create factories**

Create `database/factories/DepartmentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'parent_id'            => null,
            'name'                 => $this->faker->unique()->company(),
            'code'                 => $this->faker->unique()->bothify('dept-####'),
            'leader_admin_user_id' => null,
            'sort'                 => 0,
            'is_active'            => true,
        ];
    }
}
```

Create `database/factories/MenuFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Menu> */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        return [
            'parent_id'       => null,
            'title'           => $this->faker->unique()->words(2, true),
            'icon'            => 'heroicon-o-circle-stack',
            'route_name'      => null,
            'url'             => '/admin',
            'permission_name' => null,
            'sort'            => 0,
            'is_active'       => true,
            'target'          => 'self',
            'source'          => 'core',
        ];
    }
}
```

Create `database/factories/RoleDataScopeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\DataScope;
use App\Models\RoleDataScope;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/** @extends Factory<RoleDataScope> */
class RoleDataScopeFactory extends Factory
{
    protected $model = RoleDataScope::class;

    public function definition(): array
    {
        return [
            'role_id'        => Role::create([
                'name'       => '测试角色 ' . $this->faker->unique()->word(),
                'guard_name' => 'admin',
            ])->id,
            'scope'          => DataScope::Self,
            'department_ids' => null,
        ];
    }
}
```

- [ ] **Step 7: Run model tests**

Run:

```bash
php artisan test tests/Unit/Models/DepartmentTest.php tests/Unit/Models/MenuTest.php tests/Unit/Models/RoleDataScopeTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models database/factories tests/Unit/Models
git commit -m "feat: 新增菜单部门与数据权限模型"
```

## Task 4: Build DepartmentTree and DataScopeResolver Services

**Files:**
- Create: `app/Services/DepartmentTree.php`
- Create: `app/Services/DataScopeResolver.php`
- Test: `tests/Unit/Services/DepartmentTreeTest.php`
- Test: `tests/Unit/Services/DataScopeResolverTest.php`

- [ ] **Step 1: Write DepartmentTree tests**

Create `tests/Unit/Services/DepartmentTreeTest.php`:

```php
<?php

use App\Models\Department;
use App\Services\DepartmentTree;

it('获取部门及所有下级部门 ID', function () {
    $root  = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);
    $leaf  = Department::factory()->create(['parent_id' => $child->id]);

    $ids = app(DepartmentTree::class)->selfAndDescendantIds($root);

    expect($ids)->toEqualCanonicalizing([$root->id, $child->id, $leaf->id]);
});

it('阻止部门父级设置成自己或自己的下级', function () {
    $root  = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);

    expect(app(DepartmentTree::class)->canMoveUnder($root, $child))->toBeFalse()
        ->and(app(DepartmentTree::class)->canMoveUnder($root, $root))->toBeFalse();
});
```

- [ ] **Step 2: Write DataScopeResolver tests**

Create `tests/Unit/Services/DataScopeResolverTest.php`:

```php
<?php

use App\Enums\DataScope;
use App\Models\AdminUser;
use App\Models\Department;
use App\Models\RoleDataScope;
use App\Services\DataScopeResolver;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('超级管理员拥有全部数据范围', function () {
    $role = Role::create(['name' => config('filament-admin.super_admin_role'), 'guard_name' => 'admin']);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    $scope = app(DataScopeResolver::class)->resolve($user);

    expect($scope['type'])->toBe(DataScope::All);
});

it('本部门及下级范围返回部门树 ID', function () {
    $root  = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);
    $role  = Role::create(['name' => '部门主管', 'guard_name' => 'admin']);
    $user  = AdminUser::factory()->create(['department_id' => $root->id]);

    $user->assignRole($role);
    RoleDataScope::factory()->create([
        'role_id' => $role->id,
        'scope'   => DataScope::DepartmentAndChildren,
    ]);

    $scope = app(DataScopeResolver::class)->resolve($user);

    expect($scope['type'])->toBe(DataScope::CustomDepartments)
        ->and($scope['department_ids'])->toEqualCanonicalizing([$root->id, $child->id]);
});
```

- [ ] **Step 3: Run tests to verify failure**

Run:

```bash
php artisan test tests/Unit/Services/DepartmentTreeTest.php tests/Unit/Services/DataScopeResolverTest.php
```

Expected: FAIL because services do not exist.

- [ ] **Step 4: Implement DepartmentTree**

Create `app/Services/DepartmentTree.php`:

```php
<?php

namespace App\Services;

use App\Models\Department;

class DepartmentTree
{
    /**
     * @return list<int>
     */
    public function selfAndDescendantIds(Department $department): array
    {
        return [$department->id, ...$this->descendantIds($department)];
    }

    /**
     * @return list<int>
     */
    public function descendantIds(Department $department): array
    {
        $ids = [];

        $department->children()->each(function (Department $child) use (&$ids): void {
            $ids[] = $child->id;
            $ids   = [...$ids, ...$this->descendantIds($child)];
        });

        return array_values(array_unique($ids));
    }

    public function canMoveUnder(Department $department, ?Department $newParent): bool
    {
        if ($newParent === null) {
            return true;
        }

        if ($department->is($newParent)) {
            return false;
        }

        return ! in_array($newParent->id, $this->descendantIds($department), true);
    }
}
```

- [ ] **Step 5: Implement DataScopeResolver**

Create `app/Services/DataScopeResolver.php`:

```php
<?php

namespace App\Services;

use App\Enums\DataScope;
use App\Models\AdminUser;
use App\Models\RoleDataScope;

class DataScopeResolver
{
    public function __construct(private readonly DepartmentTree $departmentTree) {}

    /**
     * @return array{type: DataScope, department_ids?: list<int>, admin_user_id?: int}
     */
    public function resolve(AdminUser $user): array
    {
        if ($user->hasRole(config('filament-admin.super_admin_role'), 'admin')) {
            return ['type' => DataScope::All];
        }

        $roleIds = $user->roles()->pluck('roles.id')->all();
        $scopes  = RoleDataScope::query()->whereIn('role_id', $roleIds)->get();

        if ($scopes->contains(fn (RoleDataScope $scope): bool => $scope->scope === DataScope::All)) {
            return ['type' => DataScope::All];
        }

        $departmentIds = [];
        $hasDepartmentScope = false;

        foreach ($scopes as $scope) {
            if ($scope->scope === DataScope::Department && $user->department_id !== null) {
                $hasDepartmentScope = true;
                $departmentIds[] = $user->department_id;
            }

            if ($scope->scope === DataScope::DepartmentAndChildren && $user->department !== null) {
                $hasDepartmentScope = true;
                $departmentIds = [...$departmentIds, ...$this->departmentTree->selfAndDescendantIds($user->department)];
            }

            if ($scope->scope === DataScope::CustomDepartments) {
                $hasDepartmentScope = true;
                $departmentIds = [...$departmentIds, ...($scope->department_ids ?? [])];
            }
        }

        if ($hasDepartmentScope) {
            return [
                'type'           => DataScope::CustomDepartments,
                'department_ids' => array_values(array_unique(array_map('intval', $departmentIds))),
            ];
        }

        return [
            'type'          => DataScope::Self,
            'admin_user_id' => $user->id,
        ];
    }
}
```

- [ ] **Step 6: Run service tests**

Run:

```bash
php artisan test tests/Unit/Services/DepartmentTreeTest.php tests/Unit/Services/DataScopeResolverTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services tests/Unit/Services
git commit -m "feat: 新增部门树与数据权限解析服务"
```

## Task 5: Register Policies and Foundation Permissions

**Files:**
- Create: `app/Policies/MenuPolicy.php`
- Create: `app/Policies/DepartmentPolicy.php`
- Create: `app/Policies/RoleDataScopePolicy.php`
- Create: `app/Policies/ActivityLogPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Create: `database/seeders/AdminFoundationPermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/AdminFoundation/FoundationPermissionTest.php`

- [ ] **Step 1: Write permission test**

Create `tests/Feature/AdminFoundation/FoundationPermissionTest.php`:

```php
<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('基础管理权限点被种子创建', function () {
    $this->seed(\Database\Seeders\AdminFoundationPermissionSeeder::class);

    foreach ([
        'view_any_admin_user',
        'reset_password_admin_user',
        'assign_role_admin_user',
        'view_any_login_log',
        'view_any_menu',
        'reorder_menu',
        'view_any_department',
        'reorder_department',
        'view_any_role_data_scope',
        'view_any_activity_log',
    ] as $permission) {
        expect(Permission::where('guard_name', 'admin')->where('name', $permission)->exists())->toBeTrue();
    }
});
```

- [ ] **Step 2: Run test to verify failure**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/FoundationPermissionTest.php
```

Expected: FAIL because seeder does not exist.

- [ ] **Step 3: Create policy classes**

Create each policy extending `BasePolicy`:

```php
<?php

namespace App\Policies;

class MenuPolicy extends BasePolicy
{
    public function reorder($user): bool
    {
        return $user->can('reorder_menu');
    }
}
```

```php
<?php

namespace App\Policies;

class DepartmentPolicy extends BasePolicy
{
    public function reorder($user): bool
    {
        return $user->can('reorder_department');
    }
}
```

```php
<?php

namespace App\Policies;

class RoleDataScopePolicy extends BasePolicy
{
    // 全部继承自 BasePolicy。
}
```

```php
<?php

namespace App\Policies;

class ActivityLogPolicy extends BasePolicy
{
    // 全部继承自 BasePolicy。
}
```

- [ ] **Step 4: Register policies**

In `app/Providers/AuthServiceProvider.php`, import models and policies:

```php
use App\Models\Department;
use App\Models\Menu;
use App\Models\RoleDataScope;
use App\Policies\ActivityLogPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\MenuPolicy;
use App\Policies\RoleDataScopePolicy;
use Spatie\Activitylog\Models\Activity;
```

Add to `$policies`:

```php
Menu::class          => MenuPolicy::class,
Department::class    => DepartmentPolicy::class,
RoleDataScope::class => RoleDataScopePolicy::class,
Activity::class      => ActivityLogPolicy::class,
```

- [ ] **Step 5: Create foundation permission seeder**

Create `database/seeders/AdminFoundationPermissionSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AdminFoundationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'admin',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function permissions(): array
    {
        return [
            'view_any_admin_user',
            'view_admin_user',
            'create_admin_user',
            'update_admin_user',
            'delete_admin_user',
            'restore_admin_user',
            'force_delete_admin_user',
            'reset_password_admin_user',
            'assign_role_admin_user',
            'view_any_login_log',
            'view_login_log',
            'view_any_menu',
            'view_menu',
            'create_menu',
            'update_menu',
            'delete_menu',
            'restore_menu',
            'reorder_menu',
            'view_any_department',
            'view_department',
            'create_department',
            'update_department',
            'delete_department',
            'restore_department',
            'reorder_department',
            'view_any_role_data_scope',
            'view_role_data_scope',
            'update_role_data_scope',
            'view_any_activity_log',
            'view_activity_log',
        ];
    }
}
```

- [ ] **Step 6: Register seeder**

In `database/seeders/DatabaseSeeder.php`, add `AdminFoundationPermissionSeeder::class` before `SuperAdminSeeder::class` if super admin role assignment depends on permissions later:

```php
$this->call([
    AdminUserSeeder::class,
    AdminFoundationPermissionSeeder::class,
    SuperAdminSeeder::class,
]);
```

- [ ] **Step 7: Run permission test**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/FoundationPermissionTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Policies app/Providers/AuthServiceProvider.php database/seeders tests/Feature/AdminFoundation/FoundationPermissionTest.php
git commit -m "feat: 注册基础管理权限点"
```

## Task 6: Build AdminUserResource

**Files:**
- Create: `app/Filament/Resources/AdminUsers/AdminUserResource.php`
- Create: `app/Filament/Resources/AdminUsers/Pages/ListAdminUsers.php`
- Create: `app/Filament/Resources/AdminUsers/Pages/CreateAdminUser.php`
- Create: `app/Filament/Resources/AdminUsers/Pages/EditAdminUser.php`
- Create: `app/Filament/Resources/AdminUsers/Pages/ViewAdminUser.php`
- Modify: `app/Policies/AdminUserPolicy.php`
- Test: `tests/Feature/AdminFoundation/AdminUserResourceTest.php`

- [ ] **Step 1: Write resource tests**

Create `tests/Feature/AdminFoundation/AdminUserResourceTest.php`:

```php
<?php

use App\Enums\AdminUserStatus;
use App\Filament\Resources\AdminUsers\AdminUserResource;
use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Models\AdminUser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(\Database\Seeders\AdminFoundationPermissionSeeder::class);
});

it('超级管理员可以访问管理员列表', function () {
    $role = Role::create(['name' => config('filament-admin.super_admin_role'), 'guard_name' => 'admin']);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin')
        ->get(AdminUserResource::getUrl('index'))
        ->assertSuccessful();
});

it('管理员禁用后不能访问后台', function () {
    $admin = AdminUser::factory()->create(['status' => AdminUserStatus::Disabled]);

    expect($admin->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('管理员表单可以分配角色', function () {
    $role = Role::create(['name' => config('filament-admin.super_admin_role'), 'guard_name' => 'admin']);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $targetRole = Role::create(['name' => '运营', 'guard_name' => 'admin']);

    Livewire\Livewire::actingAs($admin, 'admin')
        ->test(\App\Filament\Resources\AdminUsers\Pages\EditAdminUser::class, [
            'record' => $admin->getRouteKey(),
        ])
        ->fillForm([
            'username' => $admin->username,
            'email'    => $admin->email,
            'name'     => $admin->name,
            'status'   => AdminUserStatus::Active->value,
            'roles'    => [$targetRole->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->fresh()->roles()->whereKey($targetRole->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/AdminUserResourceTest.php
```

Expected: FAIL because `AdminUserResource` does not exist.

- [ ] **Step 3: Update AdminUserPolicy for custom actions**

In `app/Policies/AdminUserPolicy.php`, add:

```php
public function resetPassword($user): bool
{
    return $user->can('reset_password_admin_user');
}

public function assignRole($user): bool
{
    return $user->can('assign_role_admin_user');
}
```

- [ ] **Step 4: Create AdminUserResource**

Create `app/Filament/Resources/AdminUsers/AdminUserResource.php` using Filament 5 `Schema`:

```php
<?php

namespace App\Filament\Resources\AdminUsers;

use App\Enums\AdminUserStatus;
use App\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\Resources\AdminUsers\Pages\ViewAdminUser;
use App\Models\AdminUser;
use App\Services\DataScopeResolver;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = '管理员';

    protected static ?string $pluralModelLabel = '管理员管理';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('username')->label('用户名')->required()->maxLength(255),
            TextInput::make('email')->label('邮箱')->email()->required()->maxLength(255),
            TextInput::make('name')->label('姓名')->required()->maxLength(255),
            TextInput::make('password')->label('密码')->password()->dehydrated(fn ($state): bool => filled($state)),
            Select::make('status')
                ->label('状态')
                ->options(collect(AdminUserStatus::cases())->mapWithKeys(fn (AdminUserStatus $status): array => [$status->value => $status->label()]))
                ->required(),
            Select::make('department_id')
                ->label('所属部门')
                ->relationship('department', 'name')
                ->searchable()
                ->preload(),
            Select::make('roles')
                ->label('角色')
                ->relationship('roles', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'admin'))
                ->multiple()
                ->preload()
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')->label('用户名')->searchable(),
                TextColumn::make('email')->label('邮箱')->searchable(),
                TextColumn::make('name')->label('姓名')->searchable(),
                TextColumn::make('status')->label('状态')->badge(),
                TextColumn::make('department.name')->label('部门'),
                TextColumn::make('roles.name')->label('角色')->badge(),
                TextColumn::make('created_at')->label('创建时间')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(collect(AdminUserStatus::cases())->mapWithKeys(fn (AdminUserStatus $status): array => [$status->value => $status->label()])),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['department', 'roles']);
        $user = Auth::guard('admin')->user();

        if (! $user instanceof AdminUser) {
            return $query->whereRaw('1 = 0');
        }

        $scope = app(DataScopeResolver::class)->resolve($user);

        return match ($scope['type']->value) {
            'all' => $query,
            'custom_departments' => $query->whereIn('department_id', $scope['department_ids'] ?? []),
            'self' => $query->whereKey($scope['admin_user_id']),
            default => $query->whereKey($user->id),
        };
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'view'   => ViewAdminUser::route('/{record}'),
            'edit'   => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Create Resource pages**

Create `app/Filament/Resources/AdminUsers/Pages/ListAdminUsers.php`:

```php
<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;
}
```

Create `app/Filament/Resources/AdminUsers/Pages/CreateAdminUser.php`:

```php
<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;
}
```

Create `app/Filament/Resources/AdminUsers/Pages/EditAdminUser.php`:

```php
<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\EditRecord;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;
}
```

Create `app/Filament/Resources/AdminUsers/Pages/ViewAdminUser.php`:

```php
<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminUser extends ViewRecord
{
    protected static string $resource = AdminUserResource::class;
}
```

- [ ] **Step 6: Run resource tests**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/AdminUserResourceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/AdminUsers app/Policies/AdminUserPolicy.php tests/Feature/AdminFoundation/AdminUserResourceTest.php
git commit -m "feat: 完成管理员管理后台资源"
```

## Task 7: Build LoginLogResource and Cleanup Command

**Files:**
- Create: `app/Filament/Resources/LoginLogs/LoginLogResource.php`
- Create: `app/Filament/Resources/LoginLogs/Pages/ListLoginLogs.php`
- Create: `app/Filament/Resources/LoginLogs/Pages/ViewLoginLog.php`
- Create: `app/Console/Commands/CleanLoginLogs.php`
- Test: `tests/Feature/AdminFoundation/LoginLogResourceTest.php`

- [ ] **Step 1: Write tests**

Create `tests/Feature/AdminFoundation/LoginLogResourceTest.php`:

```php
<?php

use App\Filament\Resources\LoginLogs\LoginLogResource;
use App\Models\AdminUser;
use App\Models\LoginLog;
use Spatie\Permission\Models\Role;

it('超级管理员可以查看登录日志列表', function () {
    $role = Role::create(['name' => config('filament-admin.super_admin_role'), 'guard_name' => 'admin']);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);
    LoginLog::factory()->count(2)->create();

    $this->actingAs($admin, 'admin')
        ->get(LoginLogResource::getUrl('index'))
        ->assertSuccessful();
});

it('登录日志清理命令删除过期日志', function () {
    LoginLog::factory()->create(['created_at' => now()->subDays(120)]);
    LoginLog::factory()->create(['created_at' => now()]);

    $this->artisan('filament-admin:clean-login-logs', ['--days' => 90])
        ->assertSuccessful();

    expect(LoginLog::count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/LoginLogResourceTest.php
```

Expected: FAIL because resource and command do not exist.

- [ ] **Step 3: Create LoginLogResource**

Create `app/Filament/Resources/LoginLogs/LoginLogResource.php` with `LoginLog::class` as the model. The Resource must only expose `ViewAction` and must not include delete or edit actions.

Use these table columns and status filter:

```php
TextColumn::make('adminUser.username')->label('管理员')->searchable();
TextColumn::make('username')->label('登录账号')->searchable();
TextColumn::make('status')->label('结果')->badge();
TextColumn::make('ip_address')->label('IP')->searchable();
TextColumn::make('failure_reason')->label('失败原因');
TextColumn::make('created_at')->label('时间')->dateTime()->sortable();

SelectFilter::make('status')->label('结果')->options([
    'success' => '成功',
    'failed'  => '失败',
]);
```

Create `ListLoginLogs` with `ListRecords` and `ViewLoginLog` with `ViewRecord`. Override `getPages()` in the Resource:

```php
public static function getPages(): array
{
    return [
        'index' => ListLoginLogs::route('/'),
        'view'  => ViewLoginLog::route('/{record}'),
    ];
}
```

Override `getEloquentQuery()` with `DataScopeResolver`:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery()->with('adminUser');
    $user = Auth::guard('admin')->user();

    if (! $user instanceof AdminUser) {
        return $query->whereRaw('1 = 0');
    }

    $scope = app(DataScopeResolver::class)->resolve($user);

    return match ($scope['type']->value) {
        'all' => $query,
        'custom_departments' => $query->whereHas('adminUser', fn (Builder $builder): Builder => $builder->whereIn('department_id', $scope['department_ids'] ?? [])),
        'self' => $query->where('admin_user_id', $scope['admin_user_id']),
        default => $query->where('admin_user_id', $user->id),
    };
}
```

- [ ] **Step 4: Create cleanup command**

Create `app/Console/Commands/CleanLoginLogs.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\LoginLog;
use Illuminate\Console\Command;

class CleanLoginLogs extends Command
{
    protected $signature = 'filament-admin:clean-login-logs {--days=90}';

    protected $description = '清理指定天数以前的登录日志';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = LoginLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("已清理 {$deleted} 条登录日志。");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run tests**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/LoginLogResourceTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/LoginLogs app/Console/Commands/CleanLoginLogs.php tests/Feature/AdminFoundation/LoginLogResourceTest.php
git commit -m "feat: 完成管理员登录日志后台资源"
```

## Task 8: Build DepartmentResource

**Files:**
- Create: `app/Filament/Resources/Departments/DepartmentResource.php`
- Create: `app/Filament/Resources/Departments/Pages/ListDepartments.php`
- Create: `app/Filament/Resources/Departments/Pages/CreateDepartment.php`
- Create: `app/Filament/Resources/Departments/Pages/EditDepartment.php`
- Test: `tests/Feature/AdminFoundation/DepartmentResourceTest.php`

- [ ] **Step 1: Write tests**

Create `tests/Feature/AdminFoundation/DepartmentResourceTest.php`:

```php
<?php

use App\Models\Department;
use App\Services\DepartmentTree;

it('部门同级排序可更新', function () {
    $a = Department::factory()->create(['sort' => 1]);
    $b = Department::factory()->create(['sort' => 2]);

    $a->update(['sort' => 2]);
    $b->update(['sort' => 1]);

    expect(Department::orderBy('sort')->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('部门不能移动到自己的下级', function () {
    $root  = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);

    expect(app(DepartmentTree::class)->canMoveUnder($root, $child))->toBeFalse();
});
```

- [ ] **Step 2: Create DepartmentResource**

Create `app/Filament/Resources/Departments/DepartmentResource.php` with `Department::class` as the model. The form must contain these components:

```php
Select::make('parent_id')
    ->label('上级部门')
    ->relationship('parent', 'name')
    ->searchable()
    ->preload();

TextInput::make('name')->label('部门名称')->required()->maxLength(255);
TextInput::make('code')->label('部门编码')->required()->maxLength(255)->unique(ignoreRecord: true);

Select::make('leader_admin_user_id')
    ->label('负责人')
    ->relationship('leader', 'name')
    ->searchable()
    ->preload();

TextInput::make('sort')->label('排序')->numeric()->default(0);
Toggle::make('is_active')->label('启用')->default(true);
```

Create `ListDepartments`, `CreateDepartment`, and `EditDepartment`. Each class must set `protected static string $resource = DepartmentResource::class;`; the base classes are `ListRecords`, `CreateRecord`, and `EditRecord`. In `table()`, add:

```php
->reorderable('sort')
->defaultSort('sort')
```

- [ ] **Step 3: Add reorder authorization**

In `DepartmentResource`, ensure reorder is authorized:

```php
public static function canReorder(): bool
{
    return auth('admin')->user()?->can('reorder_department') ?? false;
}
```

- [ ] **Step 4: Run tests**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/DepartmentResourceTest.php tests/Unit/Services/DepartmentTreeTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Departments tests/Feature/AdminFoundation/DepartmentResourceTest.php
git commit -m "feat: 完成部门组织后台资源"
```

## Task 9: Build RoleDataScopeResource

**Files:**
- Create: `app/Filament/Resources/RoleDataScopes/RoleDataScopeResource.php`
- Create: `app/Filament/Resources/RoleDataScopes/Pages/ListRoleDataScopes.php`
- Create: `app/Filament/Resources/RoleDataScopes/Pages/EditRoleDataScope.php`
- Test: `tests/Feature/AdminFoundation/DataScopeTest.php`

- [ ] **Step 1: Write tests**

Create `tests/Feature/AdminFoundation/DataScopeTest.php`:

```php
<?php

use App\Enums\DataScope;
use App\Models\AdminUser;
use App\Models\Department;
use App\Models\RoleDataScope;
use App\Services\DataScopeResolver;
use Spatie\Permission\Models\Role;

it('指定部门数据范围只返回配置部门', function () {
    $department = Department::factory()->create();
    $role = Role::create(['name' => '指定部门角色', 'guard_name' => 'admin']);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    RoleDataScope::factory()->create([
        'role_id'        => $role->id,
        'scope'          => DataScope::CustomDepartments,
        'department_ids' => [$department->id],
    ]);

    $scope = app(DataScopeResolver::class)->resolve($user);

    expect($scope['department_ids'])->toBe([$department->id]);
});
```

- [ ] **Step 2: Create RoleDataScopeResource**

Create `app/Filament/Resources/RoleDataScopes/RoleDataScopeResource.php` with `RoleDataScope::class` as the model.

Form fields:

```php
Select::make('role_id')
    ->label('角色')
    ->relationship('role', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'admin'))
    ->required()
    ->unique(ignoreRecord: true);

Select::make('scope')
    ->label('数据范围')
    ->options(collect(DataScope::cases())->mapWithKeys(fn (DataScope $scope): array => [$scope->value => $scope->label()]))
    ->required();

Select::make('department_ids')
    ->label('指定部门')
    ->multiple()
    ->options(Department::query()->where('is_active', true)->pluck('name', 'id'))
    ->visible(fn ($get): bool => $get('scope') === DataScope::CustomDepartments->value);
```

Create `ListRoleDataScopes` with `ListRecords` and `EditRoleDataScope` with `EditRecord`. Override `getPages()`:

```php
public static function getPages(): array
{
    return [
        'index' => ListRoleDataScopes::route('/'),
        'edit'  => EditRoleDataScope::route('/{record}/edit'),
    ];
}
```

- [ ] **Step 3: Run tests**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/DataScopeTest.php tests/Unit/Services/DataScopeResolverTest.php
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/RoleDataScopes tests/Feature/AdminFoundation/DataScopeTest.php
git commit -m "feat: 完成角色数据权限配置"
```

## Task 10: Build MenuResource and Dynamic Navigation

**Files:**
- Create: `app/Services/AdminNavigationBuilder.php`
- Create: `app/Filament/Resources/Menus/MenuResource.php`
- Create: `app/Filament/Resources/Menus/Pages/ListMenus.php`
- Create: `app/Filament/Resources/Menus/Pages/CreateMenu.php`
- Create: `app/Filament/Resources/Menus/Pages/EditMenu.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Test: `tests/Unit/Services/AdminNavigationBuilderTest.php`
- Test: `tests/Feature/AdminFoundation/MenuResourceTest.php`

- [ ] **Step 1: Write navigation builder tests**

Create `tests/Unit/Services/AdminNavigationBuilderTest.php`:

```php
<?php

use App\Models\AdminUser;
use App\Models\Menu;
use App\Services\AdminNavigationBuilder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('无权限菜单不会出现在导航中', function () {
    Permission::create(['name' => 'view_secret_menu', 'guard_name' => 'admin']);
    $role = Role::create(['name' => '普通管理员', 'guard_name' => 'admin']);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    Menu::factory()->create(['title' => '公开菜单', 'permission_name' => null, 'sort' => 1]);
    Menu::factory()->create(['title' => '秘密菜单', 'permission_name' => 'view_secret_menu', 'sort' => 2]);

    $groups = app(AdminNavigationBuilder::class)->build($user);
    $labels = collect($groups)->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getLabel())->all();

    expect($labels)->toContain('公开菜单')
        ->and($labels)->not->toContain('秘密菜单');
});
```

- [ ] **Step 2: Implement AdminNavigationBuilder**

Create `app/Services/AdminNavigationBuilder.php`:

```php
<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Menu;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Route;

class AdminNavigationBuilder
{
    /**
     * @return array<NavigationGroup>
     */
    public function build(?AdminUser $user): array
    {
        if (! $user) {
            return [];
        }

        $menus = Menu::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->active()->orderBy('sort')])
            ->orderBy('sort')
            ->get()
            ->filter(fn (Menu $menu): bool => $this->isVisibleTo($menu, $user));

        $items = $menus->map(fn (Menu $menu): NavigationItem => $this->toNavigationItem($menu, $user))->all();

        return [
            NavigationGroup::make('基础管理')->items($items),
        ];
    }

    private function isVisibleTo(Menu $menu, AdminUser $user): bool
    {
        return blank($menu->permission_name) || $user->can($menu->permission_name);
    }

    private function toNavigationItem(Menu $menu, AdminUser $user): NavigationItem
    {
        return NavigationItem::make($menu->title)
            ->icon($menu->icon)
            ->sort($menu->sort)
            ->url($this->resolveUrl($menu), $menu->target === 'blank')
            ->visible($this->isVisibleTo($menu, $user));
    }

    private function resolveUrl(Menu $menu): ?string
    {
        if (filled($menu->route_name) && Route::has($menu->route_name)) {
            return route($menu->route_name);
        }

        return $menu->url;
    }
}
```

- [ ] **Step 3: Wire Panel navigation**

In `app/Providers/Filament/AdminPanelProvider.php`, add imports:

```php
use App\Models\AdminUser;
use App\Services\AdminNavigationBuilder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
```

Add after resource/page discovery:

```php
->navigation(function (AdminNavigationBuilder $builder): NavigationBuilder {
    $user = Filament::auth()->user();

    return NavigationBuilder::make()
        ->groups($builder->build($user instanceof AdminUser ? $user : null));
})
```

- [ ] **Step 4: Create MenuResource**

Create `app/Filament/Resources/Menus/MenuResource.php` with `Menu::class` as the model. The form must contain these components:

```php
Select::make('parent_id')
    ->label('上级菜单')
    ->relationship('parent', 'title')
    ->searchable()
    ->preload();

TextInput::make('title')->label('菜单名称')->required()->maxLength(255);
TextInput::make('icon')->label('图标')->maxLength(255);
TextInput::make('route_name')->label('路由名称')->maxLength(255);
TextInput::make('url')->label('URL')->maxLength(255);

Select::make('permission_name')
    ->label('绑定权限')
    ->options(fn (): array => \Spatie\Permission\Models\Permission::query()
        ->where('guard_name', 'admin')
        ->orderBy('name')
        ->pluck('name', 'name')
        ->all())
    ->searchable();

TextInput::make('sort')->label('排序')->numeric()->default(0);
Toggle::make('is_active')->label('启用')->default(true);
Select::make('target')->label('打开方式')->options(['self' => '当前窗口', 'blank' => '新窗口'])->default('self');
```

Create `ListMenus`, `CreateMenu`, and `EditMenu`. Each class must set `protected static string $resource = MenuResource::class;`; the base classes are `ListRecords`, `CreateRecord`, and `EditRecord`. In table:

```php
->reorderable('sort')
->defaultSort('sort')
```

Add `canReorder()`:

```php
public static function canReorder(): bool
{
    return auth('admin')->user()?->can('reorder_menu') ?? false;
}
```

- [ ] **Step 5: Run tests**

Run:

```bash
php artisan test tests/Unit/Services/AdminNavigationBuilderTest.php tests/Feature/AdminFoundation/MenuResourceTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AdminNavigationBuilder.php app/Providers/Filament/AdminPanelProvider.php app/Filament/Resources/Menus tests/Unit/Services/AdminNavigationBuilderTest.php tests/Feature/AdminFoundation/MenuResourceTest.php
git commit -m "feat: 完成菜单规则与动态导航"
```

## Task 11: Build Activity Logging

> **Implementation note:** Reuse the vendor `AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource` for the list/view experience. Do not hand-write `app/Filament/Resources/ActivityLogs/*` unless the plugin route later proves insufficient.

**Files:**
- Create: `app/Services/ActivityLogger.php`
- Create: `app/Filament/Resources/ActivityLogs/ActivityLogResource.php`
- Create: `app/Filament/Resources/ActivityLogs/Pages/ListActivityLogs.php`
- Create: `app/Filament/Resources/ActivityLogs/Pages/ViewActivityLog.php`
- Create: `app/Console/Commands/CleanActivityLogs.php`
- Test: `tests/Feature/AdminFoundation/ActivityLogResourceTest.php`

- [ ] **Step 1: Write tests**

Create `tests/Feature/AdminFoundation/ActivityLogResourceTest.php`:

```php
<?php

use App\Models\AdminUser;
use App\Services\ActivityLogger;
use Spatie\Activitylog\Models\Activity;

it('操作日志服务记录操作人和变更内容', function () {
    $admin = AdminUser::factory()->create();
    $target = AdminUser::factory()->create(['name' => '旧名称']);

    app(ActivityLogger::class)->log(
        causer: $admin,
        subject: $target,
        action: 'updated',
        before: ['name' => '旧名称'],
        after: ['name' => '新名称'],
    );

    $activity = Activity::query()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer->is($admin))->toBeTrue()
        ->and($activity->properties['before']['name'])->toBe('旧名称')
        ->and($activity->properties['after']['name'])->toBe('新名称');
});
```

- [ ] **Step 2: Implement ActivityLogger**

Create `app/Services/ActivityLogger.php`:

```php
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class ActivityLogger
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function log(User $causer, Model $subject, string $action, array $before = [], array $after = []): void
    {
        activity('admin')
            ->causedBy($causer)
            ->performedOn($subject)
            ->withProperties([
                'action'     => $action,
                'before'     => $before,
                'after'      => $after,
                'ip'         => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ])
            ->event($action)
            ->log($action);
    }
}
```

- [ ] **Step 3: Add ActivityLogResource**

Create `app/Filament/Resources/ActivityLogs/ActivityLogResource.php` with `Spatie\Activitylog\Models\Activity` as the model. The Resource must be read-only:

```php
public static function canCreate(): bool
{
    return false;
}
```

Use these table columns:

```php
TextColumn::make('log_name')->label('日志类型');
TextColumn::make('event')->label('动作');
TextColumn::make('causer.name')->label('操作人');
TextColumn::make('subject_type')->label('对象类型');
TextColumn::make('created_at')->label('时间')->dateTime()->sortable();
```

Create `ListActivityLogs` with `ListRecords` and `ViewActivityLog` with `ViewRecord`. Override `getPages()`:

```php
public static function getPages(): array
{
    return [
        'index' => ListActivityLogs::route('/'),
        'view'  => ViewActivityLog::route('/{record}'),
    ];
}
```

- [ ] **Step 4: Create cleanup command**

Create `app/Console/Commands/CleanActivityLogs.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class CleanActivityLogs extends Command
{
    protected $signature = 'filament-admin:clean-activity-logs {--days=180}';

    protected $description = '清理指定天数以前的操作日志';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = Activity::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("已清理 {$deleted} 条操作日志。");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run tests**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/ActivityLogResourceTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/ActivityLogger.php app/Filament/Resources/ActivityLogs app/Console/Commands/CleanActivityLogs.php tests/Feature/AdminFoundation/ActivityLogResourceTest.php
git commit -m "feat: 完成操作日志审计基础"
```

## Task 12: Seed Initial Foundation Menus

**Files:**
- Create: `database/seeders/AdminFoundationMenuSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/AdminFoundation/FoundationMenuSeederTest.php`

- [ ] **Step 1: Write seeder test**

Create `tests/Feature/AdminFoundation/FoundationMenuSeederTest.php`:

```php
<?php

use App\Models\Menu;

it('基础管理菜单种子创建核心菜单', function () {
    $this->seed(\Database\Seeders\AdminFoundationMenuSeeder::class);

    expect(Menu::where('title', '管理员管理')->exists())->toBeTrue()
        ->and(Menu::where('title', '管理员日志')->exists())->toBeTrue()
        ->and(Menu::where('title', '菜单规则')->exists())->toBeTrue()
        ->and(Menu::where('title', '部门管理')->exists())->toBeTrue()
        ->and(Menu::where('title', '数据权限')->exists())->toBeTrue()
        ->and(Menu::where('title', '操作日志')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Create menu seeder**

Create `database/seeders/AdminFoundationMenuSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class AdminFoundationMenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->menus() as $index => $menu) {
            Menu::updateOrCreate(
                ['title' => $menu['title'], 'source' => 'core'],
                ['sort' => ($index + 1) * 10, 'is_active' => true, ...$menu],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function menus(): array
    {
        return [
            ['title' => '管理员管理', 'icon' => 'heroicon-o-users', 'route_name' => 'filament.admin.resources.admin-users.index', 'permission_name' => 'view_any_admin_user'],
            ['title' => '管理员日志', 'icon' => 'heroicon-o-clipboard-document-list', 'route_name' => 'filament.admin.resources.login-logs.index', 'permission_name' => 'view_any_login_log'],
            ['title' => '角色管理', 'icon' => 'heroicon-o-shield-check', 'route_name' => null, 'url' => '/admin/roles', 'permission_name' => null],
            ['title' => '菜单规则', 'icon' => 'heroicon-o-bars-3', 'route_name' => 'filament.admin.resources.menus.index', 'permission_name' => 'view_any_menu'],
            ['title' => '部门管理', 'icon' => 'heroicon-o-building-office', 'route_name' => 'filament.admin.resources.departments.index', 'permission_name' => 'view_any_department'],
            ['title' => '数据权限', 'icon' => 'heroicon-o-adjustments-horizontal', 'route_name' => 'filament.admin.resources.role-data-scopes.index', 'permission_name' => 'view_any_role_data_scope'],
            ['title' => '操作日志', 'icon' => 'heroicon-o-clock', 'route_name' => 'filament.admin.resources.activity-logs.index', 'permission_name' => 'view_any_activity_log'],
        ];
    }
}
```

- [ ] **Step 3: Register seeder**

In `DatabaseSeeder`, add the menu seeder after `AdminFoundationPermissionSeeder::class`:

```php
$this->call([
    AdminUserSeeder::class,
    AdminFoundationPermissionSeeder::class,
    SuperAdminSeeder::class,
    AdminFoundationMenuSeeder::class,
]);
```

- [ ] **Step 4: Run seeder test**

Run:

```bash
php artisan test tests/Feature/AdminFoundation/FoundationMenuSeederTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/seeders tests/Feature/AdminFoundation/FoundationMenuSeederTest.php
git commit -m "feat: 新增基础管理菜单种子"
```

## Task 13: Documentation and Final Verification

**Files:**
- Create: `docs/features/admin-foundation.md`
- Modify: `doc/项目开发规划.md`
- Modify: `AGENTS.md` if new hard rules need to be remembered

- [ ] **Step 1: Create feature documentation**

Create `docs/features/admin-foundation.md`:

```markdown
# 后台基础管理

后台基础管理是一组后台自管理能力，包含管理员管理、管理员日志、角色与权限、菜单规则、部门组织、数据权限和操作日志。

## 功能清单

- 管理员管理：创建、编辑、禁用、删除、恢复、密码重置、角色分配、部门归属。
- 管理员日志：查看登录成功和失败记录，支持按账号、IP、状态、时间筛选。
- 角色与权限：使用 Filament Shield 自带角色管理，权限点使用 admin guard。
- 菜单规则：菜单数据接管后台左侧导航，支持权限绑定和排序。
- 部门组织：维护部门树，给管理员绑定主部门。
- 数据权限：按角色配置全部数据、本部门、本部门及下级、仅本人、指定部门。
- 操作日志：记录后台关键操作。

## 开发约定

- 后台管理员使用 `admin` guard 和 `admin_users` 表。
- 角色和权限必须使用 `guard_name = admin`。
- 不自写 RoleResource，继续使用 Shield 自带角色资源。
- 菜单只控制导航展示，不替代 Filament Resource 路由注册。
- 数据权限先做角色级范围，不做字段级权限。
```

- [ ] **Step 2: Update project plan**

In `doc/项目开发规划.md`, update the Phase 1 section to mention implementation plan path:

```markdown
详细实施计划见：`docs/superpowers/plans/2026-05-29-phase-1-admin-foundation.md`。
```

- [ ] **Step 3: Run full verification**

Run:

```bash
composer test
composer phpstan
composer pint:test
```

Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add docs/features/admin-foundation.md doc/项目开发规划.md AGENTS.md
git commit -m "docs: 补充后台基础管理功能文档"
```

## Self-Review Checklist

- Spec coverage: all confirmed Phase 1 requirements map to tasks.
- Admin management: Task 2, Task 6.
- Login logs: Task 7.
- Roles and permissions: Task 5, Task 6, Task 9.
- Menu CRUD, reorder, dynamic navigation: Task 10, Task 12.
- Departments: Task 2, Task 3, Task 4, Task 8.
- Data scope: Task 2, Task 3, Task 4, Task 9.
- Activity logs: Task 1, Task 11.
- Documentation: Task 13.
- Placeholder scan: no undecided scope remains.
- Type consistency: `AdminUserStatus`, `DataScope`, `DepartmentTree`, `DataScopeResolver`, and `AdminNavigationBuilder` names are consistent across tasks.
