<?php

use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Http\Livewire\ContactForm;
use Filamentboot\FilamentbootSite\Http\Middleware\CaptureVisitorAttribution;
use Filamentboot\FilamentbootSite\Mail\NewContactMessageMail;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

// 注册 filamentboot-site 视图命名空间指向包内视图目录（10-04 测试阶段，10-05 完整主题视图替代）
beforeEach(function () {
    $viewsPath = base_path('packages/filamentboot-site/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('filamentboot-site', $viewsPath);
    }
});

/**
 * 模拟真人填表耗时（C2）
 *
 * 组件在 mount() 记下渲染时刻，提交时若不足 3 秒即判为机器人并静默丢弃。
 * Livewire::test() 里 mount 与 call 之间只隔几微秒，不推进时钟的话
 * 每个正常提交用例都会被反刷规则挡下——这不是绕过，正是它该有的行为。
 *
 * 交给 tap 而不是放进 beforeEach：时钟必须在 mount 之后才推进。
 */
function humanPace(): Closure
{
    return static function (): void {
        travel(4)->seconds();
    };
}

/**
 * 询盘表单测试（ContactFormTest）
 *
 * 覆盖场景：
 * - 表单提交后持久化到 site_contact_messages，status=UNREAD（D-10-15）
 * - 同 IP 超过 3 次提交触发速率限制，不写入新记录（D-10-15 安全，T-10-04-02）
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Http\Livewire\ContactForm
 */

/**
 * 询盘表单提交后持久化到数据库且默认状态为未读（D-10-15）
 */
it('询盘表单提交后持久化到数据库且默认状态为未读', function () {
    // 清除该测试的速率限制器（避免测试间干扰）
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    Livewire::test(ContactForm::class)
        ->set('name', '张三')
        ->set('phone', '13800138000')
        ->set('message', '我想了解你们的智能家居方案，请与我联系。')
        ->tap(humanPace())->call('submit');

    // 断言 site_contact_messages 表新增了一条记录
    $this->assertDatabaseCount('site_contact_messages', 1);

    // 断言记录内容正确
    $this->assertDatabaseHas('site_contact_messages', [
        'name'   => '张三',
        'phone'  => '13800138000',
        'status' => ContactMessageStatus::UNREAD->value,
    ]);
});

/**
 * 提交时把转化入口与 session 中的首触归因一并入库（A1）
 *
 * 此前埋点入口全做了（Alpine store 有 source、各 CTA 有 data-contact-trigger），
 * 但组件没有 source 属性、表也没有对应列，数据一列没落。
 */
it('提交时来源与首触归因一并入库', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    session([CaptureVisitorAttribution::SESSION_KEY => [
        'landing_url'  => 'http://localhost/solutions?utm_source=wechat',
        'referer'      => 'https://www.baidu.com/s?wd=%E6%99%BA%E8%83%BD%E5%AE%B6%E5%B1%85',
        'utm_source'   => 'wechat',
        'utm_medium'   => 'cpc',
        'utm_campaign' => 'summer-2026',
        'utm_term'     => null,
        'utm_content'  => null,
    ]]);

    Livewire::test(ContactForm::class)
        ->set('name', '李四')
        ->set('phone', '13900139000')
        ->set('source', 'product-detail')
        ->tap(humanPace())->call('submit');

    $record = ContactMessage::latest('id')->first();

    expect($record->source)->toBe('product-detail')
        ->and($record->landing_url)->toBe('http://localhost/solutions?utm_source=wechat')
        ->and($record->referer)->toContain('baidu.com')
        ->and($record->utm_source)->toBe('wechat')
        ->and($record->utm_medium)->toBe('cpc')
        ->and($record->utm_campaign)->toBe('summer-2026')
        ->and($record->utm_term)->toBeNull();
});

/**
 * 非法字符的 source 被过滤而不是拒绝提交
 *
 * source 由客户端提供且访客并未填写，为它报错只会让人无从修正；
 * 但不过滤会把任意字符串原样带进后台列表与导出文件。
 */
