<?php

use Filamentboot\Database\Seeders\AdminFoundationMenuSeeder;
use Filamentboot\Models\Menu;

it('基础管理菜单种子创建核心菜单', function () {
    $this->seed(AdminFoundationMenuSeeder::class);

    expect(Menu::query()->where('title', '管理员管理')->exists())->toBeTrue()
        ->and(Menu::query()->where('title', '管理员日志')->exists())->toBeTrue()
        ->and(Menu::query()->where('title', '角色管理')->exists())->toBeTrue()
        ->and(Menu::query()->where('title', '菜单规则')->exists())->toBeTrue()
        ->and(Menu::query()->where('title', '部门管理')->exists())->toBeTrue()
        ->and(Menu::query()->where('title', '操作日志')->exists())->toBeTrue()
        ->and(Menu::query()->where('title', '数据权限')->exists())->toBeFalse();
});
