<?php

use Filamentboot\FilamentbootSite\Cms\Blocks\AbstractBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\BlockContract;
use Filamentboot\FilamentbootSite\Cms\Blocks\BlockRegistry;
use Filamentboot\FilamentbootSite\Cms\Blocks\ContactFormBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\CtaBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\FaqBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\FeatureGridBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\GatedDownloadBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\HeroBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\MapBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\MediaTextBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\RichContentBlock;
use Filamentboot\FilamentbootSite\Cms\Blocks\RoadmapBlock;

/**
 * 区块注册表与内置区块测试（#12）
 *
 * 覆盖场景：
 * - 注册表作为安全白名单：未知 key 查不到、非法 key 与重复 key 被拒
 * - 10 个内置区块均已注册且 key 唯一
 * - 各区块 rules() 能挡住缺字段与超长输入
 * - 有图必须有 alt
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Cms\Blocks\BlockRegistry
 */

/**
 * 10 个内置区块全部注册到容器单例
 */
it('容器中注册了 10 个内置区块', function () {
    $registry = app(BlockRegistry::class);

    expect($registry->keys())->toHaveCount(10)
        ->and($registry->keys())->toContain(
            'hero',
            'rich-content',
            'media-text',
            'feature-grid',
            'cta',
            'faq',
            'contact-form',
            'map',
            'gated-download',
            'roadmap',
        );
});

/**
 * 注册表是单例，全应用共用同一份白名单
 */
it('区块注册表是容器单例', function () {
    expect(app(BlockRegistry::class))->toBe(app(BlockRegistry::class));
});

/**
 * 未注册的 key 查不到且返回 null 而非抛异常
 *
 * 渲染层遇到历史遗留的未知 key 应当跳过并记日志，
 * 不能让一个失效区块把整个页面打成 500。
 */
it('未注册的区块 key 返回 null', function () {
    $registry = app(BlockRegistry::class);

    expect($registry->has('evil-block'))->toBeFalse()
        ->and($registry->get('evil-block'))->toBeNull();
});

/**
 * 非法 key 被拒绝注册
 *
 * key 会进入视图名，放宽字符集等于给视图路径解析留下可被内容侧影响的入口。
 */
it('非法区块 key 被拒绝注册', function (string $key) {
    $block = new class($key) extends AbstractBlock
    {
        public function __construct(private string $blockKey) {}

        public function key(): string
        {
            return $this->blockKey;
        }

        public function label(): string
        {
            return '测试区块';
        }

        public function schema(): array
        {
            return [];
        }
    };

    expect(fn () => (new BlockRegistry)->register($block))
        ->toThrow(InvalidArgumentException::class);
})->with([
    '../etc/passwd',
    'Hero',
    'hero_block',
    'hero.block',
    '',
    'hero-',
]);

/**
 * 重复注册同一个 key 被拒绝
 */
