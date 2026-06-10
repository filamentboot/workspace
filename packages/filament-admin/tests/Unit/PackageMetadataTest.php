<?php

namespace FilamentAdmin\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 锁死 packages/filament-admin/composer.json 的 COMPLY-03 字段。
 *
 * 每个 test_* 方法对应一个 D-12~D-15 子决策的精确字面量断言，
 * 确保后续重构时关键发布规范字段不会意外丢失。
 */
class PackageMetadataTest extends TestCase
{
    public function test_package_composer_json_has_publishable_metadata(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('laravelstack/filament-admin', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertArrayHasKey('homepage', $composer);
        self::assertArrayHasKey('support', $composer);
        self::assertArrayNotHasKey('repositories', $composer);
    }

    /**
     * D-12: authors.email 与 role。
     */
    public function test_composer_authors_has_email_and_role(): void
    {
        $composer = $this->loadComposerJson();

        self::assertSame('JasonTodd0521@gmail.com', $composer['authors'][0]['email']);
        self::assertSame('developer', $composer['authors'][0]['role']);
    }

    /**
     * D-13: support.docs 与 support.wiki 链接。
     */
    public function test_composer_support_has_docs_and_wiki(): void
    {
        $composer = $this->loadComposerJson();

        self::assertNotEmpty($composer['support']['docs']);
        self::assertNotEmpty($composer['support']['wiki']);
        self::assertStringStartsWith('https://github.com/john-captain/filament-admin', $composer['support']['docs']);
        self::assertStringStartsWith('https://github.com/john-captain/filament-admin', $composer['support']['wiki']);
    }

    /**
     * D-15: require-dev 包含质量工具链（larastan 与 pint）。
     */
    public function test_composer_require_dev_has_quality_tooling(): void
    {
        $composer = $this->loadComposerJson();

        self::assertArrayHasKey('larastan/larastan', $composer['require-dev']);
        self::assertArrayHasKey('laravel/pint', $composer['require-dev']);
        self::assertMatchesRegularExpression('/^\^?\d/', $composer['require-dev']['larastan/larastan']);
        self::assertMatchesRegularExpression('/^\^?\d/', $composer['require-dev']['laravel/pint']);
    }

    /**
     * D-15: scripts 段包含 test / test-coverage / phpstan / pint / pint:test 五条命令。
     */
    public function test_composer_scripts_define_test_phpstan_pint_commands(): void
    {
        $composer = $this->loadComposerJson();
        $scripts  = $composer['scripts'];

        foreach (['test', 'test-coverage', 'phpstan', 'pint', 'pint:test'] as $key) {
            self::assertArrayHasKey($key, $scripts);
            self::assertStringNotContainsString('artisan', $scripts[$key]);
        }
    }

    /**
     * D-15: extra.branch-alias.dev-main 字面量。
     */
    public function test_composer_extra_has_branch_alias_dev_main(): void
    {
        $composer = $this->loadComposerJson();

        self::assertSame('0.5.x-dev', $composer['extra']['branch-alias']['dev-main']);
    }

    /**
     * D-15: config.allow-plugins 与 config.sort-packages。
     */
    public function test_composer_config_allow_plugins_and_sort_packages(): void
    {
        $composer = $this->loadComposerJson();

        self::assertTrue($composer['config']['allow-plugins']['pestphp/pest-plugin']);
        self::assertTrue($composer['config']['sort-packages']);
    }

    /**
     * D-15: keywords 包含 filament-plugin / permission / audit-log / admin-panel。
     */
    public function test_composer_keywords_include_filament_plugin_and_rbac(): void
    {
        $composer = $this->loadComposerJson();
        $keywords = $composer['keywords'];

        foreach (['filament-plugin', 'permission', 'audit-log', 'admin-panel'] as $keyword) {
            self::assertContains($keyword, $keywords);
        }
    }

    /**
     * D-14: suggest.ext-redis 包含中文说明。
     */
    public function test_composer_suggest_has_ext_redis(): void
    {
        $composer = $this->loadComposerJson();

        self::assertIsString($composer['suggest']['ext-redis']);
        self::assertStringContainsString('用于缓存', $composer['suggest']['ext-redis']);
    }

    /**
     * 加载包的 composer.json 并解析为数组。
     */
    private function loadComposerJson(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
