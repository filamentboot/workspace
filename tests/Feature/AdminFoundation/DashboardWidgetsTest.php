<?php

use FilamentAdmin\Filament\Widgets\QuickGuideWidget;
use FilamentAdmin\Filament\Widgets\SystemStatsWidget;
use FilamentAdmin\Filament\Widgets\WelcomeWidget;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\LoginLog;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('WelcomeWidget 显示当前管理员昵称', function () {
    $admin = AdminUser::factory()->create([
        'nickname' => '张三',
        'account'  => 'zhangsan',
    ]);

    $component = Livewire::actingAs($admin, 'admin')
        ->test(WelcomeWidget::class);

    expect($component->instance()->getAdminNickname())->toBe('张三');
});

it('SystemStatsWidget 返回正确的管理员总数', function () {
    AdminUser::factory()->count(3)->create();

    $component = Livewire::actingAs(AdminUser::factory()->create(), 'admin')
        ->test(SystemStatsWidget::class);

    $stats  = $component->instance()->getStats();
    $counts = collect($stats)->map(fn ($s) => $s->getValue())->toArray();

    // 包含 4 条（3 + 1 个登录操作者），管理员总数 stat 的值 >= 1
    expect($counts)->toContain(AdminUser::query()->count());
});

it('SystemStatsWidget 返回正确的角色总数', function () {
    Role::create(['name' => '运营', 'guard_name' => 'admin']);
    Role::create(['name' => '客服', 'guard_name' => 'admin']);

    $component = Livewire::actingAs(AdminUser::factory()->create(), 'admin')
        ->test(SystemStatsWidget::class);

    $stats  = $component->instance()->getStats();
    $values = collect($stats)->map(fn ($s) => (int) $s->getValue())->toArray();

    expect($values)->toContain(Role::query()->where('guard_name', 'admin')->count());
});

it('SystemStatsWidget 统计今日成功登录次数', function () {
    $admin = AdminUser::factory()->create();

    LoginLog::factory()->count(2)->create([
        'admin_user_id' => $admin->id,
        'status'        => 'success',
        'created_at'    => now(),
    ]);
    LoginLog::factory()->create([
        'admin_user_id' => $admin->id,
        'status'        => 'failed',
        'created_at'    => now(),
    ]);

    $component = Livewire::actingAs($admin, 'admin')
        ->test(SystemStatsWidget::class);

    $stats  = $component->instance()->getStats();
    $values = collect($stats)->map(fn ($s) => (int) $s->getValue())->toArray();

    expect($values)->toContain(2);
});

it('QuickGuideWidget 对未完成 onboarding 的管理员可见', function () {
    $admin = AdminUser::factory()->create(['onboarding_completed' => false]);

    Livewire::actingAs($admin, 'admin');

    expect(QuickGuideWidget::canView())->toBeTrue();
});

it('QuickGuideWidget 对已完成 onboarding 的管理员不可见', function () {
    $admin = AdminUser::factory()->create(['onboarding_completed' => true]);

    Livewire::actingAs($admin, 'admin');

    expect(QuickGuideWidget::canView())->toBeFalse();
});

it('QuickGuideWidget dismiss 后设置 onboarding_completed 为 true', function () {
    $admin = AdminUser::factory()->create(['onboarding_completed' => false]);

    Livewire::actingAs($admin, 'admin')
        ->test(QuickGuideWidget::class)
        ->call('dismiss');

    expect($admin->fresh()->onboarding_completed)->toBeTrue();
});
