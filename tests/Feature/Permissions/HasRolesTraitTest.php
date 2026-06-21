<?php

use Filamentboot\Models\AdminUser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('AdminUser 可以分配角色', function () {
    $role  = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $admin = AdminUser::factory()->create();

    $admin->assignRole($role);

    expect($admin->hasRole('editor'))->toBeTrue();
});
