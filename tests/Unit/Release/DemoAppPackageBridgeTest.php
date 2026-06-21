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
        self::assertSame('packages/filamentboot', $composer['repositories'][0]['url']);
        self::assertArrayHasKey('filamentboot/filamentboot', $composer['require']);
        self::assertArrayNotHasKey('Filamentboot\\', $composer['autoload']['psr-4'] ?? []);
    }

    public function test_package_directory_exists_with_package_composer_json(): void
    {
        self::assertFileExists(__DIR__.'/../../../packages/filamentboot/composer.json');

        $composer = json_decode((string) file_get_contents(__DIR__.'/../../../packages/filamentboot/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('filamentboot/filamentboot', $composer['name']);
        self::assertSame('library', $composer['type']);
    }

    /**
     * 断言根 CI 的 APP_KEY 环境变量引用 secret 而非硬编码占位符。
     *
     * RELEASE-04 / D-44：根 ci.yml 中不允许存在 base64:AAAA 硬编码，
     * 必须改用 ${{ secrets.CI_APP_KEY }} 引用，防止 CI secret 信息泄露。
     */
    public function test_root_ci_app_key_is_not_hardcoded(): void
    {
        $ci = (string) file_get_contents(__DIR__.'/../../../.github/workflows/ci.yml');

        self::assertStringNotContainsString(
            'base64:AAAA',
            $ci,
            '根 CI ci.yml 不应包含硬编码 APP_KEY 占位符 base64:AAAA'
        );

        self::assertStringContainsString(
            'secrets.CI_APP_KEY',
            $ci,
            '根 CI ci.yml 应使用 secrets.CI_APP_KEY 引用替代硬编码'
        );
    }

    /**
     * 断言根 CI 包含安全审计步骤。
     *
     * RELEASE-03：根 ci.yml 应包含 composer audit --abandoned=report，
     * 以便在 CI summary 中可见供应链安全审计结果（仅警告，不阻塞主线）。
     */
    public function test_root_ci_has_security_audit_step(): void
    {
        $ci = (string) file_get_contents(__DIR__.'/../../../.github/workflows/ci.yml');

        self::assertStringContainsString(
            'composer audit --abandoned=report',
            $ci,
            '根 CI ci.yml 应包含 composer audit --abandoned=report 安全审计步骤'
        );
    }
}
