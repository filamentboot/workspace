<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Menu;
use FilamentAdmin\Services\AdminNavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * 用例 A：顶级无子项菜单（有效 url）应作为可点击导航项出现
 *
 * 根节点 parent_id=0，无子菜单，但有 url，应渲染为一个可点击的顶级导航项。
 */
it('无子项但有 url 的顶级菜单应作为可点击导航项出现', function () {
    $admin = AdminUser::factory()->create();

    // 建一个 parent_id=0（根）的顶级菜单，无子项，带有效 url
    $topMenu = Menu::factory()->create([
        'parent_id'       => 0,
        'title'           => '独立菜单',
        'url'             => 'https://example.com/standalone',
        'route_name'      => null,
        'permission_name' => null,
        'is_active'       => true,
        'type'            => 'menu',
        'sort'            => 1,
    ]);

    $builder = new AdminNavigationBuilder();
    $result  = $builder->build($admin);

    // 结果应包含该顶级导航项
    $urls = collect($result)->flatMap(function ($item) {
        if ($item instanceof NavigationItem) {
            return [$item->getUrl()];
        }

        if ($item instanceof NavigationGroup) {
            return collect($item->getItems())->map(fn ($i) => $i->getUrl())->toArray();
        }

        return [];
    })->toArray();

    // 顶级可点击项本身应出现在结果中
    $topLevelTitles = collect($result)->map(function ($item) {
        if ($item instanceof NavigationItem) {
            return $item->getLabel();
        }

        return null;
    })->filter()->values()->toArray();

    expect($topLevelTitles)->toContain('独立菜单');
});

/**
 * 用例 B：顶级菜单有子菜单时，渲染为分组（保持原行为）
 *
 * 根节点 parent_id=0，有一个有效子菜单，应渲染为 NavigationGroup 并包含子项。
 */
it('有子菜单的顶级菜单应渲染为分组且包含子项', function () {
    $admin = AdminUser::factory()->create();

    $topMenu = Menu::factory()->create([
        'parent_id'       => 0,
        'title'           => '系统管理',
        'url'             => null,
        'route_name'      => null,
        'permission_name' => null,
        'is_active'       => true,
        'type'            => 'menu',
        'sort'            => 1,
    ]);

    $childMenu = Menu::factory()->create([
        'parent_id'       => $topMenu->id,
        'title'           => '子菜单项',
        'url'             => 'https://example.com/child',
        'route_name'      => null,
        'permission_name' => null,
        'is_active'       => true,
        'type'            => 'menu',
        'sort'            => 1,
    ]);

    $builder = new AdminNavigationBuilder();
    $result  = $builder->build($admin);

    // 结果应包含该 NavigationGroup
    $groups = collect($result)->filter(fn ($item) => $item instanceof NavigationGroup)->values();
    expect($groups)->toHaveCount(1);

    $group = $groups->first();
    expect($group->getLabel())->toBe('系统管理');

    // 组内应包含子菜单
    $itemLabels = collect($group->getItems())->map(fn ($item) => $item->getLabel())->toArray();
    expect($itemLabels)->toContain('子菜单项');
});

/**
 * 用例 C：顶级菜单无子项且无 url/route，应被跳过（不产生空组）
 *
 * 根节点 parent_id=0，无子菜单，且无 url/route_name，应被过滤掉。
 */
it('无子项且无 url 的顶级菜单应被跳过', function () {
    $admin = AdminUser::factory()->create();

    Menu::factory()->create([
        'parent_id'       => 0,
        'title'           => '空壳菜单',
        'url'             => null,
        'route_name'      => null,
        'permission_name' => null,
        'is_active'       => true,
        'type'            => 'menu',
        'sort'            => 1,
    ]);

    $builder = new AdminNavigationBuilder();
    $result  = $builder->build($admin);

    // 结果应为空（没有任何导航项）
    expect($result)->toBeEmpty();
});

/**
 * 用例 D：parent_id 为 NULL 的历史行（防御性兼容）
 *
 * 直接 DB 写入绕过 NOT NULL 约束，插入一行 parent_id=NULL 的根菜单。
 * 当前列约束为 NOT NULL，先放宽约束，插入后恢复。
 */
it('parent_id 为 NULL 的历史根菜单也应被识别为根（防御性兼容）', function () {
    $admin = AdminUser::factory()->create();

    // 临时放宽 parent_id 列约束以插入 NULL 值（模拟历史数据）
    DB::statement('ALTER TABLE menus MODIFY parent_id BIGINT UNSIGNED NULL DEFAULT 0');

    DB::table('menus')->insert([
        'parent_id'       => null,
        'title'           => 'NULL根菜单',
        'icon'            => null,
        'route_name'      => null,
        'url'             => 'https://example.com/null-root',
        'permission_name' => null,
        'sort'            => 1,
        'is_active'       => 1,
        'target'          => 'self',
        'source'          => 'core',
        'type'            => 'menu',
        'created_at'      => now(),
        'updated_at'      => now(),
        'deleted_at'      => null,
    ]);

    $builder = new AdminNavigationBuilder();
    $result  = $builder->build($admin);

    // null 根菜单也应出现在顶级导航项中
    $topLevelTitles = collect($result)->map(function ($item) {
        if ($item instanceof NavigationItem) {
            return $item->getLabel();
        }

        return null;
    })->filter()->values()->toArray();

    expect($topLevelTitles)->toContain('NULL根菜单');
});
