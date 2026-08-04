<?php

use Filamentboot\FilamentbootSite\Cms\Blocks\ContactFormBlock;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Mail\NewContactMessageMail;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Services\ContactSubmission;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * 询盘表单可配置字段（不同活动问不同问题）
 *
 * 覆盖三层：
 *   1. 区块配置解析：一行一个选项、丢弃重名与空下拉、条数上限
 *   2. 提交侧**边界**约束：条数、键长、值长、非标量。⚠️ 刻意**没有**「必填在服务端被拒」
 *      这类用例——端点无状态，无从核对是哪份表单配置提交的，必填只在浏览器生效
 *      （理由写在 ContactFormBlock 的类注释里，这里只锁住边界确实卡住了）
 *   3. 落地：入库、后台可见、通知邮件带上答案
 *
 * @group site
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.default_theme' => 'decoration',
    ]);

    $provider = new SiteServiceProvider(app());

    foreach (['registerThemeViews', 'shareSiteSettings'] as $method) {
        (new ReflectionMethod(SiteServiceProvider::class, $method))->invoke($provider);
    }

    require base_path('packages/filamentboot-site/routes/site.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    RateLimiter::clear('site-contact:127.0.0.1');
});

/**
 * 切换前台主题并重建视图命名空间（各文件各写一份，见 SiteRelatedContentTest 的注释）
 */
function switchThemeForExtraFields(string $theme): void
{
    $settings               = app(SiteSettings::class);
    $settings->active_theme = $theme;
    app()->instance(SiteSettings::class, $settings);

    (new ReflectionMethod(SiteServiceProvider::class, 'registerThemeViews'))
        ->invoke(new SiteServiceProvider(app()));

    app('view')->flushFinderCache();
}

/**
 * 提交一次带额外答案的询盘
 *
 * @param  array<string, mixed>  $extra
 */
function submitWithExtra(array $extra): TestResponse
{
    return test()->postJson(route('site.contact.store'), [
        'name'    => '张三',
        'phone'   => '13800138000',
        'message' => '想了解方案',
        'elapsed' => 5,
        'extra'   => $extra,
    ]);
}

// ---------------------------------------------------------------------------
// 区块配置解析
// ---------------------------------------------------------------------------

it('下拉选项按行解析并去重', function () {
    $fields = (new ContactFormBlock)->normalizedFields(['fields' => [
        ['label' => '预算区间', 'type' => 'select', 'options' => "10 万以内\n10-20 万\n\n10-20 万\n20 万以上"],
    ]]);

    expect($fields)->toHaveCount(1)
        ->and($fields[0]['options'])->toBe(['10 万以内', '10-20 万', '20 万以上']);
});

it('没有可选项的下拉被丢弃', function () {
    // 渲染出来会是一个只有「请选择」的下拉框：访客选不了，必填还提交不了
    $fields = (new ContactFormBlock)->normalizedFields(['fields' => [
        ['label' => '预算区间', 'type' => 'select', 'options' => "  \n "],
        ['label' => '房屋面积', 'type' => 'text'],
    ]]);

    expect(array_column($fields, 'label'))->toBe(['房屋面积']);
});

it('重名问题只保留第一个', function () {
    // 键就是问题文本，重名会让后台答案互相覆盖
    $fields = (new ContactFormBlock)->normalizedFields(['fields' => [
        ['label' => '预算区间', 'type' => 'text'],
        ['label' => '预算区间', 'type' => 'textarea'],
    ]]);

    expect($fields)->toHaveCount(1)
        ->and($fields[0]['type'])->toBe('text');
});

it('未知答题方式与空问题被丢弃', function () {
    $fields = (new ContactFormBlock)->normalizedFields(['fields' => [
        ['label' => '上传户型图', 'type' => 'file'],
        ['label' => '   ', 'type' => 'text'],
        ['label' => '何时开工', 'type' => 'text'],
    ]]);

    expect(array_column($fields, 'label'))->toBe(['何时开工']);
});

it('问题条数超过上限时截断', function () {
    $raw = [];

    for ($i = 0; $i < ContactFormBlock::MAX_FIELDS + 4; $i++) {
        $raw[] = ['label' => '问题'.$i, 'type' => 'text'];
    }

    expect((new ContactFormBlock)->normalizedFields(['fields' => $raw]))
        ->toHaveCount(ContactFormBlock::MAX_FIELDS);
});

it('选项数超过上限时截断', function () {
    $options = implode("\n", array_map(fn (int $i): string => '选项'.$i, range(1, ContactFormBlock::MAX_OPTIONS + 5)));

    $fields = (new ContactFormBlock)->normalizedFields(['fields' => [
        ['label' => '预算区间', 'type' => 'select', 'options' => $options],
    ]]);

    expect($fields[0]['options'])->toHaveCount(ContactFormBlock::MAX_OPTIONS);
});

it('没配额外问题时返回空数组', function () {
    $block = new ContactFormBlock;

    expect($block->normalizedFields([]))->toBe([])
        ->and($block->normalizedFields(['fields' => null]))->toBe([])
        ->and($block->normalizedFields(['fields' => 'not-an-array']))->toBe([]);
});

// ---------------------------------------------------------------------------
// 提交侧边界
// ---------------------------------------------------------------------------

it('额外答案按表单顺序入库', function () {
    submitWithExtra(['预算区间' => '10-20 万', '何时开工' => '三个月内'])->assertOk();

    // 存的是有序列表而不是映射：MySQL 的 JSON 对象会被规范化、丢掉键顺序，
    // 而答案顺序就是表单上问题的先后
    expect(ContactMessage::sole()->extra)->toBe([
        ['label' => '预算区间', 'value' => '10-20 万'],
        ['label' => '何时开工', 'value' => '三个月内'],
    ]);
});

