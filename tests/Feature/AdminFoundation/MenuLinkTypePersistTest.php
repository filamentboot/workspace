<?php

// 菜单 link_type 字段持久化测试
// 验证 menus.link_type 列可以正确写入与回读，
// 覆盖 FIX-02：link_type Radio 从 dehydrated(false) 改为正常持久化。

use FilamentAdmin\Models\Menu;

it('link_type=url 的菜单可以正确持久化与回读', function () {
    $menu = Menu::create([
        'parent_id'       => 0,
        'title'           => '测试外链菜单',
        'link_type'       => 'url',
        'url'             => 'https://example.com',
        'route_name'      => null,
        'permission_name' => null,
        'sort'            => 0,
        'is_active'       => true,
        'type'            => 'menu',
        'target'          => 'self',
        'source'          => 'core',
    ]);

    $fresh = Menu::find($menu->id);

    expect($fresh->link_type)->toBe('url');
    expect($fresh->url)->toBe('https://example.com');
});

it('link_type=route 的菜单可以正确持久化与回读', function () {
    $menu = Menu::create([
        'parent_id'       => 0,
        'title'           => '测试路由菜单',
        'link_type'       => 'route',
        'url'             => null,
        'route_name'      => 'filament.admin.pages.dashboard',
        'permission_name' => null,
        'sort'            => 1,
        'is_active'       => true,
        'type'            => 'menu',
        'target'          => 'self',
        'source'          => 'core',
    ]);

    $fresh = Menu::find($menu->id);

    expect($fresh->link_type)->toBe('route');
    expect($fresh->route_name)->toBe('filament.admin.pages.dashboard');
});
