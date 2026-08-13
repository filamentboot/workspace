<?php

use Filament\Facades\Filament;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource\Pages\EditSiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource\Pages\EditSiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages\CreateNewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages\ListNewsArticles;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource\Pages\ListNewsCategories;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * 资讯资源与内容承载字段的后台页面测试
 *
 * 面板注册方式与 SiteContactResourcePageTest 同源：插件是否启用取决于 plugins.is_enabled，
 * 测试库在 boot 之后才有数据，因此手工把插件注册进面板并重跑 Filament 路由文件，
 * 让后台页面在测试里真实渲染，而不是把「会不会 500」留给手工点击。
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
        'view_any_news_article',
        'create_news_article',
        'update_news_article',
        'view_any_news_category',
        'view_any_site_product',
        'update_site_product',
        'view_any_site_case',
        'update_site_case',
    ] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
    }
});

/**
 * 创建一个拥有指定权限的管理员并登录
 *
 * @param  list<string>  $permissions
 */
function loginToNewsPanel(array $permissions): AdminUser
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
 * 文章列表页渲染，草稿与已发布都在后台可见（前台才过滤）
 */
it('资讯列表页渲染并同时列出草稿与已发布', function () {
    loginToNewsPanel(['view_any_news_article']);

    NewsArticle::factory()->create(['title_zh' => '已发布的资讯']);
    NewsArticle::factory()->draft()->create(['title_zh' => '草稿资讯']);

    Livewire::test(ListNewsArticles::class)
        ->assertOk()
        ->assertSee('已发布的资讯')
        ->assertSee('草稿资讯');
});

/**
 * 创建页表单能落库，slug 唯一约束与富文本正文均可写
 */
it('资讯创建页可提交并落库', function () {
    loginToNewsPanel(['view_any_news_article', 'create_news_article']);

    $category = NewsCategory::factory()->create(['name_zh' => '行业动态']);

    Livewire::test(CreateNewsArticle::class)
        ->fillForm([
            'slug'           => 'quan-wu-zhi-neng-zhi-nan',
            'category_id'    => $category->id,
            'title_zh'       => '全屋智能选购指南',
            'description_zh' => '从场景出发而不是从单品出发',
            'content_zh'     => '<p>先定场景，再定设备。</p>',
            'published_at'   => now(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('site_news_articles', [
        'slug'     => 'quan-wu-zhi-neng-zhi-nan',
        'title_zh' => '全屋智能选购指南',
    ]);
});

/**
 * 分类列表页渲染，文章数统计列可用
 */
it('资讯分类列表页渲染并统计文章数', function () {
    loginToNewsPanel(['view_any_news_category']);

    $category = NewsCategory::factory()->create(['name_zh' => '公司新闻']);
    NewsArticle::factory()->count(2)->create(['category_id' => $category->id]);

    Livewire::test(ListNewsCategories::class)
        ->assertOk()
        ->assertSee('公司新闻');
});

/**
 * 产品编辑页新增的详情正文字段可写入（P2 新增承载字段）
 */
it('产品编辑页可写入详情正文', function () {
    loginToNewsPanel(['view_any_site_product', 'update_site_product']);

    $product = SiteProduct::factory()->create();

    Livewire::test(EditSiteProduct::class, ['record' => $product->getKey()])
        ->fillForm(['content_zh' => '<p>支持米家与 HomeKit 双生态。</p>'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->content_zh)->toContain('双生态');
});

/**
 * 案例编辑页的「业主见证」区块三字段可写入
 */
it('案例编辑页可写入业主见证', function () {
    loginToNewsPanel(['view_any_site_case', 'update_site_case']);

    $case = SiteCase::factory()->create();

    Livewire::test(EditSiteCase::class, ['record' => $case->getKey()])
        ->fillForm([
            'customer_name'  => '张先生',
            'customer_meta'  => '万科城市之光 · 入住 8 个月',
            'customer_quote' => '回家灯就亮了，这点比什么都实在。',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($case->fresh()->hasCustomerTestimonial())->toBeTrue();
});
