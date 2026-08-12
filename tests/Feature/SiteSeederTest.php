<?php

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteDemoSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteNewsSeeder;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Support\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * 演示内容种子测试（SiteDemoSeeder / SiteNewsSeeder）
 *
 * 种子里的富文本是手写的长文案，最容易出两类静默故障：
 * 一是用了前台白名单外的标签，结构被悄悄吃掉；
 * 二是 SEO 字段超过后台表单的 maxLength，后台一打开就是校验红框。
 * 两种都不会让种子报错，只能靠断言守住。
 *
 * @group site
 */

/**
 * 提取 HTML 中出现过的标签名
 *
 * @return list<string>
 */
function htmlTagNames(string $html): array
{
    preg_match_all('/<([a-z][a-z0-9]*)[\s>\/]/i', $html, $matches);

    /** @var list<string> $tags */
    $tags = array_values(array_unique(array_map('strtolower', $matches[1])));
    sort($tags);

    return $tags;
}

/**
 * 收集所有含富文本与 SEO 字段的演示内容
 *
 * @return Collection<int, Model>
 */
function seededContent(): Collection
{
    return collect()
        ->concat(SiteCase::all())
        ->concat(SiteProduct::all())
        ->concat(SitePage::all())
        ->concat(NewsArticle::all());
}

it('演示种子灌入预期数量的内容', function () {
    $this->seed(SiteDemoSeeder::class);
    $this->seed(SiteNewsSeeder::class);

    expect(SiteCase::count())->toBe(6)
        ->and(SiteProduct::count())->toBe(18)
        // about / contact / services / faq / privacy —— privacy 是二期收尾补的合规页
        ->and(SitePage::count())->toBe(5)
        // 11 篇里有 1 篇草稿
        ->and(NewsArticle::count())->toBe(11)
        ->and(NewsArticle::published()->count())->toBe(10);
});

/**
 * 隐私政策页是合规要件，不是普通演示页
 *
 * 询盘表除访客填写的内容外，还会自动记 ip / source / landing_url / referer
 * （见 2026_08_03_100001_add_attribution_to_site_contact_messages_table）。
 * 收了这些就必须有告知入口——这一条锁住「页在、已发布、能访问」，
 * 免得哪次改种子把它顺手删了却没人发现。
 */
it('隐私政策页存在、已发布且十章骨架完整', function () {
    $this->seed(SiteDemoSeeder::class);

    $privacy = SitePage::where('slug', 'privacy')->first();

    expect($privacy)->not->toBeNull()
        ->and($privacy->status)->toBe(PageStatus::PUBLISHED);

    // 十章骨架照《个保法》催生的通行版式排（小米、华为的隐私政策同一套结构），
    // 缺哪一章都是实质缺口。前台可达性由 SitePageStatusTest 覆盖——那边才装了前台路由。
    foreach ([
        '我们如何收集和使用您的个人信息',
        '我们如何使用 Cookie 和同类技术',
        '我们如何共享、转让、公开披露您的个人信息',
        '我们如何保存和保护您的个人信息',
        '您如何管理您的个人信息',
        '我们如何处理未成年人的个人信息',
        '第三方链接及其产品与服务',
        '您的个人信息如何在全球范围内传输',
        '本政策如何更新',
        '如何联系我们',
    ] as $heading) {
        expect($privacy->content_zh)->toContain($heading);
    }
});

it('隐私政策页不写死运营主体信息', function () {
    $this->seed(SiteDemoSeeder::class);

    // 正文要能原样回流开源仓库：主体信息由页脚的 SiteSettings 渲染，
    // 写进正文就成了只对本站成立的假设（CLAUDE.md 的包内红线）。
    $content = SitePage::where('slug', 'privacy')->value('content_zh');

    expect($content)
        ->not->toContain('示例装修')
        ->not->toContain('晴空妙享')
        ->not->toContain('qkznj')
        ->not->toContain('400-800-6688')
        ->not->toContain('027-');
});

