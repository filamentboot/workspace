<?php

namespace FilamentAdmin\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PackageBoundaryTest extends TestCase
{
    public function test_composer_json_uses_publishable_package_metadata(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('laravelstack/filament-admin', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertArrayNotHasKey('repositories', $composer);
        self::assertArrayNotHasKey('filament-admin/plugin-platform', $composer['require']);
        self::assertNotSame('The skeleton application for the Laravel framework.', $composer['description']);
        self::assertArrayHasKey('homepage', $composer);
        self::assertArrayHasKey('support', $composer);
        self::assertArrayHasKey('authors', $composer);
    }

    public function test_runtime_files_do_not_reference_plugin_platform_namespace(): void
    {
        $files = [
            __DIR__.'/../../src/FilamentAdminServiceProvider.php',
            __DIR__.'/../../src/Services/AdminNavigationBuilder.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents($file);

            self::assertIsString($source);
            self::assertStringNotContainsString('FilamentAdmin\\PluginPlatform', $source, $file);
            self::assertStringNotContainsString('packages/plugin-platform', $source, $file);
        }
    }

    /**
     * COMPLY-06: 确认根目录 /src/ 孤儿已删除，包代码统一在 packages/filament-admin/src/
     */
    public function test_root_src_orphan_directory_does_not_exist(): void
    {
        $rootSrc = dirname(__DIR__, 4) . '/src';
        self::assertFalse(is_dir($rootSrc), '根目录 /src/ 仍然存在，应删除（COMPLY-06）');
    }
}
