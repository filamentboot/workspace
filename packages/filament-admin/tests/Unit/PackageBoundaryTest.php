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
}
