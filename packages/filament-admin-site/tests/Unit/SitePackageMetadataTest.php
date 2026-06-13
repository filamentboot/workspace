<?php

namespace LaravelStack\FilamentAdminSite\Tests\Unit;

use LaravelStack\FilamentAdminSite\Settings\SiteSettings;
use LaravelStack\FilamentAdminSite\SitePlugin;
use LaravelStack\FilamentAdminSite\SiteServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * 官网插件包元数据测试（SitePackageMetadataTest）
 *
 * 锁死 packages/filament-admin-site/composer.json 的关键字段：
 * - extra.filament-admin.slug 与 SitePlugin::getId() 保持一致（D-10-01）
 * - extra.laravel.providers 包含 SiteServiceProvider（自动包发现）
 * - extra.filament-admin 含完整插件契约（plugin:scan 可扫描）
 *
 * 本测试在 Plan 10-01 交付后立即转绿（不含 markTestIncomplete）。
 */
class SitePackageMetadataTest extends TestCase
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
        self::assertSame('laravelstack/filament-admin-site', $this->composer['name']);
    }

    /**
     * 验证 extra.filament-admin 含完整契约字段且 slug 值正确
     *
     * plugin:scan 依赖这些字段发现并索引插件，
     * slug 必须与 SitePlugin::getId() 返回值一致（D-10-01）。
     */
    public function test_extra_filament_admin_contract(): void
    {
        $meta = $this->composer['extra']['filament-admin'];

        self::assertSame('filament-admin-site', $meta['slug']);
        self::assertNotEmpty($meta['plugin_class'], 'plugin_class 不得为空');
        self::assertSame('LaravelStack\\FilamentAdminSite\\SitePlugin', $meta['plugin_class']);
        self::assertNotEmpty($meta['service_provider'], 'service_provider 不得为空');
        self::assertSame('LaravelStack\\FilamentAdminSite\\SiteServiceProvider', $meta['service_provider']);
        self::assertSame('solution_plugin', $meta['type']);
        self::assertContains('laravelstack/filament-admin', $meta['requires']);
    }

    /**
     * 验证 extra.laravel.providers 包含 SiteServiceProvider（自动包发现）
     */
    public function test_service_provider_registered(): void
    {
        $providers = $this->composer['extra']['laravel']['providers'];

        self::assertContains(
            'LaravelStack\\FilamentAdminSite\\SiteServiceProvider',
            $providers,
        );
    }

    /**
     * 验证 SitePlugin 类存在且 getId() 返回正确 slug
     */
    public function test_site_plugin_class_exists_and_returns_correct_id(): void
    {
        self::assertTrue(class_exists(SitePlugin::class), 'SitePlugin 类应存在');

        $plugin = new SitePlugin();
        self::assertSame('filament-admin-site', $plugin->getId());
    }

    /**
     * 验证 SiteSettings 类存在且 group() 返回 'site'
     */
    public function test_site_settings_class_exists_and_returns_correct_group(): void
    {
        self::assertTrue(class_exists(SiteSettings::class), 'SiteSettings 类应存在');
        self::assertSame('site', SiteSettings::group());
    }
}