it('种子重复执行不产生重复数据', function () {
    $this->seed(SiteDemoSeeder::class);
    $this->seed(SiteNewsSeeder::class);

    $before = [SiteCase::count(), SiteProduct::count(), SitePage::count(), NewsArticle::count()];

    $this->seed(SiteDemoSeeder::class);
    $this->seed(SiteNewsSeeder::class);

    expect([SiteCase::count(), SiteProduct::count(), SitePage::count(), NewsArticle::count()])
        ->toBe($before);
});

it('每个产品都有详情正文且处于已发布状态', function () {
    $this->seed(SiteDemoSeeder::class);

    SiteProduct::all()->each(function (SiteProduct $product): void {
        expect($product->published_at)->not->toBeNull()
            ->and($product->content_zh)->not->toBeEmpty()
            ->and($product->description_zh)->not->toBeEmpty();
    });
});

/**
 * 产品是渠道商口径：不报价、品牌不是站点公司名
 *
 * 这两条都是二期 C 段刻意定的，且都属于「破坏了不报错」的那类：
 *
 * 1. `price` 一律 null —— 不上第三方价格，那是别人的经营数据、会过期。前台在 price
 *    为空时渲染「咨询价格」，填了价反而是回归。本测试原先断言的是 `price` **不为**
 *    null，正好锁的是改之前那套自有品牌口径。
 * 2. `brand` 不能是公司名 —— 示例装修有限公司是装修公司，卖各品牌智能产品 + 装修服务 +
 *    装修方案，公司名不是产品品牌。`SiteFrontController::productSchema()` 会把 brand
 *    当 `schema.org/Brand` 输出，填错等于对外声明这些产品是自有品牌。
 */
it('产品不报价且品牌不是站点公司名', function () {
    $this->seed(SiteDemoSeeder::class);

    SiteProduct::all()->each(function (SiteProduct $product): void {
        expect($product->price)->toBeNull()
            ->and($product->brand)->not->toBe('示例装修有限公司');
    });
});

/**
 * 产品正文里不出现自拟的「型号」
 *
 * QK- 是本文件自拟的编号，真实品牌的产品不会有本公司型号，所以正文里它只能叫
 * 「选配编号」（装修方案的选配项编号）。存量数据由宿主迁移
 * `2026_08_05_110000_fix_demo_product_brand_and_price.php` 同步改写。
 */
it('产品正文不把自拟编号写成型号', function () {
    $this->seed(SiteDemoSeeder::class);

    SiteProduct::all()->each(function (SiteProduct $product): void {
        expect($product->content_zh)->not->toContain('型号 QK-');
    });
});

/**
 * 富文本必须只用前台白名单内的标签
 *
 * 白名单见 Support\RichText，超纲标签会被前台静默过滤——不报错、不留痕，
 * 只是版式没了。这里断言方向是「原文标签 ⊆ 过滤后标签」，走的是与视图完全
 * 相同的 RichText::purify()，避免测试对着另一套画像空跑。
 */
it('演示富文本经 purifier 过滤后结构无损', function () {
    $this->seed(SiteDemoSeeder::class);
    $this->seed(SiteNewsSeeder::class);

    seededContent()->each(function (Model $record): void {
        $raw = (string) ($record->getAttribute('content_zh') ?? '');

        if ($raw === '') {
            return;
        }

        $purifiedTags = htmlTagNames(RichText::purify($raw));

        expect(array_diff(htmlTagNames($raw), $purifiedTags))->toBe(
            [],
            $record::class.' #'.$record->getKey().' 的正文含 purifier 白名单外的标签'
        );
    });
});

/**
 * SEO 字段不得超过后台表单的 maxLength
 *
 * 超了种子不报错，但后台一打开记录就是校验红框，且改一个字都保存不了。
 */
it('演示内容的 SEO 字段不超过后台表单长度上限', function () {
    $this->seed(SiteDemoSeeder::class);
    $this->seed(SiteNewsSeeder::class);

    seededContent()->each(function (Model $record): void {
        $label = $record::class.' #'.$record->getKey();

        expect(mb_strlen((string) $record->getAttribute('seo_title')))
            ->toBeLessThanOrEqual(70, $label.' 的 SEO 标题超过 70 字')
            ->and(mb_strlen((string) $record->getAttribute('seo_description')))
            ->toBeLessThanOrEqual(160, $label.' 的 SEO 描述超过 160 字');
    });
});

