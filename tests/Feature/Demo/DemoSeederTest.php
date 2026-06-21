<?php

use Filamentboot\Database\Seeders\DemoSeeder;
use Filamentboot\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

/**
 * DemoSeeder 测试
 *
 * 验证演示账号种子：创建可登录的 demo@example.com / demo123
 * 账号，分配 super_admin 角色，且二次运行幂等。
 */

it('DemoSeeder 运行后演示账号存在于数据库', function () {
    $this->seed(DemoSeeder::class);

    $user = AdminUser::where('email', 'demo@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->account)->toBe('demo');
});

it('演示账号密码 demo123 通过 Hash::check 校验', function () {
    $this->seed(DemoSeeder::class);

    $user = AdminUser::where('email', 'demo@example.com')->first();

    expect(Hash::check('demo123', $user->password))->toBeTrue();
});

it('演示账号已分配 super_admin 角色', function () {
    $this->seed(DemoSeeder::class);

    $user = AdminUser::where('email', 'demo@example.com')->first();

    expect($user->hasRole('super_admin'))->toBeTrue();
});

it('重复运行 DemoSeeder 两次不产生重复账号', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $count = AdminUser::where('email', 'demo@example.com')->count();

    expect($count)->toBe(1);
});
