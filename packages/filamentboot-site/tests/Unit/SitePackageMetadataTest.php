<?php

namespace Filamentboot\FilamentbootSite\Tests\Unit;

use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * 官网插件包元数据测试（SitePackageMetadataTest）
 *
 * 锁死 packages/filamentboot-site/composer.json 的关键字段：
 * - extra.filamentboot.slug 与 SitePlugin::getId() 保持一致（D-10-01）
 * - extra.laravel.providers 包含 SiteServiceProvider（自动包发现）
 * - extra.filamentboot 含完整插件契约（plugin:scan 可扫描）
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
            (string) file_get_contents(__DIR__.'/../../composer.json'),
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
        self::assertSame('filamentboot/filamentboot-site', $this->composer['name']);
    }

    /**
     * 验证 extra.filamentboot 含完整契约字段且 slug 值正确
     *
     * plugin:scan 依赖这些字段发现并索引插件，
     * slug 必须与 SitePlugin::getId() 返回值一致（D-10-01）。
     */
    public function test_extra_filament_admin_contract(): void
    {
        $meta = $this->composer['extra']['filamentboot'];

        self::assertSame('filamentboot-site', $meta['slug']);
        self::assertNotEmpty($meta['plugin_class'], 'plugin_class 不得为空');
        self::assertSame('Filamentboot\\FilamentbootSite\\SitePlugin', $meta['plugin_class']);
        self::assertNotEmpty($meta['service_provider'], 'service_provider 不得为空');
        self::assertSame('Filamentboot\\FilamentbootSite\\SiteServiceProvider', $meta['service_provider']);
        self::assertSame('solution_plugin', $meta['type']);
        self::assertContains('filamentboot/filamentboot', $meta['requires']);
    }

    /**
     * 验证 extra.laravel.providers 包含 SiteServiceProvider（自动包发现）
     */
    public function test_service_provider_registered(): void
    {
        $providers = $this->composer['extra']['laravel']['providers'];

        self::assertContains(
            'Filamentboot\\FilamentbootSite\\SiteServiceProvider',
            $providers,
        );
    }

    /**
     * 验证 SitePlugin 类存在且 getId() 返回正确 slug
     */
    public function test_site_plugin_class_exists_and_returns_correct_id(): void
    {
        self::assertTrue(class_exists(SitePlugin::class), 'SitePlugin 类应存在');

        $plugin = new SitePlugin;
        self::assertSame('filamentboot-site', $plugin->getId());
    }

    /**
     * 验证 SiteSettings 类存在且 group() 返回 'site'
     */
    public function test_site_settings_class_exists_and_returns_correct_group(): void
    {
        self::assertTrue(class_exists(SiteSettings::class), 'SiteSettings 类应存在');
        self::assertSame('site', SiteSettings::group());
    }

    /**
     * post_install 声明的每个 publish tag 都必须在 ServiceProvider 中真实注册
     *
     * 此前 composer.json 声明了 filamentboot-site-config，
     * 而 ServiceProvider 只注册了 -migrations / -views / -assets 三个 tag，
     * 安装后执行 vendor:publish --tag=filamentboot-site-config 静默无产出。
     */
    public function test_declared_publish_tags_are_registered_in_service_provider(): void
    {
        /** @var list<string> $tags */
        $tags = $this->composer['extra']['filamentboot']['post_install']['publish_tags'];

        $providerSource = (string) file_get_contents(__DIR__.'/../../src/SiteServiceProvider.php');

        foreach ($tags as $tag) {
            self::assertStringContainsString(
                "'".$tag."'",
                $providerSource,
                "composer.json 声明的 publish tag {$tag} 未在 SiteServiceProvider 中注册",
            );
        }
    }

    /**
     * Playwright 冒烟测试的 filamentboot-site-tests tag 已注册，但刻意不进
     * post_install.publish_tags 自动发布清单（批次 5）
     *
     * 与 filamentboot-site-views 同一个先例：真实存在的 tag，但要求 Node +
     * Playwright 才用得上，不该跟着每一次安装强行落地。这条测试锁的是这个
     * 设计决定本身——tag 必须存在（手动 vendor:publish 能用），但不能悄悄被加进
     * 自动发布清单里（一旦加进去，每次安装都会往下游项目根目录扔一批 .cjs 文件）。
     */
    public function test_e2e_tests_publish_tag_is_registered_but_not_auto_published(): void
    {
        $providerSource = (string) file_get_contents(__DIR__.'/../../src/SiteServiceProvider.php');

        self::assertStringContainsString(
            "'filamentboot-site-tests'",
            $providerSource,
            'filamentboot-site-tests tag 未在 SiteServiceProvider 中注册',
        );

        /** @var list<string> $autoPublished */
        $autoPublished = $this->composer['extra']['filamentboot']['post_install']['publish_tags'];

        self::assertNotContains(
            'filamentboot-site-tests',
            $autoPublished,
            'filamentboot-site-tests 不应进入 post_install.publish_tags 自动发布清单',
        );
    }

    /**
     * post_install 声明的 Seeder 类必须真实存在
     *
     * 此前声明的是 SiteSeeder，实际类名为 SiteDemoSeeder，
     * 插件市场执行初始化时会因类不存在而失败。
     */
    public function test_declared_seeders_exist(): void
    {
        /** @var list<string> $seeders */
        $seeders = $this->composer['extra']['filamentboot']['post_install']['seeders'];

        self::assertNotEmpty($seeders, 'post_install.seeders 不应为空');

        foreach ($seeders as $seeder) {
            self::assertTrue(
                class_exists($seeder),
                "composer.json 声明的 Seeder 类 {$seeder} 不存在",
            );
        }
    }

    /**
     * README 声明的内容表数量必须与迁移实际建表数一致
     *
     * 迁移文件数 ≠ 建表数：create_site_tags_tables 一个文件建了
     * site_tags 与 site_taggables 两张表，README 曾据文件数误写为 8 张。
     */
    public function test_readme_table_count_matches_migrations(): void
    {
        $migrationDir = __DIR__.'/../../database/migrations';
        $created      = 0;

        foreach (glob($migrationDir.'/*.php') ?: [] as $file) {
            $created += preg_match_all('/Schema::create\(/', (string) file_get_contents($file));
        }

        $readme = (string) file_get_contents(__DIR__.'/../../README.md');

        self::assertStringContainsString(
            "执行数据库迁移（{$created} 张内容表）",
            $readme,
            "README 中的内容表数量与迁移实际建表数（{$created}）不一致",
        );
    }

    /**
     * CMS v1 为中文单语言，包元数据不得再宣称双语能力
     */
    public function test_metadata_does_not_advertise_bilingual(): void
    {
        $encoded = json_encode($this->composer, JSON_UNESCAPED_UNICODE) ?: '';

        self::assertStringNotContainsString('bilingual', $encoded);
        self::assertStringNotContainsString('双语', $encoded);
    }
}
