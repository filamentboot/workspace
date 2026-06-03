<?php

use FilamentAdmin\Database\Seeders\AdminFoundationPermissionSeeder;
use FilamentAdmin\Enums\AdminUserStatus;
use FilamentAdmin\Filament\Resources\AdminUsers\AdminUserResource;
use FilamentAdmin\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use FilamentAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(AdminFoundationPermissionSeeder::class);
});

it('超级管理员可以访问管理员列表', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin')
        ->get(AdminUserResource::getUrl('index'))
        ->assertSuccessful();
});

it('管理员禁用后不能访问后台', function () {
    $admin = AdminUser::factory()->create([
        'status' => AdminUserStatus::Disabled,
    ]);

    expect($admin->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('管理员表单可以分配角色', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $targetRole = Role::create([
        'name'       => '运营',
        'guard_name' => 'admin',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(EditAdminUser::class, [
            'record' => $admin->getRouteKey(),
        ])
        ->fillForm([
            'account'  => $admin->account,
            'email'    => $admin->email,
            'nickname' => $admin->nickname,
            'status'   => AdminUserStatus::Active->value,
            'roles'    => [$targetRole->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->fresh()->roles()->whereKey($targetRole->id)->exists())->toBeTrue();
});

it('没有重置密码权限的管理员不能修改目标管理员密码', function () {
    $role = Role::create([
        'name'       => '管理员编辑者',
        'guard_name' => 'admin',
    ]);
    $role->givePermissionTo([
        'view_any_admin_user',
        'view_admin_user',
        'update_admin_user',
    ]);

    $actor = AdminUser::factory()->create([
        'password' => 'old-secret',
    ]);
    $actor->assignRole($role);

    $component = Livewire::actingAs($actor, 'admin')
        ->test(EditAdminUser::class, [
            'record' => $actor->getRouteKey(),
        ])
        ->assertFormFieldDoesNotExist('password');

    $method = new ReflectionMethod($component->instance(), 'mutateFormDataBeforeSave');
    $method->setAccessible(true);

    $mutatedData = $method->invoke($component->instance(), [
        'account'  => $actor->account,
        'email'    => $actor->email,
        'nickname' => $actor->nickname,
        'status'   => $actor->status?->value ?? AdminUserStatus::Active->value,
        'password' => 'new-secret',
    ]);

    expect($mutatedData)->not->toHaveKey('password');
});

it('拥有重置密码权限的管理员可以修改目标管理员密码', function () {
    $role = Role::create([
        'name'       => '密码管理员',
        'guard_name' => 'admin',
    ]);
    $role->givePermissionTo([
        'view_any_admin_user',
        'view_admin_user',
        'update_admin_user',
        'reset_password_admin_user',
    ]);

    $actor = AdminUser::factory()->create([
        'password' => 'old-secret',
    ]);
    $actor->assignRole($role);

    Livewire::actingAs($actor, 'admin')
        ->test(EditAdminUser::class, [
            'record' => $actor->getRouteKey(),
        ])
        ->fillForm([
            'account'  => $actor->account,
            'email'    => $actor->email,
            'nickname' => $actor->nickname,
            'status'   => AdminUserStatus::Active->value,
            'password' => 'new-secret',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-secret', $actor->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-secret', $actor->fresh()->password))->toBeFalse();
});
