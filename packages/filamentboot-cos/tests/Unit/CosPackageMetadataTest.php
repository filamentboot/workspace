<?php

namespace Filamentboot\FilamentbootCos\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * COS 插件包 composer.json 元信息锁定测试
 *
 * 确保 extra.filamentboot 契约字段和 extra.laravel.providers 声明不会意外丢失。
 */
class CosPackageMetadataTest extends TestCase
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
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * 验证 extra.filamentboot 含 slug/name/plugin_class/service_provider 且 slug 正确
     */
    public function test_extra_filament_admin_has_required_fields(): void
    {
        $Filamentboot = $this->composer['extra']['filamentboot'];

        self::assertSame('filamentboot-cos', $Filamentboot['slug']);
        self::assertArrayHasKey('name', $Filamentboot);
        self::assertArrayHasKey('plugin_class', $Filamentboot);
        self::assertArrayHasKey('service_provider', $Filamentboot);
        self::assertStringContainsString('CosPlugin', $Filamentboot['plugin_class']);
        self::assertStringContainsString('CosServiceProvider', $Filamentboot['service_provider']);
    }

    /**
     * 验证 extra.laravel.providers 包含 CosServiceProvider
     */
    public function test_laravel_provider_is_declared(): void
    {
        $providers = $this->composer['extra']['laravel']['providers'];

        self::assertContains(
            'Filamentboot\\FilamentbootCos\\CosServiceProvider',
            $providers
        );
    }
}
