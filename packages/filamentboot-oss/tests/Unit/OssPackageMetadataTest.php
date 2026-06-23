<?php

namespace Filamentboot\FilamentbootOss\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * OSS 包元信息测试
 *
 * 锁死 packages/filament-admin-oss/composer.json 的关键字段。
 */
class OssPackageMetadataTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $composer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = json_decode(
            (string) file_get_contents(__DIR__.'/../../composer.json'),
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
        self::assertSame('filamentboot-oss', $meta['slug']);
    }

    /**
     * 验证 extra.laravel.providers 包含 OssServiceProvider
     */
    public function test_laravel_provider_is_declared(): void
    {
        $providers = $this->composer['extra']['laravel']['providers'];

        self::assertContains(
            'Filamentboot\\FilamentbootOss\\OssServiceProvider',
            $providers,
        );
    }
}
