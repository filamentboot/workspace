<?php

namespace Filamentboot\Tests\Unit\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Services\EnvironmentChecker;
use Orchestra\Testbench\TestCase;

/**
 * EnvironmentChecker 自检测试（MKTPLACE-04 / MKTPLACE-07）
 *
 * 覆盖场景：
 * 1. proc_open 被禁用时 selfCheck() 返回 ok=false，issues 非空
 * 2. COMPOSER_PATH 环境变量未设置且系统无 composer 时返回 ok=false
 * 3. vendor/ 目录无写权限时返回 ok=false，issues 非空
 * 4. 所有条件满足时返回 ok=true，composer_path 非空
 *
 * 威胁缓解：T-12-00-02 — selfCheck 永不抛异常，返回结果数组。
 * RESEARCH Pattern 5：'ok','composer_path','issues' 三键结果结构。
 */
class EnvironmentCheckerTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentbootServiceProvider::class,
        ];
    }

    /**
     * proc_open 被禁用时 selfCheck 返回 ok=false（MKTPLACE-04）
     */
    public function test_self_check_fails_when_proc_open_disabled(): void
    {
        $checker = new EnvironmentChecker(
            procOpenAvailable: false,
            composerPathOverride: '/usr/local/bin/composer',
            vendorPathOverride: base_path('vendor'),
        );

        $result = $checker->selfCheck();

        $this->assertArrayHasKey('ok', $result);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['issues']);

        // 确认错误信息含 proc_open 关键字
        $combined = implode(' ', $result['issues']);
        $this->assertStringContainsString('proc_open', $combined);
    }

    /**
     * COMPOSER_PATH 未设置且 which composer 无结果时 selfCheck 返回 ok=false（MKTPLACE-07）
     */
    public function test_self_check_fails_when_composer_path_not_found(): void
    {
        $checker = new EnvironmentChecker(
            procOpenAvailable: true,
            composerPathOverride: '',
            vendorPathOverride: base_path('vendor'),
        );

        $result = $checker->selfCheck();

        $this->assertFalse($result['ok']);
        $this->assertNull($result['composer_path']);

        $combined = implode(' ', $result['issues']);
        $this->assertStringContainsString('Composer', $combined);
    }

    /**
     * vendor/ 目录无写权限时 selfCheck 返回 ok=false（MKTPLACE-07）
     */
    public function test_self_check_fails_when_vendor_directory_not_writable(): void
    {
        $checker = new EnvironmentChecker(
            procOpenAvailable: true,
            composerPathOverride: '/usr/local/bin/composer',
            vendorPathOverride: '/nonexistent-unwritable-path',
        );

        $result = $checker->selfCheck();

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['issues']);

        $combined = implode(' ', $result['issues']);
        $this->assertStringContainsString('vendor', $combined);
    }

    /**
     * 所有条件满足时 selfCheck 返回 ok=true（MKTPLACE-04/07）
     */
    public function test_self_check_passes_when_all_conditions_met(): void
    {
        // vendorPathOverride 不用 base_path('vendor')：Testbench 骨架不保证
        // 这个目录真实存在（CI 全新环境里确实不存在），用系统临时目录才是
        // 真正与运行环境无关的"确定可写路径"。
        $checker = new EnvironmentChecker(
            procOpenAvailable: true,
            composerPathOverride: '/usr/local/bin/composer',
            vendorPathOverride: sys_get_temp_dir(),
        );

        $result = $checker->selfCheck();

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('composer_path', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['composer_path']);
        $this->assertEmpty($result['issues']);
    }
}
