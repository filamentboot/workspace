<?php

namespace Filamentboot\FilamentbootMarkdownEditor\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Markdown 编辑器包元信息测试
 *
 * 锁死 packages/filamentboot-markdown-editor/composer.json 的关键字段，
 * 确保插件市场契约（extra.filamentboot）完整有效。
 */
class PackageMetadataTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = json_decode(
            (string) file_get_contents(__DIR__ . '/../../composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * 验证 extra.filamentboot 含必填字段，slug 值为 filamentboot-markdown-editor
     */
    public function test_extra_filament_admin_has_required_fields(): void
    {
        $meta = $this->composer['extra']['filamentboot'];

        self::assertArrayHasKey('slug', $meta);
        self::assertArrayHasKey('name', $meta);
        self::assertArrayHasKey('plugin_class', $meta);
        self::assertArrayHasKey('service_provider', $meta);
        self::assertSame('filamentboot-markdown-editor', $meta['slug']);
    }

    /**
     * 验证 plugin_class 指向 MarkdownEditorPlugin
     */
    public function test_plugin_class_is_declared(): void
    {
        $meta = $this->composer['extra']['filamentboot'];

        self::assertStringContainsString(
            'MarkdownEditorPlugin',
            $meta['plugin_class'],
        );
    }

    /**
     * 验证 extra.laravel.providers 包含 MarkdownEditorServiceProvider
     */
    public function test_laravel_provider_is_declared(): void
    {
        $providers = $this->composer['extra']['laravel']['providers'];

        self::assertContains(
            'Filamentboot\\FilamentbootMarkdownEditor\\MarkdownEditorServiceProvider',
            $providers,
        );
    }

    /**
     * 验证 require 中包含 mews/purifier（XSS 过滤依赖，D-09-09）
     */
    public function test_mews_purifier_is_required(): void
    {
        self::assertArrayHasKey('mews/purifier', $this->composer['require']);
    }

    /**
     * 验证 require 中包含 league/commonmark（Markdown 渲染依赖，D-09-06）
     */
    public function test_league_commonmark_is_required(): void
    {
        self::assertArrayHasKey('league/commonmark', $this->composer['require']);
    }

    /**
     * 验证 composer.json 可正常解析（JSON 格式正确）
     */
    public function test_composer_json_is_valid(): void
    {
        self::assertIsArray($this->composer);
        self::assertArrayHasKey('name', $this->composer);
        self::assertSame('filamentboot/filamentboot-markdown-editor', $this->composer['name']);
    }
}
