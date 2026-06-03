<?php

use FilamentAdmin\Database\Seeders\AdminFoundationMenuSeeder;
use FilamentAdmin\Models\Menu;

it('系统配置菜单组存在于数据库', function () {
    $this->seed(AdminFoundationMenuSeeder::class);

    expect(Menu::where('title', '系统配置')->where('parent_id', 0)->exists())->toBeTrue();
});

it('系统配置子菜单存在', function () {
    $this->seed(AdminFoundationMenuSeeder::class);

    $parent = Menu::where('title', '系统配置')->where('parent_id', 0)->first();

    expect(Menu::where('title', '基础配置')->where('parent_id', $parent->id)->exists())->toBeTrue()
        ->and(Menu::where('title', '上传配置')->where('parent_id', $parent->id)->exists())->toBeTrue()
        ->and(Menu::where('title', '安全配置')->where('parent_id', $parent->id)->exists())->toBeTrue()
        ->and(Menu::where('title', '日志配置')->where('parent_id', $parent->id)->exists())->toBeTrue();
});
