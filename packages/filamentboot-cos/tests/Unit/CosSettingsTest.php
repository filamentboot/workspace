<?php

namespace Filamentboot\FilamentbootCos\Tests\Unit;

use Filamentboot\FilamentbootCos\Settings\CosSettings;
use PHPUnit\Framework\TestCase;

/**
 * CosSettings 凭证配置类单元测试
 *
 * 验证加密字段声明和 Settings 分组名。
 */
class CosSettingsTest extends TestCase
{
    /**
     * 验证 encrypted() 返回数组包含 secret_key
     */
    public function test_encrypted_fields_declared(): void
    {
        $encrypted = CosSettings::encrypted();

        self::assertContains('secret_key', $encrypted);
    }

    /**
     * 验证 group() 返回 'cos'
     */
    public function test_group_name(): void
    {
        self::assertSame('cos', CosSettings::group());
    }
}
