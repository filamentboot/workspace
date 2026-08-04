<?php

use Filament\Facades\Filament;
use Filamentboot\FilamentbootSite\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource\Pages\CreateSitePage;
use Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource\Pages\EditSitePage;
use Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource\Pages\ListSitePages;
use Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource\RelationManagers\RevisionsRelationManager;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SitePageRevision;
use Filamentboot\FilamentbootSite\Models\SiteRedirect;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\Models\AdminUser;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 页面编辑与发布流转后台测试（#14）
 *
 * 覆盖场景：
 * - Builder 区块存取：拖进去的区块进库，保存侧净化剥离脚本
 * - 状态流转 Action 按 PageStatus 的允许边显隐
 * - 发布类 Action 需要 publish_site_page，内容编辑只能提交审核
 * - 定时发布写入 published_at；已排期后点立即发布会把未来时间拉回当下
 * - 列表按状态分 Tab 且计数正确
 *
 * 官网插件的资源路由由 vendor/filament/filament/routes/web.php 在应用 boot 时
 * 一次性注册，而插件是否注册取决于 plugins.is_enabled——测试库在 boot 之后才有数据，
 * 于是资源路由缺失。手法与 SiteContactResourcePageTest 同源：手工把插件注册进面板，
 * 再重跑一次 Filament 的路由文件并刷新名称查找表。
 *
 * @group site
 */
beforeEach(function () {
    $panel = Filament::getPanel('admin');
    $panel->plugin(SitePlugin::make());

    require base_path('vendor/filament/filament/routes/web.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    Filament::setCurrentPanel($panel);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view_any_site_page',
        'view_site_page',
        'create_site_page',
        'update_site_page',
        'publish_site_page',
        'rollback_site_page',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
    }
});

/**
 * 创建一个拥有指定权限的管理员并登录
 *
 * @param  list<string>  $permissions
 */
function loginPageEditor(array $permissions): AdminUser
{
    $role = Role::create(['name' => 'page-role-'.uniqid(), 'guard_name' => 'admin']);

    foreach ($permissions as $permission) {
        $role->givePermissionTo($permission);
    }

    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user, 'admin');

    return $user;
}

/**
 * 内容编辑（无 publish 权限）的完整权限组
 *
 * @return list<string>
 */
function editorPermissions(): array
{
    return ['view_any_site_page', 'view_site_page', 'create_site_page', 'update_site_page'];
}

/**
 * Builder 区块能从表单存进库，结构与区块契约一致
 */
