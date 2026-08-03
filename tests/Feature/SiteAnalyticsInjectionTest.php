<?php

use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * 统计代码注入测试（SiteAnalyticsInjectionTest，A3）
 *
 * 覆盖场景：
 * - 结构化 ID 渲染为固定模板代码，格式非法时不输出
 * - 自定义代码块原样输出（有意开放的例外）
 * - 无 manage_site_settings 权限的用户保存设置后已有脚本不被抹空
 * - 自定义代码变更写入操作日志
 *
 * @group site
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.themes'        => ['decoration' => '科技装修（深色）'],
        'filamentboot-site.default_theme' => 'decoration',
    ]);

    $provider = new SiteServiceProvider(app());

    foreach (['registerLivewireComponents', 'registerThemeViews', 'shareSiteSettings', 'registerFrontend'] as $method) {
        $reflection = new ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'manage_site_settings', 'guard_name' => 'admin']);
});

/**
 * 填了百度统计 ID 后前台输出百度统计代码
 */
it('配置百度统计 ID 后前台输出统计代码', function () {
    $settings                  = app(SiteSettings::class);
    $settings->baidu_tongji_id = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
    $settings->save();

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('hm.baidu.com/hm.js?a1b2c3d4e5f60718293a4b5c6d7e8f90');
});

/**
 * 统计 ID 格式非法时不输出任何脚本
 *
 * ID 被拼进 script src，格式校验是防止把设置项变成注入点的第一道闸。
 */
it('统计 ID 格式非法时不输出统计代码', function () {
    $settings                    = app(SiteSettings::class);
    $settings->baidu_tongji_id   = '"></script><script>alert(1)</script>';
    $settings->ga_measurement_id = 'not-a-ga-id';
    $settings->save();

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('hm.baidu.com')
        ->and($html)->not->toContain('alert(1)')
        ->and($html)->not->toContain('googletagmanager.com');
});

/**
 * GA4 衡量 ID 渲染 gtag 代码
 */
it('配置 GA4 ID 后前台输出 gtag 代码', function () {
    $settings                    = app(SiteSettings::class);
    $settings->ga_measurement_id = 'G-ABCD123456';
    $settings->save();

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('googletagmanager.com/gtag/js?id=G-ABCD123456');
});

/**
 * 自定义代码块原样输出到对应位置
 */
it('自定义代码块原样输出到 head 与 body', function () {
    $settings                   = app(SiteSettings::class);
    $settings->head_scripts     = '<meta name="custom-head-marker" content="1">';
    $settings->body_end_scripts = '<script>window.__customBodyMarker = 1;</script>';
    $settings->save();

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<meta name="custom-head-marker" content="1">')
        ->and($html)->toContain('window.__customBodyMarker = 1;');
});

/**
 * 表单转化事件监听器已挂载
 */
it('前台挂载表单转化事件监听器', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain("addEventListener('site-contact-submitted'");
});

/**
 * 无 manage_site_settings 权限的用户保存设置后，已有脚本不被抹空
 *
 * 字段用 disabled() 门住，而 Filament 不 dehydrate disabled 字段、
 * Spatie 的 Settings::fill() 只覆盖传入的键——两者叠加才使原值得以保留。
 * 这是推断出来的行为，必须用测试锁住，否则哪天任一侧实现变化就会静默清空线上脚本。
 */
it('无权限用户保存设置不会抹空已有自定义脚本', function () {
    $settings               = app(SiteSettings::class);
    $settings->head_scripts = '<meta name="preexisting" content="1">';
    $settings->save();

    // 无任何角色的普通管理员
    $user = AdminUser::factory()->create();
    $this->actingAs($user, 'admin');

    Livewire::test(SiteSettingsPage::class)
        ->fillForm(['company_name_zh' => '改了公司名'])
        ->call('save');

    $fresh = app(SiteSettings::class)->refresh();

    expect($fresh->head_scripts)->toBe('<meta name="preexisting" content="1">')
        ->and($fresh->company_name_zh)->toBe('改了公司名');
});

/**
 * 有权限用户修改自定义代码后写入操作日志
 */
it('自定义代码变更写入操作日志', function () {
    $role = Role::create(['name' => 'site-manager', 'guard_name' => 'admin']);
    $role->givePermissionTo('manage_site_settings');

    $user = AdminUser::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user, 'admin');

    $before = Activity::count();

    Livewire::test(SiteSettingsPage::class)
        ->fillForm(['head_scripts' => '<script>console.log("new")</script>'])
        ->call('save');

    expect(Activity::count())->toBeGreaterThan($before);

    $log = Activity::latest('id')->first();

    expect($log->event)->toBe('update')
        ->and($log->properties['model'])->toBe('SiteSettings')
        ->and($log->properties['changed'])->toHaveKey('head_scripts');
});
