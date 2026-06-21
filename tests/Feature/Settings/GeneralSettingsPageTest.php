<?php

use Filamentboot\Models\AdminUser;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $role = Role::firstOrCreate([
        'name'       => 'super_admin',
        'guard_name' => 'admin',
    ]);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);
    actingAs($user, 'admin');
});

it('基础配置页面可以渲染', function () {
    $this->get('/admin/settings/general')
        ->assertOk();
});
