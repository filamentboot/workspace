<?php

use Filament\Facades\Filament;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages\CreateSitePage;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages\ListSitePages;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages\ViewContactMessage;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Models\ContactMessageNote;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * 官网插件后台页面渲染与交互测试
 *
 * 官网插件的资源路由由 vendor/filament/filament/routes/web.php 在应用 boot 时
 * 一次性注册，而插件是否注册取决于 plugins.is_enabled——测试库在 boot 之后才有数据，
 * 于是资源路由缺失，渲染页面会因拿不到 resources.*.index 而失败。
 *
 * 解决办法与既有的前台路由测试同源：手工把插件注册进面板，再重跑一次 Filament 的
 * 路由文件并刷新名称查找表。这样后台页面就能在测试里真实渲染，
 * 不必把「页面会不会 500」留给手工点击。
 *
 * @group site
 */
beforeEach(function () {
    $panel = Filament::getPanel('admin');
    $panel->plugin(SitePlugin::make());

    // 重跑 Filament 路由文件，让新注册的资源获得路由
    require base_path('vendor/filament/filament/routes/web.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    Filament::setCurrentPanel($panel);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view_any_contact_message',
        'view_contact_message',
        'update_contact_message',
        'export_contact_message',
        'view_any_site_page',
        'create_site_page',
        'update_site_page',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
    }
});

/**
 * 创建一个拥有指定权限的管理员并登录
 *
 * @param  list<string>  $permissions
 */
function loginWithPermissions(array $permissions): AdminUser
{
    $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'admin']);

    foreach ($permissions as $permission) {
        $role->givePermissionTo($permission);
    }

    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user, 'admin');

    return $user;
}

/**
 * 询盘列表页渲染，来源列显示中文标签
 */
it('询盘列表页渲染并显示来源中文标签', function () {
    loginWithPermissions(['view_any_contact_message']);

    ContactMessage::create([
        'name'       => '张三',
        'phone'      => '13800138000',
        'message'    => '咨询全屋智能',
        'status'     => ContactMessageStatus::UNREAD,
        'source'     => 'product-detail',
        'utm_source' => 'wechat',
    ]);

    Livewire::test(ListContactMessages::class)
        ->assertOk()
        ->assertSee('张三')
        ->assertSee('产品详情页');
});

/**
 * 未登记的来源 key 回落显示原始值，不至于空白
 */
it('未登记来源回落显示原始 key', function () {
    loginWithPermissions(['view_any_contact_message']);

    ContactMessage::create([
        'name'    => '李四',
        'phone'   => '13900139000',
        'message' => '',
        'status'  => ContactMessageStatus::UNREAD,
        'source'  => 'landing-spring',
    ]);

    Livewire::test(ListContactMessages::class)
        ->assertOk()
        ->assertSee('landing-spring');
});

/**
 * 无导出权限时列表页不显示导出按钮
 */
it('无导出权限时列表页无导出按钮', function () {
    loginWithPermissions(['view_any_contact_message']);

    Livewire::test(ListContactMessages::class)->assertActionHidden('export');
});

/**
 * 有导出权限时列表页显示导出按钮
 */
it('有导出权限时列表页显示导出按钮', function () {
    loginWithPermissions(['view_any_contact_message', 'export_contact_message']);

    Livewire::test(ListContactMessages::class)->assertActionVisible('export');
});

/**
 * 询盘详情页渲染来源与渠道分区
 */
it('询盘详情页渲染来源与渠道信息', function () {
    loginWithPermissions(['view_any_contact_message', 'view_contact_message']);

    $message = ContactMessage::create([
        'name'         => '王五',
        'phone'        => '13700137000',
        'message'      => '想了解价格',
        'status'       => ContactMessageStatus::UNREAD,
        'source'       => 'nav-desktop',
        'landing_url'  => 'http://localhost/solutions?utm_source=wechat',
        'referer'      => 'https://www.baidu.com/',
        'utm_source'   => 'wechat',
        'utm_campaign' => 'summer2026',
    ]);

    Livewire::test(ViewContactMessage::class, ['record' => $message->getKey()])
        ->assertOk()
        ->assertSee('来源与渠道')
        ->assertSee('导航栏')
        ->assertSee('wechat')
        ->assertSee('summer2026')
        ->assertSee('跟进记录');
});

