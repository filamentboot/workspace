<?php

use Filamentboot\FilamentbootSite\Cms\Blocks\AbstractBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\BlockRegistry;
use Filamentboot\FilamentbootSite\Cms\Rendering\BlockRenderer;
use Filamentboot\FilamentbootSite\Cms\Rendering\BlockSanitizer;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Support\Facades\Log;

/**
 * 区块渲染器与保存侧净化测试（#13）
 *
 * 覆盖场景：
 * - 未注册的 block key 被跳过并记 warning，不抛异常（一个失效区块不能打成 500）
 * - 视图缺失同样降级跳过
 * - 脏 payload（非数组条目、缺 type）被静默丢弃
 * - withDefaults() 补齐历史 payload 缺失字段后再渲染
 * - faq 区块转 FAQPage 结构化数据；空问答不产出无效节点
 * - BlockSanitizer 只净化 rich-content.content，其余字段原样保留
 * - 两套主题各 10 个区块视图文件齐备（§0.3 第 1 条）
 *
 * 视图解析需要主题命名空间就位，因此手工调 registerThemeViews()——
 * 单测不经 HTTP，SiteServiceProvider::boot() 里那段只在插件启用时跑。
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Cms\Rendering\BlockRenderer
 * @covers \Filamentboot\FilamentbootSite\Cms\Rendering\BlockSanitizer
 */
beforeEach(function () {
    $provider = new SiteServiceProvider(app());

    $register = new ReflectionMethod($provider, 'registerThemeViews');
    $register->invoke($provider);
});

/**
 * 未注册的 block key 被跳过并记 warning
 */
it('未注册的区块被跳过并记日志', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, '未注册的区块')
            && $context['block_type'] === 'no-such-block');

    $html = app(BlockRenderer::class)->render([
        ['type' => 'no-such-block', 'data' => ['title' => '不该出现']],
    ]);

    expect((string) $html)->toBe('');
});

/**
 * 已注册但视图缺失的区块同样降级跳过
 *
 * 用一个真实注册进注册表的匿名区块，其 view() 指向不存在的视图名。
 */
it('视图缺失的区块被跳过并记日志', function () {
    $registry = app(BlockRegistry::class);
    $registry->register(new class extends AbstractBlock
    {
        public function key(): string
        {
            return 'viewless';
        }

        public function label(): string
        {
            return '没有视图的区块';
        }

        public function schema(): array
        {
            return [];
        }

        public function view(): string
        {
            return 'filamentboot-site::blocks.definitely-not-here';
        }
    });

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, '视图缺失')
            && $context['block_type'] === 'viewless');

    expect((string) app(BlockRenderer::class)->render([['type' => 'viewless', 'data' => []]]))->toBe('');
});

/**
 * 脏 payload 条目被静默丢弃，不记日志也不抛异常
 *
 * 缺 type 就无从查注册表，日志里也说不出是哪个区块，属纯脏数据。
 */
it('缺 type 或非数组的条目被丢弃', function () {
    Log::shouldReceive('warning')->never();

    $html = app(BlockRenderer::class)->render([
        'not-an-array',
        ['data' => ['title' => '无 type']],
        ['type' => '', 'data' => []],
        ['type' => 123, 'data' => []],
    ]);

    expect((string) $html)->toBe('');
});

/**
 * null 与空数组安全通过
 */
it('空 blocks 渲染为空串', function () {
    expect((string) app(BlockRenderer::class)->render(null))->toBe('')
        ->and((string) app(BlockRenderer::class)->render([]))->toBe('');
});

/**
 * 正常区块渲染出内容，且字段经 HTML 转义
 */
