<?php

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * 草稿预览授权测试（#16）
 *
 * 覆盖场景：
 * - 双通道：有效签名 或 已登录管理员且有 view 权限
 * - 未登录且无签名 → 403；签名过期 → 403；签名被篡改 → 403
 * - 已登录但无 view_site_page 权限 → 403
 * - 响应带 X-Robots-Tag: noindex, nofollow（签名 URL 外泄不能让草稿进搜索结果）
 * - 预览页不输出 canonical / og:url（已 noindex，再自指规范地址是矛盾信号）
 * - 预览绕过 published() 但不绕过软删除
 * - 预览渲染区块（与正式渲染共用 pageViewData）
 *
 * 走真实 HTTP：授权是中间件与控制器协作的结果，直调控制器测不出来。
 *
 * @group site
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.themes'        => ['decoration' => '科技装修（浅色）'],
        'filamentboot-site.default_theme' => 'decoration',
    ]);

    $provider = new SiteServiceProvider(app());

    foreach (['registerThemeViews', 'shareSiteSettings', 'registerFrontend'] as $method) {
        $reflection = new ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * 生成一个带 view_site_page 权限的管理员
 */
function adminWithPageView(): AdminUser
{
    Permission::firstOrCreate(['name' => 'view_site_page', 'guard_name' => 'admin']);

    $role = Role::create(['name' => 'previewer-'.uniqid(), 'guard_name' => 'admin']);
    $role->givePermissionTo('view_site_page');

    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * 有效签名可预览草稿
 */
it('带有效签名可预览草稿', function () {
    $page = SitePage::factory()->draft()->create(['title_zh' => '未发布的草稿标题']);

    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);

    $this->get($url)
        ->assertOk()
        ->assertSee('未发布的草稿标题', escape: false);
});

/**
 * 预览响应带 noindex，签名 URL 外泄也不会被收录
 */
it('预览响应带 noindex 头', function () {
    $page = SitePage::factory()->draft()->create();

    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);

    $this->get($url)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

/**
 * 预览页不输出 canonical 与 og:url
 *
 * 已经 noindex，再自指一个规范地址是矛盾信号，签名 URL 本身也不该被当作正式地址。
 */
it('预览页不输出 canonical', function () {
    $page = SitePage::factory()->draft()->create();

    $url  = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->not->toContain('rel="canonical"')
        ->and($html)->not->toContain('property="og:url"');
});

/**
 * 正式页面仍然输出 canonical（确认上面那条不是把全站的 canonical 关掉了）
 */
it('正式页面仍输出 canonical', function () {
    $page = SitePage::factory()->create(['slug' => 'normal-page']);

    $html = $this->get('/'.$page->slug)->assertOk()->getContent();

    expect($html)->toContain('rel="canonical"');
});

/**
 * 未登录且无签名 → 403
 */
it('无签名未登录访问预览被拒', function () {
    $page = SitePage::factory()->draft()->create();

    $this->get('/preview/site_page/'.$page->getKey())->assertForbidden();
});

/**
 * 签名过期 → 403
 */
it('签名过期后预览被拒', function () {
    $page = SitePage::factory()->draft()->create();

    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);

    $this->travel(16)->minutes();

    $this->get($url)->assertForbidden();
});

/**
 * 签名被篡改（改成另一个页面 id）→ 403
 */
it('篡改签名后预览被拒', function () {
    $page  = SitePage::factory()->draft()->create();
    $other = SitePage::factory()->draft()->create();

    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);

    $tampered = str_replace('/preview/site_page/'.$page->getKey(), '/preview/site_page/'.$other->getKey(), $url);

    $this->get($tampered)->assertForbidden();
});

/**
 * 已登录管理员直接访问即可预览，不需要签名
 *
 * 只挂 signed 中间件会把已登录管理员挡在门外，这条锁住那个设计。
 */
it('已登录管理员无需签名即可预览', function () {
    $page = SitePage::factory()->draft()->create(['title_zh' => '管理员直看的草稿']);

    $this->actingAs(adminWithPageView(), 'admin')
        ->get('/preview/site_page/'.$page->getKey())
        ->assertOk()
        ->assertSee('管理员直看的草稿', escape: false);
});

/**
 * 已登录但无 view_site_page 权限 → 403
 */
it('无查看权限的管理员预览被拒', function () {
    $page = SitePage::factory()->draft()->create();

    $role = Role::create(['name' => 'nobody-'.uniqid(), 'guard_name' => 'admin']);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user, 'admin')
        ->get('/preview/site_page/'.$page->getKey())
        ->assertForbidden();
});

/**
 * 四种未发布状态都能预览（预览存在的理由就是绕过 published()）
 */
it('四种未发布状态都可预览', function (string $state) {
    $page = SitePage::factory()->{$state}()->create(['title_zh' => '状态 '.$state]);

    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);

    $this->get($url)->assertOk()->assertSee('状态 '.$state, escape: false);
})->with(['draft', 'review', 'scheduled', 'archived']);

/**
 * 已软删除的页面不可预览
 *
 * 隐式绑定带软删除全局作用域，删掉的东西不该还能预览。
 */
it('已删除页面不可预览', function () {
    $page = SitePage::factory()->draft()->create();

    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);

    $page->delete();

    $this->get($url)->assertNotFound();
});

/**
 * 预览渲染区块（与正式渲染共用 pageViewData，预览所见即发布后所见）
 */
it('预览渲染页面区块', function () {
    $page = SitePage::factory()->draft()->create([
        'blocks' => [
            ['type' => 'hero', 'data' => ['title' => '草稿里的首屏标题']],
            ['type' => 'faq', 'data' => ['items' => [['question' => '草稿问题', 'answer' => '草稿答案']]]],
        ],
    ]);

    $url  = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);
    $html = $this->get($url)->assertOk()->getContent();

    expect($html)->toContain('草稿里的首屏标题')
        ->and($html)->toContain('草稿问题')
        // 区块结构化数据同样输出，预览时能核对 FAQ 有没有生效
        ->and($html)->toContain('FAQPage');
});

/**
 * 预览路由不会被 /{slug} 兜底吞掉
 *
 * root 模式下 /{slug} 是最后注册的贪婪路由，preview 靠 reserved_slugs 的
 * 负向预查排除。断言解析到的路由名，而不是断言状态码——
 * 状态码相同不代表命中了同一条路由。
 */
it('preview 路径解析到预览路由', function () {
    $page = SitePage::factory()->draft()->create();

    $route = app('router')->getRoutes()->match(
        Request::create('/preview/site_page/'.$page->getKey(), 'GET')
    );

    expect($route->getName())->toBe('site.preview');
});

/**
 * 不存在的页面 id 返回 404 而不是 403
 *
 * 授权判定在控制器里，隐式绑定先于它执行——「页面不存在」与「无权查看」
 * 是两件事，混成同一个状态码会让编辑分不清是链接错了还是权限不够。
 */
it('不存在的页面预览返回 404', function () {
    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => 999999]);

    $this->get($url)->assertNotFound();
});

/**
 * 不存在的预览类型返回 404
 *
 * $type 只认 SiteFrontController::PREVIEW_TYPES 里的键，不能让任意字符串
 * 探测出「这个类型存在与否」之外的信息。
 */
it('不存在的预览类型返回 404', function () {
    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'not_a_real_type', 'id' => 1]);

    $this->get($url)->assertNotFound();
});
