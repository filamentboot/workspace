<?php

namespace Filamentboot\FilamentbootOss\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 阿里云 OSS 存储配置 Settings 类
 *
 * 凭证以加密方式存储在 settings 表，由超管在后台配置页修改，
 * 无需修改 .env 文件。access_key_secret 使用 AES-256-CBC 加密存储。
 */
class OssSettings extends Settings
{
    /** AccessKeyId（阿里云 RAM 账号） */
    public string $access_key_id = '';

    /** AccessKeySecret（加密存储，敏感凭证） */
    public string $access_key_secret = '';

    /** OSS Bucket 名称 */
    public string $bucket = '';

    /** OSS Endpoint（例如：oss-cn-hangzhou.aliyuncs.com） */
    public string $endpoint = 'oss-cn-hangzhou.aliyuncs.com';

    /** OSS Region（例如：cn-hangzhou） */
    public string $region = 'cn-hangzhou';

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'oss';
    }

    /**
     * 声明需要加密存储的字段
     *
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['access_key_secret'];
    }
}