it('渲染 hero 区块并转义字段', function () {
    $html = (string) app(BlockRenderer::class)->render([
        [
            'type' => 'hero',
            'data' => [
                'title'    => '智能<script>alert(1)</script>家居',
                'subtitle' => '副标题',
            ],
        ],
    ]);

    expect($html)->toContain('智能')
        ->and($html)->toContain('副标题')
        ->and($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;');
});

/**
 * 历史 payload 缺字段时由 withDefaults() 补齐，渲染不报错
 *
 * media-text 的 media_position 缺失应回落 left（defaults() 的值）。
 */
it('缺字段的历史 payload 被默认值补齐', function () {
    $html = (string) app(BlockRenderer::class)->render([
        ['type' => 'media-text', 'data' => ['title' => '仅有标题']],
    ]);

    expect($html)->toContain('仅有标题')
        // 图片缺失走 image-placeholder 降级，不出破图
        ->and($html)->toContain('暂无图片');
});

/**
 * cta_url 被 SafeUrl 拦下时不渲染按钮
 */
it('hero 的不安全 cta_url 不渲染按钮', function () {
    $html = (string) app(BlockRenderer::class)->render([
        [
            'type' => 'hero',
            'data' => ['title' => '标题', 'cta_label' => '点我', 'cta_url' => 'javascript:alert(1)'],
        ],
    ]);

    expect($html)->not->toContain('点我')
        ->and($html)->not->toContain('javascript:');
});

/**
 * cta 区块留空 button_url 时改为打开询盘面板
 */
it('cta 区块空链接时渲染询盘面板触发器', function () {
    $html = (string) app(BlockRenderer::class)->render([
        [
            'type' => 'cta',
            'data' => ['title' => '预约', 'button_label' => '立即预约', 'button_url' => ''],
        ],
    ]);

    expect($html)->toContain('data-contact-trigger="page-cta"')
        ->and($html)->toContain('立即预约');
});

/**
 * faq 区块转成 FAQPage 结构化数据
 */
it('faq 区块产出 FAQPage 结构化数据', function () {
    $nodes = app(BlockRenderer::class)->structuredData([
        [
            'type' => 'faq',
            'data' => [
                'items' => [
                    ['question' => '装修周期多久？', 'answer' => '通常 45 天。'],
                    ['question' => '支持旧房改造吗？', 'answer' => '支持。'],
                ],
            ],
        ],
    ]);

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]['@type'])->toBe('FAQPage')
        ->and($nodes[0]['mainEntity'])->toHaveCount(2)
        ->and($nodes[0]['mainEntity'][0]['name'])->toBe('装修周期多久？')
        ->and($nodes[0]['mainEntity'][0]['acceptedAnswer']['text'])->toBe('通常 45 天。');
});

/**
 * 问答不完整的 faq 区块不产出无效节点
 *
 * 空 mainEntity 的 FAQPage 会被 Search Console 报为无效结构化数据。
 */
it('问答不完整的 faq 不产出结构化数据', function () {
    $nodes = app(BlockRenderer::class)->structuredData([
        ['type' => 'faq', 'data' => ['items' => [['question' => '只有问题', 'answer' => '']]]],
        ['type' => 'faq', 'data' => ['items' => []]],
        ['type' => 'hero', 'data' => ['title' => '非 faq 区块']],
    ]);

    expect($nodes)->toBe([]);
});

/**
 * BlockSanitizer 只净化 rich-content.content
 */
it('保存侧净化剥离 rich-content 里的脚本', function () {
    $sanitized = app(BlockSanitizer::class)->sanitize([
        [
            'type' => 'rich-content',
            'data' => ['title' => '标题', 'content' => '<p>正文</p><script>alert(1)</script>'],
        ],
    ]);

    expect($sanitized[0]['data']['content'])->toContain('<p>正文</p>')
        ->and($sanitized[0]['data']['content'])->not->toContain('script')
        // 其余字段原样保留，净化器不改写作者输入
        ->and($sanitized[0]['data']['title'])->toBe('标题');
});

/**
 * 非 rich-content 区块与畸形条目原样通过
 *
 * 结构问题由区块 rules() 管，在净化器里"顺手修正"会静默丢掉作者数据。
 */
it('保存侧净化不改动其它区块', function () {
    $input = [
        ['type' => 'hero', 'data' => ['title' => '<b>粗体</b>']],
        ['type' => 'rich-content'],
        'garbage',
    ];

    expect(app(BlockSanitizer::class)->sanitize($input))->toBe($input)
        ->and(app(BlockSanitizer::class)->sanitize(null))->toBe([]);
});

/**
 * 两套主题都能解析出全部 10 个区块视图
 *
 * 每个已注册区块，两套主题各自都要能渲染得出来——不是「文件必须物理放在
 * 主题目录里」。七期批次 1 起，两套主题字节相同的区块视图已下沉到
 * resources/views/shared/blocks/，按视图解析链找而不是硬编码主题目录，
 * 既不会把合法下沉的文件误判成缺失，真分岔出自己版本的区块（放回主题
 * 目录）也照样测得到——两种情况对客户来说都是"这个主题渲染得出这个区块"。
 */
it('两套主题都能解析出全部 10 个区块视图', function (string $theme) {
    $settings               = app(SiteSettings::class);
    $settings->active_theme = $theme;
    app()->instance(SiteSettings::class, $settings);

    (new ReflectionMethod(SiteServiceProvider::class, 'registerThemeViews'))
        ->invoke(new SiteServiceProvider(app()));

    app('view')->flushFinderCache();

    foreach (app(BlockRegistry::class)->keys() as $key) {
        expect(view()->exists("filamentboot-site::blocks.{$key}"))->toBeTrue(
            "主题 {$theme} 解析不到区块视图 {$key}"
        );
    }
})->with(['decoration', 'software']);