it('重复注册同一区块 key 被拒绝', function () {
    $registry = new BlockRegistry;
    $registry->register(new HeroBlock);

    expect(fn () => $registry->register(new HeroBlock))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * 每个内置区块都实现契约、视图名规范、有中文标签
 */
it('内置区块契约完整', function (string $class) {
    /** @var BlockContract $block */
    $block = new $class;

    expect($block)->toBeInstanceOf(BlockContract::class)
        ->and($block->label())->not->toBe('')
        ->and($block->view())->toBe('filamentboot-site::blocks.'.$block->key())
        ->and($block->schema())->not->toBe([]);
})->with([
    HeroBlock::class,
    RichContentBlock::class,
    MediaTextBlock::class,
    FeatureGridBlock::class,
    CtaBlock::class,
    FaqBlock::class,
    ContactFormBlock::class,
    MapBlock::class,
    GatedDownloadBlock::class,
    RoadmapBlock::class,
]);

/**
 * 后台下拉选项为 key => 中文标签
 */
it('注册表输出 key 到标签的映射', function () {
    $options = app(BlockRegistry::class)->options();

    expect($options['hero'])->toBe('首屏横幅')
        ->and($options['faq'])->toBe('常见问题');
});

/**
 * hero 区块：缺主标题被拒
 */
it('hero 区块缺主标题被拒', function () {
    $errors = (new HeroBlock)->validate(['subtitle' => '只有副标题']);

    expect($errors)->toHaveKey('title');
});

/**
 * hero 区块：上传了图片却没填 alt 被拒（无障碍与图片搜索收录）
 */
it('hero 区块有图无 alt 被拒', function () {
    $block = new HeroBlock;

    expect($block->validate(['title' => '标题', 'image' => 'site/hero.jpg']))
        ->toHaveKey('image_alt');

    expect($block->validate(['title' => '标题', 'image' => 'site/hero.jpg', 'image_alt' => '首屏配图']))
        ->toBe([]);
});

/**
 * hero 区块：填了按钮文字却没填链接被拒
 */
it('hero 区块有按钮文字无链接被拒', function () {
    $errors = (new HeroBlock)->validate(['title' => '标题', 'cta_label' => '立即咨询']);

    expect($errors)->toHaveKey('cta_url');
});

/**
 * media-text 区块：图片与 alt 均为必填
 */
it('media-text 区块图片与 alt 必填', function () {
    $errors = (new MediaTextBlock)->validate(['title' => '标题', 'body' => '正文']);

    expect($errors)->toHaveKeys(['image', 'image_alt']);
});

/**
 * media-text 区块：图片位置只允许 left / right
 */
it('media-text 区块图片位置取值受限', function () {
    $errors = (new MediaTextBlock)->validate([
        'title'          => '标题',
        'body'           => '正文',
        'image'          => 'site/a.jpg',
        'image_alt'      => '配图',
        'media_position' => 'center',
    ]);

    expect($errors)->toHaveKey('media_position');
});

/**
 * feature-grid 区块：条目为空被拒，条目内标题必填
 */
it('feature-grid 区块条目校验', function () {
    $block = new FeatureGridBlock;

    expect($block->validate(['columns' => 3, 'items' => []]))->toHaveKey('items');

    expect($block->validate([
        'columns' => 3,
        'items'   => [['icon' => 'heroicon-o-star', 'description' => '缺标题']],
    ]))->toHaveKey('items.0.title');
});

/**
 * feature-grid 区块：列数取值受限
 */
it('feature-grid 区块列数取值受限', function () {
    $errors = (new FeatureGridBlock)->validate([
        'columns' => 7,
        'items'   => [['title' => '优势']],
    ]);

    expect($errors)->toHaveKey('columns');
});

/**
 * faq 区块：问答均为必填
 */
it('faq 区块问答必填', function () {
    $errors = (new FaqBlock)->validate(['items' => [['question' => '只有问题']]]);

    expect($errors)->toHaveKey('items.0.answer');
});

/**
 * rich-content 区块：正文必填
 */
it('rich-content 区块正文必填', function () {
    expect((new RichContentBlock)->validate(['title' => '小标题']))->toHaveKey('content');
});

/**
 * cta 区块：主张与按钮文字必填，样式取值受限
 */
it('cta 区块必填与样式校验', function () {
    $block = new CtaBlock;

    expect($block->validate([]))->toHaveKeys(['title', 'button_label', 'style']);

    expect($block->validate([
        'title'        => '现在就开始',
        'button_label' => '预约咨询',
        'style'        => 'rainbow',
    ]))->toHaveKey('style');
});

/**
 * roadmap 区块：条目为空被拒，条目内标题与状态必填
 */
it('roadmap 区块条目校验', function () {
    $block = new RoadmapBlock;

    expect($block->validate(['items' => []]))->toHaveKey('items');

    expect($block->validate([
        'items' => [['status' => 'available', 'description' => '缺标题']],
    ]))->toHaveKey('items.0.title');
});

/**
 * roadmap 区块：状态只允许 available / in_progress / planned
 *
 * 三档是四期已拍板的固定分类，不开放自定义状态字符串——开放了就会有人
 * 悄悄多出第四档，Roadmap 页「只分三档」的纪律就管不住了。
 */
it('roadmap 区块状态取值受限', function () {
    $errors = (new RoadmapBlock)->validate([
        'items' => [['status' => 'shipped', 'title' => '已发布']],
    ]);

    expect($errors)->toHaveKey('items.0.status');
});

/**
 * contact-form 区块：来源标识字符集受限
 *
 * 与 ContactForm::normalizedSource() 的过滤规则一致，
 * 否则后台填了含非法字符的来源，入库时会被静默剥掉而对不上账。
 */
it('contact-form 区块来源标识字符集受限', function () {
    $block = new ContactFormBlock;

    expect($block->validate(['source' => 'Landing Page!']))->toHaveKey('source');
    expect($block->validate(['source' => 'landing-spring']))->toBe([]);
});

/**
 * 默认值可补齐历史 payload 缺失的字段
 */
it('默认值补齐历史 payload 缺失字段', function () {
    $filled = (new CtaBlock)->withDefaults(['title' => '现在就开始']);

    expect($filled['title'])->toBe('现在就开始')
        ->and($filled['button_label'])->toBe('预约咨询')
        ->and($filled['style'])->toBe('primary');
});

/**
 * 合法 payload 通过校验
 */
it('合法 payload 通过校验', function () {
    $errors = (new FaqBlock)->validate([
        'title' => '常见问题',
        'items' => [
            ['question' => '安装周期多久？', 'answer' => '通常 3 到 5 个工作日。'],
        ],
    ]);

    expect($errors)->toBe([]);
});
