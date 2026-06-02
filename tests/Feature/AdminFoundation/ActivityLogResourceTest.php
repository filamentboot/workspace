<?php

use App\Enums\AdminUserStatus;
use App\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Resources\Menus\Pages\MenuTree;
use App\Models\AdminUser;
use App\Models\Department;
use App\Models\Menu;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('操作日志服务记录操作人和变更内容', function () {
    $admin  = AdminUser::factory()->create();
    $target = AdminUser::factory()->create(['nickname' => '旧名称']);

    app(ActivityLogger::class)->log(
        causer: $admin,
        subject: $target,
        action: 'updated',
        before: ['nickname' => '旧名称'],
        after: ['nickname' => '新名称'],
    );

    $activity = Activity::query()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer->is($admin))->toBeTrue()
        ->and($activity->properties['before']['nickname'])->toBe('旧名称')
        ->and($activity->properties['after']['nickname'])->toBe('新名称');
});

it('操作日志清理命令删除过期记录', function () {
    Activity::query()->create([
        'log_name'     => 'admin',
        'description'  => '旧日志',
        'subject_type' => AdminUser::class,
        'subject_id'   => 1,
        'causer_type'  => AdminUser::class,
        'causer_id'    => 1,
        'event'        => 'deleted',
        'properties'   => [],
        'created_at'   => now()->subDays(200),
        'updated_at'   => now()->subDays(200),
    ]);

    Activity::query()->create([
        'log_name'     => 'admin',
        'description'  => '新日志',
        'subject_type' => AdminUser::class,
        'subject_id'   => 1,
        'causer_type'  => AdminUser::class,
        'causer_id'    => 1,
        'event'        => 'updated',
        'properties'   => [],
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $this->artisan('filament-admin:clean-activity-logs', ['--days' => 180])
        ->assertSuccessful();

    expect(Activity::count())->toBe(1);
});

it('编辑管理员角色和密码会写入操作日志', function () {
    $superRole = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $targetRole = Role::create([
        'name'       => '运营',
        'guard_name' => 'admin',
    ]);

    $actor = AdminUser::factory()->create();
    $actor->assignRole($superRole);

    $target = AdminUser::factory()->create([
        'password' => 'old-secret',
    ]);

    Livewire::actingAs($actor, 'admin')
        ->test(EditAdminUser::class, [
            'record' => $target->getRouteKey(),
        ])
        ->fillForm([
            'account'  => $target->account,
            'email'    => $target->email,
            'nickname' => $target->nickname,
            'status'   => AdminUserStatus::Active->value,
            'roles'    => [$targetRole->id],
            'password' => 'new-secret',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $events = Activity::query()
        ->orderBy('id')
        ->pluck('event')
        ->all();

    $roleActivity     = Activity::query()->where('event', 'roles_updated')->first();
    $passwordActivity = Activity::query()->where('event', 'password_reset')->first();

    expect(Hash::check('new-secret', $target->fresh()->password))->toBeTrue()
        ->and($events)->toContain('roles_updated', 'password_reset')
        ->and($roleActivity)->not->toBeNull()
        ->and($roleActivity->causer?->is($actor))->toBeTrue()
        ->and($roleActivity->properties['before']['roles'])->toBe([])
        ->and($roleActivity->properties['after']['roles'])->toBe(['运营'])
        ->and($passwordActivity)->not->toBeNull()
        ->and($passwordActivity->causer?->is($actor))->toBeTrue()
        ->and($passwordActivity->properties['after']['password_changed'])->toBeTrue();
});

it('部门创建编辑删除恢复会写入操作日志', function () {
    $superRole = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $actor = AdminUser::factory()->create();
    $actor->assignRole($superRole);

    $this->actingAs($actor, 'admin');

    $department = Department::factory()->create([
        'name' => '客服部',
        'code' => 'SERVICE',
    ]);

    $department->update(['name' => '客户成功部']);
    $department->delete();
    $department->restore();

    $events = Activity::query()
        ->orderBy('id')
        ->pluck('event')
        ->all();

    expect($events)->toBe([
        'created',
        'updated',
        'deleted',
        'restored',
    ]);
});

it('菜单拖拽排序会写入操作日志', function () {
    $superRole = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $actor = AdminUser::factory()->create();
    $actor->assignRole($superRole);

    $first = Menu::factory()->create([
        'title' => '首页',
        'sort'  => 1,
    ]);
    $second = Menu::factory()->create([
        'title' => '系统设置',
        'sort'  => 2,
    ]);

    Livewire::actingAs($actor, 'admin')
        ->test(MenuTree::class)
        ->call('updateTree', [['id' => $second->id], ['id' => $first->id]]);

    $activity = Activity::query()->where('event', 'reordered')->first();

    expect(Menu::query()->orderBy('sort')->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($activity)->not->toBeNull()
        ->and($activity->causer?->is($actor))->toBeTrue()
        ->and($activity->properties['before']['order'][0]['id'])->toBe($first->id)
        ->and($activity->properties['after']['order'][0]['id'])->toBe($second->id);
});
