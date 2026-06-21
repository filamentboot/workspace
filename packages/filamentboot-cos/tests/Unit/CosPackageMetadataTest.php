<?php

namespace Filamentboot\FilamentbootCos\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * COS 插件包 composer.json 元信息锁定测试
 *
 * 确保 extra.filament-admin 契约字段和 extra.laravel.providers 声明不会意外丢失。
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
        $filamentAdmin = $this->composer['extra']['filamentboot'];

        self::assertSame('filamentboot-cos', $filamentAdmin['slug']);
        self::assertArrayHasKey('name', $filamentAdmin);
        self::assertArrayHasKey('plugin_class', $filamentAdmin);
        self::assertArrayHasKey('service_provider', $filamentAdmin);
        self::assertStringContainsString('CosPlugin', $filamentAdmin['plugin_class']);
        self::assertStringContainsString('CosServiceProvider', $filamentAdmin['service_provider']);
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
