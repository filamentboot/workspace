<?php

use Filamentboot\FilamentbootSite\Support\SafeUrl;

/**
 * 链接 scheme 白名单测试（#13）
 *
 * 覆盖场景：
 * - 放行站内路径、锚点、http(s)、tel、mailto
 * - 拦下伪协议（javascript / data / vbscript / file）与大小写混淆
 * - 拦下协议相对 URL（//evil.com）与裸相对路径
 * - 拦下控制字符混淆
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Support\SafeUrl
 */

/**
 * 白名单内的链接原样放行
 */
it('放行白名单内的链接', function (string $url) {
    expect(SafeUrl::sanitize($url))->toBe($url);
})->with([
    '站内绝对路径'         => ['/contact'],
    '带查询串的站内路径'   => ['/products?category=lighting'],
    '页内锚点'             => ['#section-faq'],
    'http 外链'            => ['http://example.com/a'],
    'https 外链'           => ['https://example.com/a?b=c#d'],
    '电话'                 => ['tel:+8613800138000'],
    '邮件'                 => ['mailto:hello@example.com'],
    '大写 scheme 同样放行' => ['HTTPS://example.com'],
]);

/**
 * 伪协议与其它 scheme 一律返回 null
 *
 * 返回 null 而不是降级成 '#'：调用方据此不渲染按钮，
 * 给访客一个点了没反应的按钮比不显示更糟。
 */
it('拦下白名单外的链接', function (?string $url) {
    expect(SafeUrl::sanitize($url))->toBeNull();
})->with([
    'javascript 伪协议'     => ['javascript:alert(1)'],
    '大写混淆的 javascript' => ['JavaScript:alert(1)'],
    '前后空格混淆'          => ['  javascript:alert(1)  '],
    'data URI'              => ['data:text/html,<script>alert(1)</script>'],
    'vbscript 伪协议'       => ['vbscript:msgbox(1)'],
    '本地文件'              => ['file:///etc/passwd'],
    'ftp'                   => ['ftp://example.com'],
    '协议相对 URL'          => ['//evil.com/phish'],
    '裸相对路径'            => ['about'],
    '空串'                  => [''],
    '纯空格'                => ['   '],
    'null'                  => [null],
]);

/**
 * 控制字符混淆的伪协议被拦下
 *
 * 浏览器会忽略 URL 里的控制字符，`java\0script:` 靠它绕过朴素的前缀比对。
 */
it('拦下含控制字符的链接', function () {
    expect(SafeUrl::sanitize("java\0script:alert(1)"))->toBeNull()
        ->and(SafeUrl::sanitize("java\tscript:alert(1)"))->toBeNull()
        ->and(SafeUrl::sanitize("java\nscript:alert(1)"))->toBeNull();
});