it('没有额外答案时该列为 null', function () {
    test()->postJson(route('site.contact.store'), [
        'name' => '张三', 'phone' => '13800138000', 'elapsed' => 5,
    ])->assertOk();

    expect(ContactMessage::sole()->extra)->toBeNull();
});

it('空答案被丢掉而不是存空串', function () {
    submitWithExtra(['预算区间' => '', '何时开工' => '   ', '房屋面积' => '120'])->assertOk();

    expect(ContactMessage::sole()->extra)->toBe([['label' => '房屋面积', 'value' => '120']]);
});

it('全是空答案时该列为 null', function () {
    submitWithExtra(['预算区间' => '', '何时开工' => ''])->assertOk();

    expect(ContactMessage::sole()->extra)->toBeNull();
});

it('答案条数超过服务端上限时截断', function () {
    $extra = [];

    for ($i = 0; $i < ContactSubmission::MAX_EXTRA_ANSWERS + 5; $i++) {
        $extra['问题'.$i] = '答案'.$i;
    }

    submitWithExtra($extra)->assertOk();

    expect(ContactMessage::sole()->extra)->toHaveCount(ContactSubmission::MAX_EXTRA_ANSWERS);
});

it('超长的问题与答案被截断到边界', function () {
    submitWithExtra([str_repeat('问', 200) => str_repeat('答', 2000)])->assertOk();

    $extra = ContactMessage::sole()->extra ?? [];

    expect(mb_strlen($extra[0]['label']))->toBe(ContactSubmission::EXTRA_LABEL_LENGTH)
        ->and(mb_strlen($extra[0]['value']))->toBe(ContactSubmission::EXTRA_VALUE_LENGTH);
});

it('非标量答案被丢弃', function () {
    // 多选、文件那类结构化答案不在支持范围；json_encode 塞进去后台会显示成一坨乱码
    submitWithExtra(['多选题' => ['a', 'b'], '正常题' => '答案'])->assertOk();

    expect(ContactMessage::sole()->extra)->toBe([['label' => '正常题', 'value' => '答案']]);
});

it('extra 不是数组时整段忽略', function () {
    test()->postJson(route('site.contact.store'), [
        'name' => '张三', 'phone' => '13800138000', 'elapsed' => 5, 'extra' => 'oops',
    ])->assertOk();

    expect(ContactMessage::sole()->extra)->toBeNull();
});

it('答案里的控制字符被清掉', function () {
    submitWithExtra(["预算\x00区间" => "10-20\x01 万"])->assertOk();

    expect(ContactMessage::sole()->extra)->toBe([['label' => '预算区间', 'value' => '10-20 万']]);
});

// ---------------------------------------------------------------------------
// 前台渲染与通知
// ---------------------------------------------------------------------------

it('配了额外问题后前台表单渲染出对应控件', function (string $theme) {
    switchThemeForExtraFields($theme);

    SitePage::factory()->create([
        'slug'     => 'extra-fields-page',
        'title_zh' => '春季活动',
        'status'   => PageStatus::PUBLISHED,
        'blocks'   => [[
            'type' => 'contact-form',
            'data' => [
                'title'  => '领取活动方案',
                'source' => 'landing-spring',
                'fields' => [
                    ['label' => '预算区间', 'type' => 'select', 'options' => "10 万以内\n20 万以上", 'required' => true],
                    ['label' => '何时开工', 'type' => 'text', 'required' => false],
                    ['label' => '补充说明', 'type' => 'textarea', 'required' => false],
                ],
            ],
        ]],
    ]);

    $html = (string) $this->get('/extra-fields-page')->assertOk()->getContent();

    expect($html)->toContain('预算区间')
        ->and($html)->toContain('何时开工')
        ->and($html)->toContain('补充说明')
        // 下拉的选项都在
        ->and($html)->toContain('10 万以内')
        ->and($html)->toContain('20 万以上')
        // 绑定到 form.extra 的对应键
        ->and($html)->toContain('form.extra[')
        // 必填落成 HTML 属性
        ->and($html)->toContain('aria-required="true"');
})->with(['decoration', 'tech-product']);

it('未配额外问题时表单只有固定三项', function (string $theme) {
    switchThemeForExtraFields($theme);

    SitePage::factory()->create([
        'slug'     => 'no-extra-page',
        'title_zh' => '普通页面',
        'status'   => PageStatus::PUBLISHED,
        'blocks'   => [['type' => 'contact-form', 'data' => ['title' => '留下联系方式']]],
    ]);

    $html = (string) $this->get('/no-extra-page')->assertOk()->getContent();

    expect($html)->toContain('姓名')
        ->and($html)->toContain('电话')
        ->and($html)->not->toContain('form.extra[');
})->with(['decoration', 'tech-product']);

it('通知邮件带上额外答案', function () {
    $record = ContactMessage::factory()->create([
        'name'  => '测试访客',
        'phone' => '13800138000',
        'extra' => [
            ['label' => '预算区间', 'value' => '10-20 万'],
            ['label' => '何时开工', 'value' => '三个月内'],
        ],
    ]);

    $html = (new NewContactMessageMail($record))->render();

    expect($html)->toContain('预算区间')
        ->and($html)->toContain('10-20 万')
        ->and($html)->toContain('何时开工');
});

it('额外问题与固定字段撞名时邮件保留固定字段的值', function () {
    $record = ContactMessage::factory()->create([
        'name'  => '测试访客',
        'phone' => '13800138000',
        'extra' => [['label' => '电话', 'value' => '这不是真电话']],
    ]);

    $html = (new NewContactMessageMail($record))->render();

    expect($html)->toContain('13800138000')
        ->and($html)->not->toContain('这不是真电话');
});
