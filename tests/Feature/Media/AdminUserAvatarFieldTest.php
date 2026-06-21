<?php

use Filamentboot\Models\AdminUser;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);
    actingAs($user, 'admin');
});

it('管理员编辑表单包含头像上传区域', function () {
    $target = AdminUser::factory()->create();

    $this->get("/admin/admin-users/{$target->id}/edit")
        ->assertOk()
        ->assertSee('avatar');
});
