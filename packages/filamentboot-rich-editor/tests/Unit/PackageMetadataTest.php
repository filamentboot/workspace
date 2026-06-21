<?php

namespace Filamentboot\FilamentbootRichEditor\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 富文本编辑器包元信息测试
 *
 * 锁死 packages/filamentboot-rich-editor/composer.json 的关键字段：
 * - extra.filamentboot.slug 与 getId() 保持一致
 * - extra.laravel.providers 包含 RichEditorServiceProvider
 * - require 含 mews/purifier（XSS 过滤依赖）
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
     * 验证 extra.filamentboot 包含必填字段且 slug 值正确
     */
    public function test_extra_filament_admin_has_required_fields(): void
    {
        $meta = $this->composer['extra']['filamentboot'];

        self::assertArrayHasKey('slug', $meta);
        self::assertArrayHasKey('name', $meta);
        self::assertArrayHasKey('plugin_class', $meta);
        self::assertArrayHasKey('service_provider', $meta);
        self::assertSame('filamentboot-rich-editor', $meta['slug']);
    }

    /**
     * 验证 plugin_class 指向 RichEditorPlugin
     */
    public function test_plugin_class_is_rich_editor_plugin(): void
    {
        $meta = $this->composer['extra']['filamentboot'];

        self::assertStringContainsString(
            'RichEditorPlugin',
            $meta['plugin_class'],
        );
    }

    /**
     * 验证 extra.laravel.providers 包含 RichEditorServiceProvider
     */
    public function test_laravel_provider_is_declared(): void
    {
        $providers = $this->composer['extra']['laravel']['providers'];

        self::assertContains(
            'Filamentboot\\FilamentbootRichEditor\\RichEditorServiceProvider',
            $providers,
        );
    }

    /**
     * 验证 require 段包含 mews/purifier（XSS 过滤核心依赖）
     */
    public function test_require_contains_mews_purifier(): void
    {
        $require = $this->composer['require'];

        self::assertArrayHasKey('mews/purifier', $require);
    }

    /**
     * 验证 require 段不包含 iidestiny/flysystem-oss（不是 OSS 包）
     */
    public function test_require_does_not_contain_flysystem_oss(): void
    {
        $require = $this->composer['require'];

        self::assertArrayNotHasKey('iidestiny/flysystem-oss', $require);
    }
}
