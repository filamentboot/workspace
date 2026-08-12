<?php

use Filamentboot\FilamentbootSite\Support\RichText;

/**
 * 前台富文本净化测试（Support\RichText）
 *
 * 这层的失效方式全是静默的：白名单收窄一格，后台排好的版在前台就少一层结构，
 * 没有异常、没有日志，只有页面变丑。所以两个方向都要钉死——
 * 编辑器能产出的标签必须活下来，脚本类注入必须死掉。
 *
 * @group site
 */

/**
 * Filament RichEditor 默认工具栏（getDefaultToolbarButtons()）能产出的全部结构，
 * 外加 TipTap 实际吐出的属性形态（列表项内套 <p>、单元格带 colspan/rowspan）。
 */
function richEditorSample(): string
{
    return <<<'HTML'
        <h2>二级标题</h2>
        <h3>三级标题</h3>
        <p style="text-align: center">居中 <strong>粗</strong> <em>斜</em> <u>下划线</u> <s>删除</s> H<sub>2</sub>O x<sup>2</sup></p>
        <p><a href="https://example.com">链接</a></p>
        <blockquote><p>引用</p></blockquote>
        <pre><code>php artisan migrate</code></pre>
        <ul><li><p>无序项</p></li></ul>
        <ol><li><p>有序项</p></li></ol>
        <table><tbody><tr><th colspan="1" rowspan="1"><p>表头</p></th><td colspan="2" rowspan="3"><p>合并单元格</p></td></tr></tbody></table>
        <p><img src="/storage/site/x.jpg" alt="图" width="800" height="600"></p>
        <hr>
        <p>末段 <span style="color: rgb(255, 0, 0)">红字</span></p>
        HTML;
}

/**
 * 编辑器排的版必须活着到前台
 *
 * 之前前台走的是 purifier 的 default 画像，实测这一串里 h2/h3/s/sub/sup/
 * blockquote/pre/code/hr 全被剥掉，table 更是整张塌成一串并列的 <p>。
 */
it('编辑器默认工具栏产出的标签全部保留', function () {
    $clean = RichText::purify(richEditorSample());

    foreach ([
        '<h2>', '<h3>', '<s>', '<sub>', '<sup>', '<u>', '<strong>', '<em>',
        '<blockquote>', '<pre>', '<code>', '<ul>', '<ol>', '<li>',
        '<table>', '<tbody>', '<tr>', '<th', '<td', '<hr', '<img', '<a href=',
    ] as $tag) {
        // 用 PHPUnit 断言而非 expect()->toContain()：后者第二个参数是「再一个 needle」
        // 而不是失败说明，一次挂掉根本看不出是哪个标签没了
        $this->assertStringContainsString($tag, $clean, "白名单吃掉了 {$tag}");
    }

    // 合并单元格的跨度不能丢，否则表格结构错位
    expect($clean)->toContain('colspan="2"')
        ->and($clean)->toContain('rowspan="3"')
        // 对齐靠 p 的行内 style，CSS.AllowedProperties 必须放行 text-align
        ->and($clean)->toContain('text-align:center')
        ->and($clean)->toContain('color:rgb(255,0,0)');
});

/**
 * 放宽白名单不能放宽 XSS 防护（T-10-05-01）
 */
it('脚本与事件属性一律剥离', function () {
    $clean = RichText::purify(
        '<p onclick="evil()">正文</p>'
        .'<script>alert(1)</script>'
        .'<iframe src="//evil.example"></iframe>'
        .'<a href="javascript:alert(1)">js 链接</a>'
        .'<img src=x onerror=alert(1)>'
        .'<object data="//evil.example"></object>'
        .'<svg onload="alert(1)"></svg>'
        .'<form action="//evil.example"><input name="pwd"></form>'
    );

    expect($clean)->toContain('正文')
        ->and($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('<iframe')
        ->and($clean)->not->toContain('<object')
        ->and($clean)->not->toContain('<svg')
        ->and($clean)->not->toContain('<form')
        ->and($clean)->not->toContain('<input')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onerror')
        ->and($clean)->not->toContain('onload')
        ->and($clean)->not->toContain('javascript:');
});

it('空正文返回空串而不是走一遍过滤', function () {
    expect(RichText::purify(null))->toBe('')
        ->and(RichText::purify(''))->toBe('');
});

/**
 * 宿主接管过滤策略的逃生口
 *
 * 配上画像名后，包内白名单必须整体让位——不然宿主以为收紧了，实际没生效。
 */
it('配置了画像名时改用宿主的 purifier 画像', function () {
    config()->set('purifier.settings.site_test_profile', [
        'HTML.Allowed'             => 'p',
        'AutoFormat.AutoParagraph' => false,
    ]);
    config()->set('filamentboot-site.purifier_profile', 'site_test_profile');

    $clean = RichText::purify('<h2>标题</h2><p>正文</p>');

    expect($clean)->toContain('<p>正文</p>')
        ->and($clean)->not->toContain('<h2>');
});

/**
 * 详情页把正文包在 .prose 里，样式全靠这个类
 *
 * 项目没装 @tailwindcss/typography，.prose 定义原本两套主题各写一份；
 * 七期批次 1 起 token / 语义类 / .prose 合并进共享的 shared.css，
 * decoration.css / software.css 各自只剩 `@import './shared.css'`。
 * 这里跟着入口文件的 @import 走一层，而不是硬编码直接读 shared.css——
 * 万一将来某个主题真的分岔出自己的 .prose（不再 @import 共享文件），
 * 这条测试要能测到它自己的入口文件，不能变成只测 shared.css 那一份。
 *
 * 漏了任何一套，那个主题的富文本就退回 preflight 后的裸样式：
 * 标题与正文等大、列表没符号、段落无间距。
 */
it('两套主题都定义了 .prose 富文本样式', function (string $theme) {
    $themeDir = base_path('vendor/filamentboot/filamentboot-site/resources/css/themes');
    $css      = file_get_contents("{$themeDir}/{$theme}.css");

    // 入口文件里的相对 @import 展开一层（当前只有这一层嵌套）
    if (preg_match_all("/@import\s+['\"](\.\/[^'\"]+)['\"]/", $css, $matches)) {
        foreach ($matches[1] as $importPath) {
            $css .= file_get_contents($themeDir.'/'.basename($importPath));
        }
    }

    expect($css)->toContain('.prose');

    // 逐条对着 RichText 白名单里需要样式兜底的标签查
    foreach (['h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'table', 'th', 'td', 'hr', 'sup', 'sub'] as $tag) {
        $this->assertStringContainsString(".prose {$tag}", $css, "{$theme} 主题缺少 .prose {$tag} 的样式");
    }
})->with(['decoration', 'software']);
