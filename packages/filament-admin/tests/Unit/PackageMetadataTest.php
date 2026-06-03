<?php

namespace FilamentAdmin\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PackageMetadataTest extends TestCase
{
    public function test_package_composer_json_has_publishable_metadata(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('filament-admin/filament-admin', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertArrayHasKey('homepage', $composer);
        self::assertArrayHasKey('support', $composer);
        self::assertArrayNotHasKey('repositories', $composer);
    }
}