/**
 * 业主见证既要有配齐的样本，也要留一条不全的
 *
 * 首页见证轮播与案例页见证卡片都要求姓名与引言齐备才渲染，
 * 演示数据必须同时覆盖两条分支，否则降级路径在手工验收时根本走不到。
 */
it('案例见证同时覆盖配齐与不全两种情况', function () {
    $this->seed(SiteDemoSeeder::class);

    $complete = SiteCase::query()
        ->whereNotNull('customer_name')
        ->whereNotNull('customer_quote')
        ->count();

    expect($complete)->toBeGreaterThanOrEqual(3)
        ->and(SiteCase::query()->whereNull('customer_name')->count())->toBeGreaterThanOrEqual(1);
});

/**
 * 资讯发布时间要跨月，否则归档侧栏只有一行
 */
it('资讯发布时间跨越多个月份', function () {
    $this->seed(SiteNewsSeeder::class);

    $months = NewsArticle::published()
        ->get(['published_at'])
        ->map(fn (NewsArticle $article): string => (string) $article->published_at?->format('Y-m'))
        ->unique();

    expect($months->count())->toBeGreaterThanOrEqual(5);
});

it('常见问题页已发布且内容取自真实购前疑虑', function () {
    $this->seed(SiteDemoSeeder::class);

    $faq = SitePage::query()->where('slug', 'faq')->first();

    expect($faq)->not->toBeNull()
        ->and($faq?->status)->toBe(PageStatus::PUBLISHED)
        // reviews-insight.json 里的原始问句：「镇上的能不能做」
        ->and($faq?->content_zh)->toContain('周边县镇能不能上门');
});

/**
 * 软删除过的 slug 不能让种子崩
 *
 * slug 上是普通 unique 索引，不认 deleted_at。用户在后台删掉一条演示内容后
 * 重跑种子，默认作用域查不到那一行就会执行 INSERT，直接撞约束 500。
 * 这是种子此前只能做「整体跳过」的原因，现在查询带 withTrashed()。
 */
it('内容被软删除后重跑种子不报错也不复活', function () {
    $this->seed(SiteDemoSeeder::class);

    $slug = (string) SiteProduct::query()->value('slug');
    SiteProduct::query()->where('slug', $slug)->first()->delete();

    $this->seed(SiteDemoSeeder::class);

    expect(SiteProduct::withTrashed()->where('slug', $slug)->count())->toBe(1)
        // 用户删它是有意的，种子不替用户做恢复决定
        ->and(SiteProduct::query()->where('slug', $slug)->exists())->toBeFalse();
});

/**
 * 老版本装过的库要能补上后来新增的内容
 *
 * 这正是改掉「整体跳过」守卫要解决的事：以前 site_cases 一有数据就整条 Seeder
 * 返回，已装站点永远拿不到新增的产品与页面，只能清库重播。
 */
it('已播种的库能增量补上缺失内容', function () {
    $this->seed(SiteDemoSeeder::class);

    // 模拟老版本的库：案例在、产品那批还没有
    SiteProduct::query()->forceDelete();
    expect(SiteProduct::count())->toBe(0);

    $this->seed(SiteDemoSeeder::class);

    expect(SiteProduct::count())->toBe(18)
        ->and(SiteCase::count())->toBe(6);
});

/**
 * 增量补种不能反过来踩掉用户的编辑
 */
it('重跑种子不覆盖用户改过的文案', function () {
    $this->seed(SiteDemoSeeder::class);

    $product = SiteProduct::query()->firstOrFail();
    $product->update(['title_zh' => '我在后台改过的标题']);

    $this->seed(SiteDemoSeeder::class);

    expect($product->fresh()?->title_zh)->toBe('我在后台改过的标题');
});

/**
 * 案例与方案的标签是随机取的，重跑必须不累积
 *
 * 去掉整体跳过守卫之后，无条件 syncWithoutDetaching 会每跑一次多挂两个标签。
 */
it('重跑种子不给案例累积随机标签', function () {
    $this->seed(SiteDemoSeeder::class);

    $case   = SiteCase::query()->firstOrFail();
    $before = $case->tags()->count();

    $this->seed(SiteDemoSeeder::class);

    expect($case->tags()->count())->toBe($before);
});
