<?php

use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * 官网设置页保存行为测试
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage
 */
beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);
    actingAs($user, 'admin');
});

/**
 * 可选文本字段留空时仍能保存
 *
 * Filament 把空文本框归一为 null，而 SiteSettings 的多数属性声明为非空 string，
 * 两者相遇时 Spatie 的 Settings::fill() 抛
 * 「Cannot assign null to property ... of type string」。
 * 也就是说在此修复前，只要电话/地址/备案号等任一可选字段留空，保存设置页就 500。
 */
it('可选字段留空时保存不报错', function () {
    Livewire::test(SiteSettingsPage::class)
        ->fillForm([
            'company_name_zh'            => '晴空妙享',
            'phone'                      => null,
            'address_zh'                 => null,
            'icp_number'                 => null,
            'privacy_url'                => null,
            'seo_default_title_zh'       => null,
            'seo_default_description_zh' => null,
            'notify_emails'              => null,
            'baidu_tongji_id'            => null,
            'ga_measurement_id'          => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(SiteSettings::class)->refresh();

    expect($settings->company_name_zh)->toBe('晴空妙享')
        ->and($settings->phone)->toBe('')
        ->and($settings->icp_number)->toBe('')
        ->and($settings->notify_emails)->toBe('');
});

/**
 * 可为 null 的媒体字段留空时保持 null，不被强转空串
 */
it('可空媒体字段留空时保持 null', function () {
    Livewire::test(SiteSettingsPage::class)
        ->fillForm([
            'company_name_zh'  => '晴空妙享',
            'logo'             => null,
            'og_default_image' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(SiteSettings::class)->refresh();

    expect($settings->logo)->toBeNull()
        ->and($settings->og_default_image)->toBeNull();
});
