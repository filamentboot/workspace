<?php

use Filamentboot\FilamentbootSite\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SitePageRevision;
use Filamentboot\FilamentbootSite\Models\SiteRedirect;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Models\AdminUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 页面发布状态机测试（#11）
 *
 * 覆盖场景：
 * - draft / review / scheduled（未到期）/ archived 四态均不可通过公开 URL 访问
 * - 四态均不出现在站点地图
 * - scheduled 到点后自动可见（定时发布靠查询过滤，不依赖队列或定时任务）
 * - is_published 旧列由 status 派生，保留期内不失真
 * - 新建的四张表关系可用
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
});

/**
 * 已发布页面正常可访问（对照组）
 */
it('已发布页面可通过公开 URL 访问', function () {
    SitePage::factory()->create(['slug' => 'published-page', 'title_zh' => '已发布的页面']);

    $this->get('/published-page')
        ->assertOk()
        ->assertSee('已发布的页面', escape: false);
});

/**
 * 四态均不可通过公开 URL 访问（T-10-04-04 草稿不泄露）
 */
it('草稿/审核/定时未到/归档四态均返回 404', function (string $state) {
    SitePage::factory()->{$state}()->create(['slug' => "state-{$state}-page"]);

    $this->get("/state-{$state}-page")->assertNotFound();
})->with(['draft', 'review', 'scheduled', 'archived']);

/**
 * 四态均不出现在站点地图
 *
 * SitemapController 走的就是 SitePage::published()，scope 改对了这条自然成立；
 * 但站点地图泄露草稿 URL 等于把未发布内容主动推给搜索引擎，值得单独锁住。
 */
it('未发布的四态均不进站点地图', function () {
    SitePage::factory()->create(['slug' => 'visible-in-sitemap']);

    foreach (['draft', 'review', 'scheduled', 'archived'] as $state) {
        SitePage::factory()->{$state}()->create(['slug' => "hidden-{$state}"]);
    }

    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('visible-in-sitemap');

    foreach (['draft', 'review', 'scheduled', 'archived'] as $state) {
        expect($xml)->not->toContain("hidden-{$state}");
    }
});

/**
 * 定时发布到点后自动可见，无需队列或定时任务
 */