it('非法来源标识被过滤后仍正常提交', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    Livewire::test(ContactForm::class)
        ->set('name', '王五')
        ->set('phone', '13700137000')
        ->set('source', '<script>alert(1)</script>')
        ->tap(humanPace())->call('submit')
        ->assertHasNoErrors();

    expect(ContactMessage::latest('id')->first()->source)->toBe('scriptalert1script');
});

/**
 * 无归因数据时归因列为 null，不写入空字符串
 */
it('无归因数据时归因列为 null', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    Livewire::test(ContactForm::class)
        ->set('name', '赵六')
        ->set('phone', '13600136000')
        ->tap(humanPace())->call('submit');

    $record = ContactMessage::latest('id')->first();

    expect($record->source)->toBeNull()
        ->and($record->landing_url)->toBeNull()
        ->and($record->utm_source)->toBeNull();
});

/**
 * 配置了收件人时投递新询盘通知邮件（A2）
 */
it('配置收件人后提交发出新询盘通知邮件', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    $settings                = app(SiteSettings::class);
    $settings->notify_emails = 'sales@example.com, 这不是邮箱, ops@example.com';
    $settings->save();

    Mail::fake();

    Livewire::test(ContactForm::class)
        ->set('name', '钱七')
        ->set('phone', '13500135000')
        ->tap(humanPace())->call('submit');

    // 一封邮件投给全部合法收件人，中间那个非法地址被过滤掉
    Mail::assertQueued(
        NewContactMessageMail::class,
        fn (NewContactMessageMail $mail): bool => $mail->hasTo('sales@example.com')
            && $mail->hasTo('ops@example.com')
    );
    Mail::assertQueuedCount(1);
});

/**
 * 通知邮件正文可真实渲染
 *
 * Mail::fake() 不会渲染视图，光靠上面的 assertQueued 无法发现模板坏掉；
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

/**
 * 未配置收件人时不发通知，也不报错
 */
it('未配置收件人时不发通知', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    $settings                = app(SiteSettings::class);
    $settings->notify_emails = '';
    $settings->save();

    Mail::fake();

    Livewire::test(ContactForm::class)
        ->set('name', '孙八')
        ->set('phone', '13400134000')
        ->tap(humanPace())->call('submit')
        ->assertSet('submitted', true);

    Mail::assertNothingQueued();
    $this->assertDatabaseCount('site_contact_messages', 1);
});

/**
 * 通知通道抛异常时表单仍然提交成功（A2 硬要求）
 *
 * Mail::queue() 在队列后端不可用时会抛异常。访客侧的成功提示不能依赖通知结果——
 * 线索已经落库，通知失败是运维问题，不该让访客看到失败页面然后重复提交。
 */
it('通知发送失败时表单仍提交成功', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    $settings                = app(SiteSettings::class);
    $settings->notify_emails = 'sales@example.com';
    $settings->save();

    // 模拟队列后端不可用：Mail::to()->queue() 直接抛异常
    Mail::shouldReceive('to')->andThrow(new RuntimeException('queue backend unavailable'));

    Livewire::test(ContactForm::class)
        ->set('name', '周九')
        ->set('phone', '13300133000')
        ->tap(humanPace())->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $this->assertDatabaseCount('site_contact_messages', 1);
});

/**
 * 询盘表单超过速率限制后拒绝提交，不写入新记录（D-10-15 安全，T-10-04-02）
 */
