<?php

namespace LaravelStack\FilamentAdminWangEditor\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * wangEditor 包元信息测试
 *
 * 锁死 packages/filament-admin-wang-editor/composer.json 的关键字段：
 * - extra.filament-admin.slug 与 getId() 保持一致（D-09-03）
 * - extra.laravel.providers 包含 WangEditorServiceProvider
 * - extra.filament-admin 含完整插件契约（plugin:scan 可扫描，D-09-04）
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
     * 验证包名称正确
     */
    public function test_package_name(): void
    {
        self::assertSame('laravelstack/filament-admin-wang-editor', $this->composer['name']);
    }

    /**
     * 验证 extra.filament-admin 包含必填字段且 slug 值正确（D-09-03/D-09-04）
     *
     * plugin:scan 依赖这些字段发现并索引插件，slug 必须与 WangEditorPlugin::getId() 一致。
     */
    public function test_extra_filament_admin_contract(): void
    {
        $meta = $this->composer['extra']['filament-admin'];

        self::assertSame('filament-admin-wang-editor', $meta['slug']);
        self::assertNotEmpty($meta['plugin_class'], 'plugin_class 不得为空');
        self::assertNotEmpty($meta['service_provider'], 'service_provider 不得为空');
        self::assertSame('package', $meta['type']);
    }

    /**
     * 验证 extra.laravel.providers 包含 WangEditorServiceProvider（自动包发现）
     */
    public function test_service_provider_registered(): void
    {
        $providers = $this->composer['extra']['laravel']['providers'];

        self::assertContains(
            'LaravelStack\\FilamentAdminWangEditor\\WangEditorServiceProvider',
            $providers,
        );
    }
}
