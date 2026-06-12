<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 初始化 OSS 存储 Settings 分组
 *
 * access_key_secret 使用 addEncrypted 存储，确保凭证以 AES-256-CBC 加密。
 * 其余字段使用 add 存储明文默认值。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // AccessKeyId（明文，非敏感）
        $this->migrator->add('oss.access_key_id', '');

        // AccessKeySecret（加密存储，T-08-01 安全要求）
        $this->migrator->addEncrypted('oss.access_key_secret', '');

        // Bucket 名称（明文）
        $this->migrator->add('oss.bucket', '');

        // Endpoint（默认杭州区域）
        $this->migrator->add('oss.endpoint', 'oss-cn-hangzhou.aliyuncs.com');

        // Region（默认杭州区域）
        $this->migrator->add('oss.region', 'cn-hangzhou');
    }
};