it('询盘表单超过速率限制后拒绝提交', function () {
    $rateLimitKey = 'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1');

    // 清除速率限制，确保干净环境
    RateLimiter::clear($rateLimitKey);

    // 连续提交 3 次（达到上限），每次都应成功
    for ($i = 1; $i <= 3; $i++) {
        Livewire::test(ContactForm::class)
            ->set('name', "用户{$i}")
            ->set('phone', '13800138'.str_pad($i, 3, '0', STR_PAD_LEFT))
            ->set('message', "留言内容{$i}")
            ->tap(humanPace())->call('submit');
    }

    // 3 条记录已写入
    $this->assertDatabaseCount('site_contact_messages', 3);

    // 第 4 次提交应触发速率限制，返回错误，不写入新记录
    $component = Livewire::test(ContactForm::class)
        ->set('name', '第四次提交')
        ->set('phone', '13800138999')
        ->set('message', '这次应该被限速')
        ->tap(humanPace())->call('submit');

    // 断言速率限制错误已添加（错误信息在 phone 字段）
    $component->assertHasErrors(['phone']);

    // 断言数据库仍只有 3 条记录（第 4 次未写入）
    $this->assertDatabaseCount('site_contact_messages', 3);
});

/**
 * 蜜罐字段被填写时静默丢弃（C2）
 *
 * 关键在「静默」：回一个错误等于在教脚本怎么绕过，回成功则让它以为得手、
 * 不会换策略重试。所以断言的是「看起来成功了，但库里什么都没有」。
 */
it('填了蜜罐字段的提交被静默丢弃', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    Mail::fake();

    Livewire::test(ContactForm::class)
        ->set('name', '机器人')
        ->set('phone', '13800138000')
        ->set('website', 'http://spam.example.com')
        ->tap(humanPace())->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $this->assertDatabaseCount('site_contact_messages', 0);
    Mail::assertNothingQueued();
});

/**
 * 渲染到提交不足 3 秒判为机器（C2）
 *
 * 这里不调 humanPace()，模拟脚本秒填秒交。
 */
it('提交过快的请求被静默丢弃', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    Livewire::test(ContactForm::class)
        ->set('name', '快枪手')
        ->set('phone', '13800138000')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $this->assertDatabaseCount('site_contact_messages', 0);
});

/**
 * 机器人提交不派发转化事件
 *
 * 否则投放后台的转化数会被灌水，比漏报更难排查。
 */
it('机器人提交不上报转化事件', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    Livewire::test(ContactForm::class)
        ->set('name', '机器人')
        ->set('phone', '13800138000')
        ->set('website', 'spam')
        ->tap(humanPace())->call('submit')
        ->assertNotDispatched('site-contact-submitted');
});

/**
 * 机器人不消耗真实访客共享的 IP 限流额度
 *
 * 反刷判断放在 rateLimit() 之前，否则同一出口 IP 下的脚本三下就能把
 * 整个办公室/小区的访客一起锁在门外。
 */
it('机器人提交不消耗限流额度', function () {
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    // 先来 5 次机器人提交
    for ($i = 0; $i < 5; $i++) {
        Livewire::test(ContactForm::class)
            ->set('name', "机器人{$i}")
            ->set('phone', '13800138000')
            ->set('website', 'spam')
            ->tap(humanPace())->call('submit');
    }

    // 真实访客紧随其后仍应提交成功
    Livewire::test(ContactForm::class)
        ->set('name', '真实访客')
        ->set('phone', '13900139000')
        ->tap(humanPace())->call('submit')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('site_contact_messages', 1);
    $this->assertDatabaseHas('site_contact_messages', ['name' => '真实访客']);
});

/**
 * 蜜罐字段在 DOM 里但对读屏与键盘不可达
 *
 * 用屏外定位而非 display:none —— 后者是已知特征，成熟脚本会跳过。
 */
it('蜜罐字段渲染为屏外且不可聚焦', function () {
    $html = Livewire::test(ContactForm::class)->html();

    expect($html)->toContain('id="contact-website"')
        ->and($html)->toContain('left: -9999px')
        ->and($html)->toContain('tabindex="-1"')
        ->and($html)->toContain('aria-hidden="true"')
        // display:none 会被脚本识别为蜜罐特征
        ->and($html)->not->toContain('display: none; left: -9999px');
});
