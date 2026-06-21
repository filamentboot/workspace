<?php

use Filament\Contracts\Plugin;

/**
 * 一方插件合规审查测试（MKTPLACE-09）
 *
 * 覆盖场景：
 * 1. 六个一方插件类均实现 Filament\Contracts\Plugin 接口
 * 2. 每个插件的 composer.json 含 extra.filament-admin.post_install 块（Wave 3 补充后绿）
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
        ['filament-admin-rich-editor',     'LaravelStack\\FilamentAdminRichEditor\\RichEditorPlugin',       'packages/filament-admin-rich-editor'],
        ['filament-admin-markdown-editor', 'LaravelStack\\FilamentAdminMarkdownEditor\\MarkdownEditorPlugin', 'packages/filament-admin-markdown-editor'],
        ['filament-admin-wang-editor',     'LaravelStack\\FilamentAdminWangEditor\\WangEditorPlugin',       'packages/filament-admin-wang-editor'],
        ['filament-admin-site',            'LaravelStack\\FilamentAdminSite\\SitePlugin',                   'packages/filament-admin-site'],
    ];
}

it('OssPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootOss\\OssPlugin';
    expect(class_exists($class))->toBeTrue("OssPlugin 类应可被 autoload 到");
    expect(is_a($class, Plugin::class, true))->toBeTrue("OssPlugin 应实现 Filament\\Contracts\\Plugin");
});

it('CosPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'Filamentboot\\FilamentbootCos\\CosPlugin';
    expect(class_exists($class))->toBeTrue("CosPlugin 类应可被 autoload 到");
    expect(is_a($class, Plugin::class, true))->toBeTrue("CosPlugin 应实现 Filament\\Contracts\\Plugin");
});

it('RichEditorPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'LaravelStack\\FilamentAdminRichEditor\\RichEditorPlugin';
    expect(class_exists($class))->toBeTrue("RichEditorPlugin 类应可被 autoload 到");
    expect(is_a($class, Plugin::class, true))->toBeTrue("RichEditorPlugin 应实现 Filament\\Contracts\\Plugin");
});

it('MarkdownEditorPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'LaravelStack\\FilamentAdminMarkdownEditor\\MarkdownEditorPlugin';
    expect(class_exists($class))->toBeTrue("MarkdownEditorPlugin 类应可被 autoload 到");
    expect(is_a($class, Plugin::class, true))->toBeTrue("MarkdownEditorPlugin 应实现 Filament\\Contracts\\Plugin");
});

it('WangEditorPlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'LaravelStack\\FilamentAdminWangEditor\\WangEditorPlugin';
    expect(class_exists($class))->toBeTrue("WangEditorPlugin 类应可被 autoload 到");
    expect(is_a($class, Plugin::class, true))->toBeTrue("WangEditorPlugin 应实现 Filament\\Contracts\\Plugin");
});

it('SitePlugin 实现 Filament\\Contracts\\Plugin 接口（MKTPLACE-09）', function () {
    $class = 'LaravelStack\\FilamentAdminSite\\SitePlugin';
    expect(class_exists($class))->toBeTrue("SitePlugin 类应可被 autoload 到");
    expect(is_a($class, Plugin::class, true))->toBeTrue("SitePlugin 应实现 Filament\\Contracts\\Plugin");
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

it('filament-admin-site 的 composer.json 含 post_install 含 run_migrations（MKTPLACE-09）', function () {
    $composerJson = json_decode(
        file_get_contents(base_path('packages/filament-admin-site/composer.json')),
        true
    );

    $postInstall = $composerJson['extra']['filament-admin']['post_install'] ?? [];

    expect($postInstall)->toHaveKey('publish_tags');
    expect($postInstall)->toHaveKey('run_migrations');
    expect($postInstall['run_migrations'])->toBeTrue();
});
