<?php

use FilamentAdmin\Filament\Resources\LoginLogs\LoginLogResource;
use FilamentAdmin\Filament\Resources\LoginLogs\Pages\ListLoginLogs;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\LoginLog;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('超级管理员可以查看登录日志列表', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);
    LoginLog::factory()->count(2)->create();

    $this->actingAs($admin, 'admin')
        ->get(LoginLogResource::getUrl('index'))
        ->assertSuccessful();
});

it('登录日志清理命令删除过期日志', function () {
    LoginLog::factory()->create([
        'created_at' => now()->subDays(120),
    ]);
    LoginLog::factory()->create([
        'created_at' => now(),
    ]);

    $this->artisan('filament-admin:clean-login-logs', ['--days' => 90])
        ->assertSuccessful();

    expect(LoginLog::count())->toBe(1);
});

it('登录日志支持按管理员结果IP和时间范围筛选', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $viewer = AdminUser::factory()->create();
    $viewer->assignRole($role);

    $alpha = AdminUser::factory()->create(['account' => 'alpha']);
    $beta  = AdminUser::factory()->create(['account' => 'beta']);

    $alphaLog = LoginLog::factory()->create([
        'admin_user_id' => $alpha->id,
        'username'      => $alpha->account,
        'status'        => 'success',
        'ip_address'    => '10.0.0.1',
        'created_at'    => now()->subDays(2),
    ]);
    $betaLog = LoginLog::factory()->create([
        'admin_user_id' => $beta->id,
        'username'      => $beta->account,
        'status'        => 'failed',
        'ip_address'    => '10.0.0.2',
        'created_at'    => now()->subDay(),
    ]);
    $latestAlphaLog = LoginLog::factory()->create([
        'admin_user_id' => $alpha->id,
        'username'      => $alpha->account,
        'status'        => 'success',
        'ip_address'    => '192.168.1.3',
        'created_at'    => now(),
    ]);

    $component = Livewire::actingAs($viewer, 'admin')
        ->test(ListLoginLogs::class);

    $component
        ->filterTable('admin_user_id', $alpha->id)
        ->assertCanSeeTableRecords([$alphaLog, $latestAlphaLog])
        ->assertCanNotSeeTableRecords([$betaLog]);

    $component
        ->resetTableFilters()
        ->filterTable('status', 'failed')
        ->assertCanSeeTableRecords([$betaLog])
        ->assertCanNotSeeTableRecords([$alphaLog, $latestAlphaLog]);

    $component
        ->resetTableFilters()
        ->filterTable('ip_address', ['ip' => '10.0.0.2'])
        ->assertCanSeeTableRecords([$betaLog])
        ->assertCanNotSeeTableRecords([$alphaLog, $latestAlphaLog]);

    $component
        ->resetTableFilters()
        ->filterTable('created_at', [
            'created_from'  => now()->subHours(36)->toDateTimeString(),
            'created_until' => now()->subHours(12)->toDateTimeString(),
        ])
        ->assertCanSeeTableRecords([$betaLog])
        ->assertCanNotSeeTableRecords([$alphaLog, $latestAlphaLog]);
});
