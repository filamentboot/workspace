<?php

use Filament\Contracts\Plugin;

/**
 * 一方插件合规审查测试（MKTPLACE-09）
 *
 * 覆盖场景：
 * 1. 六个一方插件类均实现 Filament\Contracts\Plugin 接口
 * 2. 每个插件的 composer.json 含 extra.filamentboot.post_install 块（Wave 3 补充后绿）
 *
 * 注意：post_install 块检查在 Wave 0 阶段为 markTestIncomplete（当前均缺失），
 * Wave 3 修复后改为真实断言。
 *
 * 威胁缓解：T-12-00-02 — 纯本地文件检查，无网络调用，无真实 composer 执行。
 * RESEARCH First-Party Plugin Compliance 章节。
 */

/**
 * 一方插件类清单（6 个包）
 *
 * @return array<array{string, string, string}>
 */
function firstPartyPlugins(): array
{
    return [
        ['filamentboot-oss',               'Filamentboot\\FilamentbootOss\\OssPlugin',                     'packages/filamentboot-oss'],
        ['filamentboot-cos',               'Filamentboot\\FilamentbootCos\\CosPlugin',                     'packages/filamentboot-cos'],
        ['filamentboot-rich-editor',       'Filamentboot\\FilamentbootRichEditor\\RichEditorPlugin',       'packages/filamentboot-rich-editor'],
        ['filamentboot-markdown-editor',   'Filamentboot\\FilamentbootMarkdownEditor\\MarkdownEditorPlugin', 'packages/filamentboot-markdown-editor'],
        ['filamentboot-wang-editor',       'Filamentboot\\FilamentbootWangEditor\\WangEditorPlugin',       'packages/filamentboot-wang-editor'],
        ['filamentboot-site',            'Filamentboot\\FilamentbootSite\\SitePlugin',                   'packages/filamentboot-site'],
    ];
}

it('OssPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootOss\\OssPlugin';
    expect(class_exists($class))->toBeTrue('OssPlugin 类应可被 autoload 到');
    expect(is_a($class, Plugin::class, true))->toBeTrue('OssPlugin 应实现 Filament\\Contracts\\Plugin');
});

it('CosPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootCos\\CosPlugin';
    expect(class_exists($class))->toBeTrue('CosPlugin 类应可被 autoload 到');
    expect(is_a($class, Plugin::class, true))->toBeTrue('CosPlugin 应实现 Filament\\Contracts\\Plugin');
});

it('RichEditorPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootRichEditor\\RichEditorPlugin';
    expect(class_exists($class))->toBeTrue('RichEditorPlugin 类应可被 autoload 到');
    expect(is_a($class, Plugin::class, true))->toBeTrue('RichEditorPlugin 应实现 Filament\\Contracts\\Plugin');
});

it('MarkdownEditorPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootMarkdownEditor\\MarkdownEditorPlugin';
    expect(class_exists($class))->toBeTrue('MarkdownEditorPlugin 类应可被 autoload 到');
    expect(is_a($class, Plugin::class, true))->toBeTrue('MarkdownEditorPlugin 应实现 Filament\\Contracts\\Plugin');
});

it('WangEditorPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootWangEditor\\WangEditorPlugin';
    expect(class_exists($class))->toBeTrue('WangEditorPlugin 类应可被 autoload 到');
    expect(is_a($class, Plugin::class, true))->toBeTrue('WangEditorPlugin 应实现 Filament\\Contracts\\Plugin');
});

it('SitePlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootSite\\SitePlugin';
    expect(class_exists($class))->toBeTrue('SitePlugin 类应可被 autoload 到');
    expect(is_a($class, Plugin::class, true))->toBeTrue('SitePlugin 应实现 Filament\\Contracts\\Plugin');
});

it('filamentboot-oss 的 composer.json 含 post_install 块（MKTPLACE-09）', function () {
    $composerJson = json_decode(
        file_get_contents(base_path('packages/filamentboot-oss/composer.json')),
        true
    );

    expect($composerJson['extra']['filamentboot'])->toHaveKey('post_install');
    expect($composerJson['extra']['filamentboot']['post_install'])->toHaveKey('publish_tags');
});

it('filamentboot-cos 的 composer.json 含 post_install 块（MKTPLACE-09）', function () {
    $composerJson = json_decode(
        file_get_contents(base_path('packages/filamentboot-cos/composer.json')),
        true
    );

    expect($composerJson['extra']['filamentboot'])->toHaveKey('post_install');
    expect($composerJson['extra']['filamentboot']['post_install'])->toHaveKey('publish_tags');
});

it('filamentboot-site 的 composer.json 含 post_install 含 run_migrations（MKTPLACE-09）', function () {
    $composerJson = json_decode(
        file_get_contents(base_path('packages/filamentboot-site/composer.json')),
        true
    );

    $postInstall = $composerJson['extra']['filamentboot']['post_install'] ?? [];

    expect($postInstall)->toHaveKey('publish_tags');
    expect($postInstall)->toHaveKey('run_migrations');
    expect($postInstall['run_migrations'])->toBeTrue();
});
