<?php

namespace Filamentboot\FilamentbootCos\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 腾讯云 COS 凭证 Settings 类
 *
 * 存储 COS SecretId、SecretKey（加密）、AppId、Bucket、Region 等凭证配置。
 * secret_key 通过 encrypted() 声明为加密存储字段，SettingsMigration 使用 addEncrypted。
 */
class CosSettings extends Settings
{
    /** SecretId（腾讯云访问密钥 ID） */
    public string $secret_id = '';

    /** SecretKey（腾讯云访问密钥，加密存储） */
    public string $secret_key = '';

    /** AppId（腾讯云账户 AppId，overtrue/flysystem-cos 需要独立字段） */
    public string $app_id = '';

    /** Bucket 名称（不含 AppId 后缀） */
    public string $bucket = '';

    /** Region（存储桶所在地域，默认广州） */
    public string $region = 'ap-guangzhou';

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'cos';
    }

    /**
     * 声明加密存储字段
     *
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['secret_key'];
    }
}
