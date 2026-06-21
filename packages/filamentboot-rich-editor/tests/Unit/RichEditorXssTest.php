<?php

namespace Filamentboot\FilamentbootRichEditor\Tests\Unit;

use Filamentboot\FilamentbootRichEditor\RichEditorServiceProvider;
use Filamentboot\FilamentbootRichEditor\Support\RichEditorPurifier;
use Mews\Purifier\PurifierServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * RichEditorPurifier XSS 过滤测试
 *
 * 验证（D-09-10）：
 * 1. clean() 移除 <script> 标签（XSS 防护）
 * 2. clean() 保留 style 属性（Pitfall 4，需白名单配置）
 */
class RichEditorXssTest extends TestCase
{
    /**
     * 注册包服务提供者（含 mews/purifier）
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PurifierServiceProvider::class,
            RichEditorServiceProvider::class,
        ];
    }

    /**
     * 配置允许 style 属性的 purifier 白名单（Pitfall 4 修复）
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // 允许 span[style] 和 p[style]，防止 Tiptap 对齐/颜色样式被过滤
        $app['config']->set('purifier.settings.default.HTML.Allowed', implode(',', [
            'p[style]',
            'span[style]',
            'b',
            'i',
            'u',
            'a[href|target]',
            'ul',
            'ol',
            'li',
            'br',
            'h1',
            'h2',
            'h3',
            'blockquote',
            'pre',
            'code',
        ]));
        $app['config']->set('purifier.settings.default.CSS.AllowedProperties', 'color,text-align,font-size,font-weight');
    }

    /**
     * 验证 clean() 移除 <script> 标签
     *
     * T-09-01：富文本 HTML 内容必须过 XSS 过滤，禁止直接 {!! $html !!}。
     */
    public function test_clean_removes_script_tags(): void
    {
        $purifier = app(RichEditorPurifier::class);
        $result = $purifier->clean('<p>hi<script>alert(1)</script></p>');

        self::assertFalse(
            str_contains($result, '<script>'),
            '过滤后不应包含 <script> 标签',
        );
        self::assertStringContainsString('hi', $result, '过滤后应保留文本内容');
    }

    /**
     * 验证 clean() 保留 style 属性（Pitfall 4 修复）
     *
     * mews/purifier 默认配置会删除 style 属性，
     * 需在 config/purifier.php 或测试中配置白名单允许 p[style]/span[style]。
     */
    public function test_clean_preserves_style_attribute(): void
    {
        $purifier = app(RichEditorPurifier::class);
        $result = $purifier->clean('<p style="color:red">x</p>');

        self::assertStringContainsString(
            'style',
            $result,
            '配置白名单后 style 属性应被保留',
        );
    }
}
