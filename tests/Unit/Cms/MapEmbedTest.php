<?php

use Filamentboot\FilamentbootSite\Cms\Blocks\MapBlock;
use Filamentboot\FilamentbootSite\Support\MapEmbed;

/**
 * 地图嵌入地址白名单测试
 *
 * 地图区块让作者粘一条服务商生成的嵌入地址，iframe 由我们自己拼。
 * 于是唯一要防的是「src 指向哪里」——iframe 能加载任何东西并全屏覆盖页面，
 * 所以放行判据必须是精确匹配而不是「以某域名结尾」。
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Support\MapEmbed
 */
beforeEach(function () {
    config(['filamentboot-site.map.allowed_hosts' => ['map.baidu.com', 'uri.amap.com']]);
});

it('放行白名单内的 https 地址', function () {
    $url = 'https://map.baidu.com/?shareurl=1&poi=abc';

    expect(MapEmbed::sanitize($url))->toBe($url);
});

it('host 大小写不影响放行', function () {
    $url = 'https://MAP.Baidu.com/?poi=abc';

    expect(MapEmbed::sanitize($url))->toBe($url);
});

it('拒绝 http：混合内容会被浏览器拦掉，放行等于放行一个必然不显示的地图', function () {
    expect(MapEmbed::sanitize('http://map.baidu.com/?poi=abc'))->toBeNull();
});

it('拒绝白名单外的域名', function () {
    expect(MapEmbed::sanitize('https://evil.example/map'))->toBeNull();
});

it('拒绝伪装成白名单子域的域名', function () {
    // 若判据是「以 map.baidu.com 结尾」，下面这条会被放行
    expect(MapEmbed::sanitize('https://map.baidu.com.evil.example/x'))->toBeNull()
        ->and(MapEmbed::sanitize('https://evil-map.baidu.com.example/x'))->toBeNull();
});

it('拒绝白名单域名的子域', function () {
    // 精确匹配：没登记过的子域一律不放行，宿主要用就在 config 里加一行
    expect(MapEmbed::sanitize('https://sub.map.baidu.com/x'))->toBeNull();
});

it('拒绝伪协议与协议相对地址', function () {
    expect(MapEmbed::sanitize('javascript:alert(1)'))->toBeNull()
        ->and(MapEmbed::sanitize('data:text/html,<script>alert(1)</script>'))->toBeNull()
        ->and(MapEmbed::sanitize('//map.baidu.com/?poi=abc'))->toBeNull();
});

it('拒绝含控制字符的地址', function () {
    expect(MapEmbed::sanitize("https://map.baidu.com/\x00.evil.example"))->toBeNull()
        ->and(MapEmbed::sanitize("https://map.\nbaidu.com/x"))->toBeNull();
});

it('空值与纯空白返回 null', function () {
    expect(MapEmbed::sanitize(null))->toBeNull()
        ->and(MapEmbed::sanitize(''))->toBeNull()
        ->and(MapEmbed::sanitize('   '))->toBeNull();
});

it('白名单归一化为小写并去重去空', function () {
    config(['filamentboot-site.map.allowed_hosts' => ['Map.Baidu.com', 'map.baidu.com', '  ', 'uri.amap.com']]);

    expect(MapEmbed::allowedHosts())->toBe(['map.baidu.com', 'uri.amap.com']);
});

it('白名单为空时任何地址都不放行', function () {
    config(['filamentboot-site.map.allowed_hosts' => []]);

    expect(MapEmbed::sanitize('https://map.baidu.com/?poi=abc'))->toBeNull();
});

// ---------------------------------------------------------------------------
// 区块规则：保存时就拦住，不要等渲染时静默不显示
// ---------------------------------------------------------------------------

it('地图区块拒绝白名单外的嵌入地址', function () {
    $errors = (new MapBlock)->validate([
        'embed_url' => 'https://evil.example/map',
        'height'    => 420,
    ]);

    expect($errors)->toHaveKey('embed_url');
});

it('地图区块接受白名单内的嵌入地址', function () {
    $errors = (new MapBlock)->validate([
        'embed_url' => 'https://map.baidu.com/?poi=abc',
        'address'   => '武汉市洪山区某路 1 号',
        'height'    => 420,
    ]);

    expect($errors)->toBe([]);
});

it('地图区块的嵌入地址必填、高度限定档位', function () {
    $block = new MapBlock;

    expect($block->validate(['height' => 420]))->toHaveKey('embed_url')
        ->and($block->validate(['embed_url' => 'https://map.baidu.com/x', 'height' => 9999]))
        ->toHaveKey('height');
});

it('地图区块默认值补齐历史 payload', function () {
    $filled = (new MapBlock)->withDefaults(['embed_url' => 'https://map.baidu.com/x']);

    expect($filled)->toHaveKeys(['title', 'embed_url', 'address', 'height'])
        ->and($filled['height'])->toBe(420);
});
