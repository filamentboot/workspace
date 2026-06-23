<?php

use App\Services\PluginManager;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filamentboot\FilamentbootMarkdownEditor\Forms\MarkdownEditorField;
use Filamentboot\FilamentbootMarkdownEditor\MarkdownEditorPlugin;
use Filamentboot\FilamentbootMarkdownEditor\MarkdownEditorServiceProvider;
use Filamentboot\FilamentbootMarkdownEditor\Support\MarkdownRenderer;
use Filamentboot\FilamentbootRichEditor\Forms\RichEditorField;
use Filamentboot\FilamentbootRichEditor\RichEditorPlugin;
use Filamentboot\FilamentbootRichEditor\RichEditorServiceProvider;
use Filamentboot\FilamentbootRichEditor\Support\RichEditorPurifier;
use Filamentboot\FilamentbootWangEditor\Forms\Components\WangEditorField;
use Filamentboot\FilamentbootWangEditor\WangEditorPlugin;
use Filamentboot\FilamentbootWangEditor\WangEditorServiceProvider;
use Filamentboot\Models\Plugin;

/**
 * 编辑器插件集成测试（EDITOR-01/02 集成闭环）
 *
 * 覆盖场景：
 * 1. 三编辑器包 ServiceProvider 与 Plugin 类存在（包发现验证）
 * 2. RichEditorField / WangEditorField / MarkdownEditorField 可实例化
 * 3. MarkdownRenderer 渲染并过滤 XSS
 * 4. RichEditorPurifier 过滤富文本 script
 * 5. plugin:scan 发现三编辑器插件
 * 6. config/purifier.php 已发布并含 richeditor 白名单
 * 7. wangEditor 上传路由集成后可用（未认证拒绝）
 */
it('三编辑器包的 ServiceProvider 与 Plugin 类存在', function () {
    // ServiceProvider 类存在
    expect(class_exists(RichEditorServiceProvider::class))->toBeTrue(
        '期望 RichEditorServiceProvider 已加载'
    );
    expect(class_exists(MarkdownEditorServiceProvider::class))->toBeTrue(
        '期望 MarkdownEditorServiceProvider 已加载'
    );
    expect(class_exists(WangEditorServiceProvider::class))->toBeTrue(
        '期望 WangEditorServiceProvider 已加载'
    );

    // Plugin 类实现 Filament\Contracts\Plugin 接口
    expect(RichEditorPlugin::class)->toImplement(Filament\Contracts\Plugin::class);
    expect(MarkdownEditorPlugin::class)->toImplement(Filament\Contracts\Plugin::class);
    expect(WangEditorPlugin::class)->toImplement(Filament\Contracts\Plugin::class);
});

it('RichEditorField 与 WangEditorField 可实例化', function () {
    $richField = RichEditorField::make('content');
    $wangField = WangEditorField::make('content');

    expect($richField)->toBeInstanceOf(RichEditor::class);
    expect($wangField)->toBeInstanceOf(Field::class);
});

it('MarkdownEditorField 可实例化且继承内置 MarkdownEditor', function () {
    $field = MarkdownEditorField::make('content');

    expect($field)->toBeInstanceOf(MarkdownEditor::class);
});

it('MarkdownRenderer 渲染 Markdown 并过滤 XSS', function () {
    $renderer = app(MarkdownRenderer::class);

    $output = $renderer->render("# Hi\n<script>alert(1)</script>");

    expect($output)->toContain('<h1')
        ->and($output)->not->toContain('<script>');
});

it('RichEditorPurifier 过滤富文本 script', function () {
    $purifier = app(RichEditorPurifier::class);

    $output = $purifier->clean('<p>x<script>bad()</script></p>');

    expect($output)->not->toContain('<script>')
        ->and($output)->toContain('x');
});

it('plugin:scan 发现 rich-editor、markdown-editor、wang-editor 三个编辑器插件', function () {
    /** @var PluginManager $pluginManager */
    $pluginManager = app(PluginManager::class);

    $count = $pluginManager->syncFromInstalled();

    // 验证同步了至少 3 个编辑器插件
    expect($count)->toBeGreaterThanOrEqual(3);

    // 验证 rich-editor 插件记录已写入
    $richPlugin = Plugin::where('package_name', 'filamentboot/filamentboot-rich-editor')->first();
    expect($richPlugin)->not->toBeNull('期望 filamentboot-rich-editor 插件记录存在');
    expect($richPlugin->slug)->toBe('filamentboot-rich-editor');
    expect($richPlugin->plugin_class)->not->toBeEmpty('期望 plugin_class 字段非空');

    // 验证 markdown-editor 插件记录已写入
    $markdownPlugin = Plugin::where('package_name', 'filamentboot/filamentboot-markdown-editor')->first();
    expect($markdownPlugin)->not->toBeNull('期望 filamentboot-markdown-editor 插件记录存在');
    expect($markdownPlugin->slug)->toBe('filamentboot-markdown-editor');
    expect($markdownPlugin->plugin_class)->not->toBeEmpty('期望 plugin_class 字段非空');

    // 验证 wang-editor 插件记录已写入
    $wangPlugin = Plugin::where('package_name', 'filamentboot/filamentboot-wang-editor')->first();
    expect($wangPlugin)->not->toBeNull('期望 filamentboot-wang-editor 插件记录存在');
    expect($wangPlugin->slug)->toBe('filamentboot-wang-editor');
    expect($wangPlugin->plugin_class)->not->toBeEmpty('期望 plugin_class 字段非空');
});

it('config/purifier.php 已发布且含 richeditor 白名单', function () {
    $richeditorConfig = config('purifier.settings.richeditor');

    expect($richeditorConfig)->not->toBeNull('期望 purifier.settings.richeditor 配置段存在');
    expect($richeditorConfig['HTML.Allowed'])->toContain('h1');
});

it('wangEditor 上传路由已注册（路由名称可解析）', function () {
    // 验证路由名称已注册（不依赖 HTTP 请求，避免测试环境 session/Redis 差异）
    // 详细 HTTP 集成测试见 tests/Feature/Editor/WangEditorUploadTest.php
    expect(route('filamentboot-wang-editor.upload'))->not->toBeEmpty()
        ->and(route('filamentboot-wang-editor.upload'))->toContain('filamentboot-wang-editor/upload');
});
