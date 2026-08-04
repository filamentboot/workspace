<?php

use Filamentboot\FilamentbootSite\Database\Seeders\SitePermissionSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * 官网三层角色种子测试（#19）
 *
 * 覆盖场景：
 * - 三个角色被创建，权限逐层递增
 * - 内容编辑**没有** publish_site_page（分层的核心约束）
 * - 站点级权限只给站点管理
 * - 权限点缺失时不抛异常（下游可能只装了部分功能）
 * - 幂等：重跑不产生重复角色
 *
 * @group site
 */

/**
 * 跑权限点 + 角色两个种子
 */
function seedSiteRoles(): void
{
    (new SitePermissionSeeder)->run();
    (new SiteRoleSeeder)->run();
}

/**
 * 三个角色都被创建
 */
it('创建三个官网角色', function () {
    seedSiteRoles();

    foreach (['内容编辑', '内容发布', '站点管理'] as $name) {
        expect(Role::where('name', $name)->where('guard_name', 'admin')->exists())
            ->toBeTrue("角色「{$name}」未创建");
    }
});

/**
 * 内容编辑能写内容但不能发布——分层的核心约束
 */
it('内容编辑不能发布页面', function () {
    seedSiteRoles();

    $role = Role::where('name', '内容编辑')->firstOrFail();
    $held = $role->permissions->pluck('name');

    expect($held)->toContain('create_site_page')
        ->and($held)->toContain('update_site_page')
        ->and($held)->toContain('view_any_news_article')
        // 关键：不能发布、不能删除、不能回滚
        ->and($held)->not->toContain('publish_site_page')
        ->and($held)->not->toContain('rollback_site_page')
        ->and($held)->not->toContain('delete_site_page');
});

/**
 * 内容发布 = 内容编辑全部 + 发布 / 回滚 / 删除
 */
it('内容发布包含内容编辑的全部权限', function () {
    seedSiteRoles();

    $editor    = Role::where('name', '内容编辑')->firstOrFail()->permissions->pluck('name');
    $publisher = Role::where('name', '内容发布')->firstOrFail()->permissions->pluck('name');

    foreach ($editor as $permission) {
        expect($publisher)->toContain($permission);
    }

    expect($publisher)->toContain('publish_site_page')
        ->and($publisher)->toContain('rollback_site_page')
        ->and($publisher)->toContain('delete_site_page')
        // 站点级配置仍然没有
        ->and($publisher)->not->toContain('manage_site_settings')
        ->and($publisher)->not->toContain('manage_site_menu')
        ->and($publisher)->not->toContain('export_contact_message');
});

/**
 * 站点管理 = 内容发布全部 + 站点级配置 + 询盘
 */
it('站点管理包含内容发布的全部权限并加上站点级配置', function () {
    seedSiteRoles();

    $publisher = Role::where('name', '内容发布')->firstOrFail()->permissions->pluck('name');
    $manager   = Role::where('name', '站点管理')->firstOrFail()->permissions->pluck('name');

    foreach ($publisher as $permission) {
        expect($manager)->toContain($permission);
    }

    expect($manager)->toContain('manage_site_settings')
        ->and($manager)->toContain('manage_site_menu')
        ->and($manager)->toContain('manage_site_redirect')
        ->and($manager)->toContain('view_any_contact_message')
        ->and($manager)->toContain('export_contact_message');
});

/**
 * 幂等：重跑不产生重复角色，权限也不重复
 */
it('角色种子可重复执行', function () {
    seedSiteRoles();
    seedSiteRoles();

    expect(Role::where('name', '内容编辑')->count())->toBe(1);

    $role = Role::where('name', '内容编辑')->firstOrFail();

    expect($role->permissions->pluck('name')->duplicates())->toBeEmpty();
});

/**
 * 权限点缺失时不抛异常
 *
 * 下游可能只装了部分功能（比如没跑资讯模块的种子），
 * 给 syncPermissions 传不存在的权限名会抛 PermissionDoesNotExist 中断安装。
 */
it('权限点缺失时角色种子不报错', function () {
    // 只建一个权限点，其余全缺
    Permission::create(['name' => 'view_any_site_page', 'guard_name' => 'admin']);

    (new SiteRoleSeeder)->run();

    $role = Role::where('name', '内容编辑')->firstOrFail();

    expect($role->permissions->pluck('name')->all())->toBe(['view_any_site_page']);
});

/**
 * syncPermissions 语义：手工加的权限重跑后被刷掉
 *
 * 角色定义以代码为准，否则升级后各站权限各不相同没法支持。
 * 这条固化那个决定，避免日后有人"顺手"改成 givePermissionTo。
 */
it('重跑会刷掉手工加到角色上的权限', function () {
    seedSiteRoles();

    $role = Role::where('name', '内容编辑')->firstOrFail();
    $role->givePermissionTo('publish_site_page');

    expect($role->refresh()->permissions->pluck('name'))->toContain('publish_site_page');

    (new SiteRoleSeeder)->run();

    expect($role->refresh()->permissions->pluck('name'))->not->toContain('publish_site_page');
});