/**
 * 详情页添加跟进备注写入时间线并记录操作人
 */
it('详情页添加跟进备注', function () {
    $user = loginWithPermissions(['view_any_contact_message', 'view_contact_message', 'update_contact_message']);

    $message = ContactMessage::create([
        'name'    => '赵六',
        'phone'   => '13600136000',
        'message' => '',
        'status'  => ContactMessageStatus::UNREAD,
    ]);

    Livewire::test(ViewContactMessage::class, ['record' => $message->getKey()])
        ->callAction('addNote', ['body' => '已电话联系，客户希望下周三上门量房。']);

    $note = ContactMessageNote::query()->where('message_id', $message->getKey())->first();

    expect($note)->not->toBeNull()
        ->and($note->body)->toBe('已电话联系，客户希望下周三上门量房。')
        ->and($note->admin_user_id)->toBe($user->getKey());
});

/**
 * 空内容的跟进备注被拒
 */
it('空跟进内容被拒绝', function () {
    loginWithPermissions(['view_any_contact_message', 'view_contact_message', 'update_contact_message']);

    $message = ContactMessage::create([
        'name'    => '孙七',
        'phone'   => '13500135000',
        'message' => '',
        'status'  => ContactMessageStatus::UNREAD,
    ]);

    Livewire::test(ViewContactMessage::class, ['record' => $message->getKey()])
        ->callAction('addNote', ['body' => ''])
        ->assertHasActionErrors(['body' => 'required']);

    expect(ContactMessageNote::count())->toBe(0);
});

/**
 * 无 update 权限时详情页不显示添加跟进备注按钮
 */
it('无更新权限时详情页无跟进备注按钮', function () {
    loginWithPermissions(['view_any_contact_message', 'view_contact_message']);

    $message = ContactMessage::create([
        'name'    => '周八',
        'phone'   => '13400134000',
        'message' => '',
        'status'  => ContactMessageStatus::UNREAD,
    ]);

    Livewire::test(ViewContactMessage::class, ['record' => $message->getKey()])
        ->assertActionHidden('addNote');
});

/**
 * 页面列表渲染状态 badge
 */
it('页面列表渲染状态标签', function () {
    loginWithPermissions(['view_any_site_page']);

    SitePage::factory()->create(['title_zh' => '已发布页面', 'slug' => 'published-one']);
    SitePage::factory()->draft()->create(['title_zh' => '草稿页面', 'slug' => 'draft-one']);

    Livewire::test(ListSitePages::class)
        ->assertOk()
        ->assertSee('已发布页面')
        ->assertSee('草稿页面')
        ->assertSee('已发布')
        ->assertSee('草稿');
});

/**
 * 页面表单能保存状态与发布时间
 *
 * scopePublished() 改读 status 之后，表单若还只有 is_published 开关，
 * 就会出现「后台点了发布、前台看不到」——这条锁住那个回归。
 * 旧列本身已在 #27 随目录重构一起删掉，这里顺带断言它真的不在表上了。
 */
it('页面表单保存状态与发布时间', function () {
    loginWithPermissions(['view_any_site_page', 'create_site_page']);

    $publishAt = now()->addDays(2)->startOfMinute();

    Livewire::test(CreateSitePage::class)
        ->fillForm([
            'slug'         => 'form-created-page',
            'title_zh'     => '表单创建的页面',
            'status'       => 'scheduled',
            'published_at' => $publishAt,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = SitePage::where('slug', 'form-created-page')->firstOrFail();

    expect($page->status->value)->toBe('scheduled')
        ->and($page->published_at->format('Y-m-d H:i'))->toBe($publishAt->format('Y-m-d H:i'))
        ->and(Schema::hasColumn('site_pages', 'is_published'))->toBeFalse();
});

/**
 * 定时发布状态下未填发布时间被拒
 */
it('定时发布未填发布时间被拒', function () {
    loginWithPermissions(['view_any_site_page', 'create_site_page']);

    Livewire::test(CreateSitePage::class)
        ->fillForm([
            'slug'         => 'no-publish-time',
            'title_zh'     => '缺发布时间',
            'status'       => 'scheduled',
            'published_at' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['published_at']);

    expect(SitePage::where('slug', 'no-publish-time')->exists())->toBeFalse();
});