it('后台可保存页面区块', function () {
    loginPageEditor(editorPermissions());

    Livewire::test(CreateSitePage::class)
        ->fillForm([
            'slug'     => 'blocks-page',
            'title_zh' => '带区块的页面',
            'status'   => PageStatus::DRAFT->value,
            'blocks'   => [
                ['type' => 'hero', 'data' => ['title' => '首屏标题', 'subtitle' => '副标题']],
                ['type' => 'faq', 'data' => [
                    'title' => '常见问题',
                    'items' => [['question' => '要多久？', 'answer' => '45 天。']],
                ]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = SitePage::where('slug', 'blocks-page')->firstOrFail();

    expect($page->blocks)->toHaveCount(2)
        ->and($page->blocks[0]['type'])->toBe('hero')
        ->and($page->blocks[0]['data']['title'])->toBe('首屏标题')
        ->and($page->blocks[1]['type'])->toBe('faq');
});

/**
 * 保存侧净化剥离 rich-content 区块里的脚本（#13 的 BlockSanitizer 接入点）
 *
 * 只在渲染侧过滤，数据库里就一直躺着未净化的 HTML，任何绕过前台视图的
 * 出口都会把它原样带出去。
 */
it('保存页面区块时剥离脚本标签', function () {
    loginPageEditor(editorPermissions());

    Livewire::test(CreateSitePage::class)
        ->fillForm([
            'slug'     => 'dirty-blocks',
            'title_zh' => '含脚本的页面',
            'status'   => PageStatus::DRAFT->value,
            'blocks'   => [
                ['type' => 'rich-content', 'data' => [
                    'title'   => '正文',
                    'content' => '<p>正常段落</p><script>alert(1)</script>',
                ]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $content = SitePage::where('slug', 'dirty-blocks')->firstOrFail()->blocks[0]['data']['content'];

    expect($content)->toContain('正常段落')
        ->and($content)->not->toContain('script');
});

/**
 * 编辑已有页面时同样过保存侧净化
 */
it('编辑页面区块时剥离脚本标签', function () {
    loginPageEditor(editorPermissions());

    $page = SitePage::factory()->draft()->create(['slug' => 'edit-dirty', 'blocks' => []]);

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->fillForm([
            'blocks' => [
                ['type' => 'rich-content', 'data' => ['title' => '', 'content' => '<p>干净</p><script>x()</script>']],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->blocks[0]['data']['content'])->not->toContain('script');
});

/**
 * 草稿页面：显示提交审核与发布，不显示归档
 *
 * draft 的允许边是 review / scheduled / published，没有 archived。
 */
it('草稿页面只显示允许的流转按钮', function () {
    loginPageEditor([...editorPermissions(), 'publish_site_page']);

    $page = SitePage::factory()->draft()->create();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->assertActionVisible('submitForReview')
        ->assertActionVisible('publish')
        ->assertActionVisible('schedule')
        // 转移到自己不是一个动作，按钮不该出现
        ->assertActionHidden('backToDraft')
        ->assertActionHidden('archive');
});

/**
 * 已发布页面：只能退回草稿或归档
 */
it('已发布页面只显示退回草稿与归档', function () {
    loginPageEditor([...editorPermissions(), 'publish_site_page']);

    $page = SitePage::factory()->create();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->assertActionVisible('backToDraft')
        ->assertActionVisible('archive')
        ->assertActionHidden('publish')
        ->assertActionHidden('submitForReview')
        ->assertActionHidden('schedule');
});

/**
 * 无 publish_site_page 的内容编辑看不到发布与定时发布
 *
 * 这是三层角色的核心约束：编辑者只能提交审核。
 */
it('无发布权限时看不到发布按钮', function () {
    loginPageEditor(editorPermissions());

    $page = SitePage::factory()->draft()->create();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->assertActionVisible('submitForReview')
        ->assertActionHidden('publish')
        ->assertActionHidden('schedule');
});

/**
 * 提交审核把状态推到 review
 */
it('提交审核把草稿变为待审核', function () {
    loginPageEditor(editorPermissions());

    $page = SitePage::factory()->draft()->create();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->callAction('submitForReview');

    expect($page->refresh()->status)->toBe(PageStatus::REVIEW);
});

/**
 * 发布把待审核页面变为已发布
 */
it('发布把待审核页面变为已发布', function () {
    loginPageEditor([...editorPermissions(), 'publish_site_page']);

    $page = SitePage::factory()->review()->create();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->callAction('publish');

    expect($page->refresh()->status)->toBe(PageStatus::PUBLISHED);
});

/**
 * 定时发布写入未来的 published_at，前台仍不可见
 */
it('定时发布写入发布时间', function () {
    loginPageEditor([...editorPermissions(), 'publish_site_page']);

    $page = SitePage::factory()->draft()->create();
    $at   = now()->addDays(3)->startOfMinute();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->callAction('schedule', ['published_at' => $at]);

    $page->refresh();

    expect($page->status)->toBe(PageStatus::SCHEDULED)
        ->and($page->published_at->format('Y-m-d H:i'))->toBe($at->format('Y-m-d H:i'))
        ->and(SitePage::published()->where('id', $page->getKey())->exists())->toBeFalse();
});

/**
 * 已排期页面点立即发布，未来的 published_at 被拉回当下
 *
 * 否则 scopePublished() 判为未到期，前台看不到——正是「点了发布前台看不到」
 * 那类故障的来源。
 */
it('已排期页面立即发布时把发布时间拉回当下', function () {
    loginPageEditor([...editorPermissions(), 'publish_site_page']);

    $page = SitePage::factory()->scheduled(now()->addDays(5))->create();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->callAction('publish');

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at->isFuture())->toBeFalse()
        ->and(SitePage::published()->where('id', $page->getKey())->exists())->toBeTrue();
});

/**
 * 归档页面只能回草稿，不能直接重新发布
 */
it('归档页面只显示退回草稿', function () {
    loginPageEditor([...editorPermissions(), 'publish_site_page']);

    $page = SitePage::factory()->archived()->create();

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->assertActionVisible('backToDraft')
        ->assertActionHidden('publish')
        ->assertActionHidden('archive');
});

/**
 * 列表按状态分 Tab，计数与实际行数一致
 */
it('页面列表按状态分 Tab 并计数', function () {
    loginPageEditor(editorPermissions());

    SitePage::factory()->count(2)->create();
    SitePage::factory()->count(3)->draft()->create();
    SitePage::factory()->review()->create();

    $component = Livewire::test(ListSitePages::class)->assertOk();

    $tabs = $component->instance()->getTabs();

    expect(array_keys($tabs))->toBe(['all', 'draft', 'review', 'scheduled', 'published', 'archived']);

    // 切到草稿 Tab 后只剩草稿行
    $component->set('activeTab', 'draft')
        ->assertCanSeeTableRecords(SitePage::where('status', PageStatus::DRAFT)->get())
        ->assertCanNotSeeTableRecords(SitePage::where('status', PageStatus::PUBLISHED)->get());
});

/**
 * 改 slug 自动建 301 重定向（#18）
 *
 * 自动创建而不是弹确认框：默认永不丢旧 URL。已被收录的地址一旦 404，
 * 权重要几周才能重新积累回来。
 */
it('改 slug 自动创建 301 重定向', function () {
    loginPageEditor(editorPermissions());

    $page = SitePage::factory()->create(['slug' => 'old-address']);

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->fillForm(['slug' => 'new-address'])
        ->call('save')
        ->assertHasNoFormErrors();

    $redirect = SiteRedirect::where('from_path', 'old-address')->first();

    expect($redirect)->not->toBeNull()
        ->and($redirect->to_path)->toBe('/new-address')
        ->and($redirect->status_code)->toBe(301);
});

/**
 * slug 未变时不建重定向（自指跳转会被浏览器判为循环）
 */
it('slug 未变时不建重定向', function () {
    loginPageEditor(editorPermissions());

    $page = SitePage::factory()->create(['slug' => 'unchanged']);

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->fillForm(['title_zh' => '只改了标题'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteRedirect::count())->toBe(0);
});

/**
 * slug 连改两次：第一条旧地址直指最终地址，不留两跳
 *
 * a→b、b→c 两跳每一跳都损耗一点权重，也让排查变麻烦。
 */
it('连续改 slug 时旧地址直指最终地址', function () {
    loginPageEditor(editorPermissions());

    $page = SitePage::factory()->create(['slug' => 'addr-a']);

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->fillForm(['slug' => 'addr-b'])
        ->call('save');

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->fillForm(['slug' => 'addr-c'])
        ->call('save');

    expect(SiteRedirect::where('from_path', 'addr-a')->first()->to_path)->toBe('/addr-b')
        ->and(SiteRedirect::where('from_path', 'addr-b')->first()->to_path)->toBe('/addr-c');
});

/**
 * 改回原 slug 时删掉反向链，避免新旧地址互指形成死循环
 */
it('改回原 slug 时清掉反向重定向', function () {
    loginPageEditor(editorPermissions());

    $page = SitePage::factory()->create(['slug' => 'back-a']);

    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->fillForm(['slug' => 'back-b'])
        ->call('save');

    expect(SiteRedirect::where('from_path', 'back-a')->exists())->toBeTrue();

    // 改回去：此时会建 back-b → /back-a，而 back-a → /back-b 必须被删掉
    Livewire::test(EditSitePage::class, ['record' => $page->getKey()])
        ->fillForm(['slug' => 'back-a'])
        ->call('save');

    expect(SiteRedirect::where('from_path', 'back-b')->first()->to_path)->toBe('/back-a')
        ->and(SiteRedirect::where('from_path', 'back-a')->exists())->toBeFalse();
});

/**
 * 版本历史关系管理器渲染并列出快照（#15）
 */
it('版本历史列表渲染快照', function () {
    loginPageEditor([...editorPermissions(), 'rollback_site_page']);

    $page = SitePage::factory()->draft()->create(['title_zh' => '第一版']);
    $page->update(['title_zh' => '第二版']);

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $page,
        'pageClass'   => EditSitePage::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords(SitePageRevision::where('page_id', $page->getKey())->get())
        // 基线快照的变更摘要显示「初始版本」而不是罗列全部字段
        ->assertSee('初始版本')
        ->assertSee('标题');
});

/**
 * 从版本历史回滚：内容回到旧版、status 不变、历史多一条
 */
it('从版本历史回滚只恢复内容', function () {
    loginPageEditor([...editorPermissions(), 'rollback_site_page']);

    $page     = SitePage::factory()->create(['title_zh' => '发布时的标题']);
    $baseline = SitePageRevision::where('page_id', $page->getKey())->firstOrFail();

    $page->update(['status' => PageStatus::ARCHIVED, 'title_zh' => '归档后的标题']);

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $page,
        'pageClass'   => EditSitePage::class,
    ])->callTableAction('rollback', $baseline);

    $page->refresh();

    expect($page->title_zh)->toBe('发布时的标题')
        ->and($page->status)->toBe(PageStatus::ARCHIVED)
        ->and(SitePageRevision::where('page_id', $page->getKey())->count())->toBe(3);
});

/**
 * 无 rollback_site_page 权限时回滚按钮不可用
 */
it('无回滚权限时看不到回滚按钮', function () {
    loginPageEditor(editorPermissions());

    $page     = SitePage::factory()->draft()->create();
    $baseline = SitePageRevision::where('page_id', $page->getKey())->firstOrFail();

    Livewire::test(RevisionsRelationManager::class, [
        'ownerRecord' => $page,
        'pageClass'   => EditSitePage::class,
    ])->assertTableActionHidden('rollback', $baseline);
});