it('定时发布到点后页面自动可见', function () {
    $page = SitePage::factory()->scheduled(now()->addHour())->create(['slug' => 'scheduled-page']);

    $this->get('/scheduled-page')->assertNotFound();

    // 到点：状态转为已发布且发布时间已过
    $page->update([
        'status'       => PageStatus::PUBLISHED,
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/scheduled-page')->assertOk();
});

/**
 * 已发布但 published_at 在未来的页面同样不可见
 *
 * 防止绕过 scheduled 状态、直接把 status 设成 published 却填了未来时间时内容提前泄露。
 */
it('发布时间在未来的已发布页面仍不可见', function () {
    SitePage::factory()->create([
        'slug'         => 'future-published-page',
        'status'       => PageStatus::PUBLISHED,
        'published_at' => now()->addDay(),
    ]);

    $this->get('/future-published-page')->assertNotFound();
});

/**
 * is_published 旧列由 status 派生
 *
 * 旧列保留一个版本供下游回滚，saving 钩子保证它不会停在一个过期的值上。
 */
it('is_published 旧列跟随 status 变化', function () {
    $page = SitePage::factory()->draft()->create(['slug' => 'legacy-column-page']);

    expect($page->fresh()->is_published)->toBeFalse();

    $page->update(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subMinute()]);
    expect($page->fresh()->is_published)->toBeTrue();

    $page->update(['status' => PageStatus::ARCHIVED]);
    expect($page->fresh()->is_published)->toBeFalse();
});

/**
 * blocks 列以数组形式读写
 */
it('blocks 列以数组读写', function () {
    $page = SitePage::factory()->create([
        'slug'   => 'blocks-page',
        'blocks' => [
            ['type' => 'hero', 'data' => ['title' => '标题']],
        ],
    ]);

    // MySQL 的 JSON 类型会重排对象键，因此比较内容而非键顺序
    $blocks = $page->fresh()->blocks;

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('hero')
        ->and($blocks[0]['data']['title'])->toBe('标题');
});

/**
 * 版本快照表可写入并关联页面与操作人，快照无 updated_at
 *
 * ⚠️ #15 之后 SitePageObserver 会在新建页面时自动写一条基线快照，
 * 所以这里断言「关联里含这条手工快照」而不是断言总数——
 * 总数属于观察器的行为，由 SitePageRevisionTest 覆盖。
 */
it('版本快照可写入并关联页面与操作人', function () {
    $page   = SitePage::factory()->create(['slug' => 'revision-page']);
    $author = AdminUser::factory()->create();

    $revision = SitePageRevision::create([
        'page_id'    => $page->getKey(),
        'payload'    => ['title_zh' => '快照标题', 'blocks' => []],
        'created_by' => $author->getKey(),
    ]);

    expect($page->refresh()->revisions->pluck('id'))->toContain($revision->getKey())
        ->and($revision->payload['title_zh'])->toBe('快照标题')
        ->and($revision->author->getKey())->toBe($author->getKey())
        ->and($revision->getAttributes())->not->toHaveKey('updated_at');
});

/**
 * 页面删除时版本快照级联清理
 *
 * 计数用「删除前的实际条数」作基准，不写死数字：#15 的观察器让新建页面
 * 自带一条基线快照，写死数字会随观察器行为变化而假红。
 */
it('页面删除时版本快照级联清理', function () {
    $page = SitePage::factory()->create(['slug' => 'revision-cascade-page']);

    SitePageRevision::create(['page_id' => $page->getKey(), 'payload' => []]);

    $before = SitePageRevision::count();

    expect($before)->toBeGreaterThan(0);

    // 软删除不清理快照（内容还能恢复）
    $page->delete();
    expect(SitePageRevision::count())->toBe($before);

    // 彻底删除才级联
    $page->forceDelete();
    expect(SitePageRevision::count())->toBe(0);
});

/**
 * 菜单项树形结构可用，根节点 parent_id 为 0
 *
 * parent_id 用 0 而非 null 是 filament-tree 的约定，也正因如此不能加外键——
 * 主包 menus 表当初为此额外付了一个迁移的代价。
 */
it('菜单项树形结构根节点 parent_id 为 0', function () {
    $menu = SiteMenu::create(['key' => 'main', 'name' => '主导航']);

    $root = SiteMenuItem::create([
        'menu_id' => $menu->getKey(),
        'type'    => 'page',
        'label'   => '关于我们',
        'target'  => 'about',
        'sort'    => 1,
    ]);

    $child = SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => $root->getKey(),
        'type'      => 'url',
        'label'     => '公司简介',
        'target'    => 'https://example.com',
        'sort'      => 1,
    ]);

    expect($root->fresh()->parent_id)->toBe(0)
        ->and($root->isRoot())->toBeTrue()
        ->and($root->children)->toHaveCount(1)
        ->and($child->fresh()->parent_id)->toBe($root->getKey())
        ->and($menu->refresh()->rootItems)->toHaveCount(1)
        ->and($menu->items)->toHaveCount(2);
});

/**
 * ModelTree 的列名覆盖生效（sort / label）
 */
it('菜单项使用 sort 与 label 作为排序与标题列', function () {
    $item = new SiteMenuItem;

    expect($item->determineOrderColumnName())->toBe('sort')
        ->and($item->determineTitleColumnName())->toBe('label');
});

/**
 * 菜单删除时菜单项级联清理
 */
it('菜单删除时菜单项级联清理', function () {
    $menu = SiteMenu::create(['key' => 'footer', 'name' => '页脚导航']);

    SiteMenuItem::create(['menu_id' => $menu->getKey(), 'type' => 'page', 'label' => '联系我们']);

    $menu->delete();

    expect(SiteMenuItem::count())->toBe(0);
});

/**
 * 重定向表默认 301 且 from_path 唯一
 */
it('重定向默认 301 且源路径唯一', function () {
    SiteRedirect::create(['from_path' => '/old-about', 'to_path' => '/about']);

    $redirect = SiteRedirect::first();

    expect($redirect->status_code)->toBe(301)
        ->and($redirect->hits)->toBe(0);

    expect(fn () => SiteRedirect::create(['from_path' => '/old-about', 'to_path' => '/elsewhere']))
        ->toThrow(QueryException::class);
});
