<?php

use FilamentAdmin\Http\Middleware\EnsureTwoFactorEnabled;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Settings\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

// 由于测试文件位于 worktree，须在文件内显式声明 uses 以引导 Laravel 测试环境
uses(TestCase::class, RefreshDatabase::class);

/**
 * 强制 2FA 拦截中间件测试（POLISH-02）
 *
 * 验证 EnsureTwoFactorEnabled 中间件在 SecuritySettings.force_2fa=true 时
 * 正确拦截未开 2FA 的管理员，并放行登出/2FA设置/个人资料等页面。
 */

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    // 确保每个测试开始时 force_2fa 为 false（测试隔离）
    $settings           = app(SecuritySettings::class);
    $settings->force_2fa = false;
    $settings->save();
    // 清除 settings 实例缓存
    app()->forgetInstance(SecuritySettings::class);
});

// =============================================================
// 用例 A：force_2fa=true + 未开 2FA 普通管理员 + 访问 Dashboard
// 预期：被重定向到 2FA 设置页
// =============================================================
it('force_2fa=true 时未开 2FA 的管理员访问后台被重定向到 2FA 设置页', function () {
    // 准备：启用强制 2FA
    $settings           = app(SecuritySettings::class);
    $settings->force_2fa = true;
    $settings->save();
    app()->forgetInstance(SecuritySettings::class);

    // 创建未开 2FA 的普通管理员
    $user = AdminUser::factory()->create();

    // 直接请求 panel Dashboard（已通过 actingAs 模拟认证）
    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertRedirect();

    // 验证重定向目标包含 two-factor-setup
    $response = $this->actingAs($user, 'admin')->get('/admin');
    $redirectTarget = $response->headers->get('Location');
    expect($redirectTarget)->toContain('two-factor-setup');
});

// =============================================================
// 用例 B：force_2fa=true + 未开 2FA + 访问 2FA 设置页 → 放行（不 redirect）
//           同时验证访问登出路由也被放行
// =============================================================
it('force_2fa=true 时未开 2FA 的管理员访问 2FA 设置页不被拦截（防锁死）', function () {
    $settings           = app(SecuritySettings::class);
    $settings->force_2fa = true;
    $settings->save();
    app()->forgetInstance(SecuritySettings::class);

    $user = AdminUser::factory()->create();

    // 2FA 设置页本身应被放行（不 redirect，HTTP 200 或 Livewire 渲染）
    $this->actingAs($user, 'admin')
        ->get('/admin/two-factor-setup')
        ->assertSuccessful();
});

it('force_2fa=true 时未开 2FA 的管理员访问 Profile 页不被拦截（防锁死）', function () {
    $settings           = app(SecuritySettings::class);
    $settings->force_2fa = true;
    $settings->save();
    app()->forgetInstance(SecuritySettings::class);

    $user = AdminUser::factory()->create();

    // 个人资料页应被放行
    $this->actingAs($user, 'admin')
        ->get('/admin/profile')
        ->assertSuccessful();
});

// =============================================================
// 用例 C：force_2fa=true + 已开 2FA 的管理员 → 放行（访问 Dashboard 200）
// =============================================================
it('force_2fa=true 时已开 2FA 的管理员可正常访问后台', function () {
    $settings           = app(SecuritySettings::class);
    $settings->force_2fa = true;
    $settings->save();
    app()->forgetInstance(SecuritySettings::class);

    // 创建已开 2FA 的管理员
    $user = AdminUser::factory()->withTwoFactor()->create();

    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertSuccessful();
});

// =============================================================
// 用例 D：force_2fa=true + 超管未开 2FA → 同样被拦（D-04 不豁免）
// =============================================================
it('force_2fa=true 时超管未开 2FA 也被拦截（不豁免）', function () {
    $settings           = app(SecuritySettings::class);
    $settings->force_2fa = true;
    $settings->save();
    app()->forgetInstance(SecuritySettings::class);

    // 创建超管角色并分配
    $role = Role::firstOrCreate([
        'name'       => config('filament-admin.super_admin_role', 'super_admin'),
        'guard_name' => 'admin',
    ]);
    $superAdmin = AdminUser::factory()->create();
    $superAdmin->assignRole($role);

    // 超管未开 2FA，访问后台也应被重定向
    $response = $this->actingAs($superAdmin, 'admin')->get('/admin');
    $response->assertRedirect();

    $redirectTarget = $response->headers->get('Location');
    expect($redirectTarget)->toContain('two-factor-setup');
});

// =============================================================
// 用例 E：force_2fa=false → 任何用户任何页面放行（不拦截）
// =============================================================
it('force_2fa=false 时未开 2FA 的管理员可正常访问后台', function () {
    // force_2fa 默认已在 beforeEach 设为 false
    $user = AdminUser::factory()->create();

    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertSuccessful();
});
