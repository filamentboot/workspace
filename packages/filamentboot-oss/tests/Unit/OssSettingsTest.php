<?php

namespace Filamentboot\FilamentbootOss\Tests\Unit;

use Filamentboot\FilamentbootOss\Settings\OssSettings;
use PHPUnit\Framework\TestCase;

/**
 * OssSettings 单元测试
 *
 * 验证加密字段声明与分组名，纯 TestCase 不引导 Laravel app。
 */
class OssSettingsTest extends TestCase
{
    /**
     * 验证 access_key_secret 已在加密字段列表中声明
     */
    public function test_encrypted_fields_declared(): void
    {
        $encrypted = OssSettings::encrypted();

        self::assertIsArray($encrypted);
        self::assertContains('access_key_secret', $encrypted);
    }

    /**
     * 验证 Settings 分组名为 oss
     */
    public function test_group_name(): void
    {
        self::assertSame('oss', OssSettings::group());
    }
}
