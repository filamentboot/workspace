<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class DemoAppPackageBridgeTest extends TestCase
{
    public function test_root_composer_is_demo_project_and_requires_local_package(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../../composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('project', $composer['type']);
        self::assertArrayHasKey('repositories', $composer);
        self::assertSame('path', $composer['repositories'][0]['type']);
        self::assertSame('packages/filament-admin', $composer['repositories'][0]['url']);
        self::assertArrayHasKey('filament-admin/filament-admin', $composer['require']);
        self::assertArrayNotHasKey('FilamentAdmin\\', $composer['autoload']['psr-4'] ?? []);
    }

    public function test_package_directory_exists_with_package_composer_json(): void
    {
        self::assertFileExists(__DIR__.'/../../../packages/filament-admin/composer.json');

        $composer = json_decode((string) file_get_contents(__DIR__.'/../../../packages/filament-admin/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('filament-admin/filament-admin', $composer['name']);
        self::assertSame('library', $composer['type']);
    }
}
