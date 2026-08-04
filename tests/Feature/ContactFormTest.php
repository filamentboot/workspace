<?php

use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Mail\NewContactMessageMail;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Services\ContactSubmission;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * 询盘提交测试（#29 起测的是无状态端点，不再是 Livewire 组件）
 *
 * 覆盖场景：
 * - 提交后持久化到 site_contact_messages，status=UNREAD（D-10-15）
 * - 来源与首触归因从**请求体**入库（#29：归因搬到客户端 localStorage，不再读 session）
 * - 归因走白名单逐键取值，请求体里的其它键进不了库
 * - 同 IP 超过 3 次触发限流，返回 429 且不写新记录（T-10-04-02）
 * - 蜜罐字段被填、或客户端上报耗时不足 3 秒，静默丢弃但**对外回成功**（C2）
 * - 机器人提交不消耗真实访客的限流额度
 * - 通知邮件按 A2 的约定投递，且模板真的能渲染
 *
 * ⚠️ 端点**不挂 web 中间件组**，因此没有 CSRF token 可带。用 postJson 直打即可，
 * 不要 withoutMiddleware——那会连 throttle 一起关掉，反而测不到限流。
 *
 * @group site
 */
beforeEach(function () {
    // 前台路由只在 plugins.is_enabled = true 时由 SiteServiceProvider 引入，
    // 测试库在应用 boot 之后才有数据，所以手工加载
    $provider = new SiteServiceProvider(app());

    (new ReflectionMethod(SiteServiceProvider::class, 'registerThemeViews'))->invoke($provider);
    (new ReflectionMethod(SiteServiceProvider::class, 'shareSiteSettings'))->invoke($provider);

    require base_path('packages/filamentboot-site/routes/site.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    RateLimiter::clear('site-contact:127.0.0.1');
});

/**
 * 一份通过校验、不像机器人的提交载荷
 *
 * elapsed 由客户端上报（表单可交互到提交的秒数），给 5 表示「真人填了一会儿」。
 * 不给这个字段也算通过——服务端对缺失值不做耗时判断，宁可放过也不误杀。
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactPayload(array $overrides = []): array
{
    return [
        'name'    => '张三',
        'phone'   => '13800138000',
        'message' => '想了解全屋智能方案',
        'elapsed' => 5,
        ...$overrides,
    ];
}

/**
 * 提交一次询盘
 *
 * @param  array<string, mixed>  $overrides
 */
function submitContact(array $overrides = []): TestResponse
{
    return test()->postJson(route('site.contact.store'), contactPayload($overrides));
}

it('提交后持久化到数据库且默认状态为未读', function () {
    submitContact()->assertOk()->assertJson(['ok' => true]);

    $record = ContactMessage::sole();

    expect($record->name)->toBe('张三')
        ->and($record->phone)->toBe('13800138000')
        ->and($record->message)->toBe('想了解全屋智能方案')
        ->and($record->status)->toBe(ContactMessageStatus::UNREAD)
        ->and($record->ip)->toBe('127.0.0.1');
});

it('缺必填字段时返回 422 且不入库', function () {
    test()->postJson(route('site.contact.store'), ['elapsed' => 5])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonStructure(['errors' => ['name', 'phone']]);

    expect(ContactMessage::count())->toBe(0);
});

it('来源与首触归因从请求体入库', function () {
    submitContact([
        'source'       => 'hero',
        'landing_url'  => 'https://www.qkznj.com/solutions?utm_source=baidu',
        'referer'      => 'https://www.baidu.com/',
        'utm_source'   => 'baidu',
        'utm_medium'   => 'cpc',
        'utm_campaign' => 'quanwu',
    ])->assertOk();

    $record = ContactMessage::sole();

    expect($record->source)->toBe('hero')
        ->and($record->landing_url)->toBe('https://www.qkznj.com/solutions?utm_source=baidu')
        ->and($record->referer)->toBe('https://www.baidu.com/')
        ->and($record->utm_source)->toBe('baidu')
        ->and($record->utm_medium)->toBe('cpc')
        ->and($record->utm_campaign)->toBe('quanwu');
});

it('归因字段走白名单，请求体里的其它键不入库', function () {
    // ContactMessage 的 $guarded 为空，若把整段请求体摊进 create() 就等于
    // 给任意字段开了批量赋值入口——这条锁住「逐键取值」这个决定
    submitContact([
        'status' => 'read',
        'ip'     => '9.9.9.9',
    ])->assertOk();

    $record = ContactMessage::sole();

    expect($record->status)->toBe(ContactMessageStatus::UNREAD)
        ->and($record->ip)->toBe('127.0.0.1');
});

it('无归因数据时归因列为 null', function () {
    submitContact()->assertOk();

    $record = ContactMessage::sole();

    foreach (ContactSubmission::ATTRIBUTION_KEYS as $key) {
        expect($record->{$key})->toBeNull();
    }
});

it('超长归因值被截断到列宽', function () {
    submitContact([
        'landing_url' => 'https://a.test/'.str_repeat('x', 2000),
        'utm_source'  => str_repeat('y', 500),
    ])->assertOk();

    $record = ContactMessage::sole();

    expect(mb_strlen((string) $record->landing_url))->toBe(1024)
        ->and(mb_strlen((string) $record->utm_source))->toBe(255);
});

it('非法来源标识被过滤后仍正常提交', function () {
    submitContact(['source' => '<script>Hero!</script>'])->assertOk();

    // 只留小写字母、数字与连字符（开闭标签的字母都会留下来）
    expect(ContactMessage::sole()->source)->toBe('scriptheroscript');
});

it('来源过滤后为空时归一为 null', function () {
    submitContact(['source' => '！！！'])->assertOk();

    expect(ContactMessage::sole()->source)->toBeNull();
});

it('超过速率限制后返回 429 且不写新记录', function () {
    for ($i = 0; $i < ContactSubmission::RATE_LIMIT; $i++) {
        submitContact(['phone' => '1380013800'.$i])->assertOk();
    }

    expect(ContactMessage::count())->toBe(ContactSubmission::RATE_LIMIT);

    submitContact(['phone' => '13900139000'])
        ->assertStatus(429)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('errors.phone.0', '提交过于频繁，请稍后再试。');

    expect(ContactMessage::count())->toBe(ContactSubmission::RATE_LIMIT);
});

it('填了蜜罐字段的提交被静默丢弃', function () {
    // 对外回成功：回错误等于在教脚本怎么绕过
    submitContact(['website' => 'https://spam.example'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(ContactMessage::count())->toBe(0);
});

it('客户端上报耗时不足阈值时被静默丢弃', function () {
    submitContact(['elapsed' => ContactSubmission::MIN_FILL_SECONDS - 1])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(ContactMessage::count())->toBe(0);
});

it('未上报耗时时不做耗时判断', function () {
    // 客户端 JS 被拦、宿主自行调用服务、时钟异常都会走到这里。宁可放过也不误杀
    submitContact(['elapsed' => 0])->assertOk();

    expect(ContactMessage::count())->toBe(1);
});

it('机器人提交不消耗真实访客的限流额度', function () {
    // 机器人识别排在限流之前，所以下面这三次不该吃掉额度
    for ($i = 0; $i < 3; $i++) {
        submitContact(['website' => 'spam'])->assertOk();
    }

    submitContact()->assertOk();

    expect(ContactMessage::count())->toBe(1);
});

it('配置收件人后提交发出新询盘通知邮件', function () {
    $settings                = app(SiteSettings::class);
    $settings->notify_emails = 'sales@example.com, 这不是邮箱, ops@example.com';
    $settings->save();

    Mail::fake();

    submitContact()->assertOk();

    // 一封邮件投给全部合法收件人，中间那个非法地址被过滤掉
    Mail::assertQueued(
        NewContactMessageMail::class,
        fn (NewContactMessageMail $mail): bool => $mail->hasTo('sales@example.com')
            && $mail->hasTo('ops@example.com')
    );
    Mail::assertQueuedCount(1);
});

it('未配置收件人时不发通知', function () {
    Mail::fake();

    submitContact()->assertOk();

    Mail::assertNothingQueued();

    expect(ContactMessage::count())->toBe(1);
});

/**
 * 通知邮件正文可真实渲染
 *
 * Mail::fake() 不会渲染视图，光靠 assertQueued 无法发现模板坏掉；
 * 邮件模板出错只会在队列 worker 里静默失败，必须单独渲染一次。
 */
it('新询盘通知邮件正文可渲染', function () {
    $record = ContactMessage::create([
        'name'       => '测试访客',
        'phone'      => '13800138000',
        'message'    => '想了解全屋智能方案',
        'status'     => ContactMessageStatus::UNREAD,
        'ip'         => '127.0.0.1',
        'source'     => 'product-detail',
        'utm_source' => 'wechat',
    ]);

    $html = (new NewContactMessageMail($record))->render();

    expect($html)->toContain('测试访客')
        ->and($html)->toContain('13800138000')
        ->and($html)->toContain('产品详情页')   // sourceLabel() 映射生效
        ->and($html)->toContain('wechat');
});

it('提交端点不起 session', function () {
    // 端点不挂 web 组，这是「公开页零 session」的一半；
    // 另一半是内容页，在 SiteCacheBoundaryTest 里验
    $response = submitContact();

    $response->assertOk();

    expect($response->headers->getCookies())->toBe([]);
});
