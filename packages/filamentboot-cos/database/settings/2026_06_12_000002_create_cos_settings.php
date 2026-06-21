<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 初始化腾讯云 COS Settings 分组默认值
 *
 * secret_key 使用 addEncrypted 初始化，确保加密存储（Pitfall 3）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('cos.secret_id', '');
        // secret_key 必须使用 addEncrypted，与 CosSettings::encrypted() 声明一致
        $this->migrator->addEncrypted('cos.secret_key', '');
        $this->migrator->add('cos.app_id', '');
        $this->migrator->add('cos.bucket', '');
        $this->migrator->add('cos.region', 'ap-guangzhou');
    }
};
