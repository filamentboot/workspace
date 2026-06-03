<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 初始化四个 Settings 分组的默认值
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // 基础配置
        $this->migrator->add('general.site_name', 'FilamentAdmin');
        $this->migrator->add('general.admin_title', '系统管理后台');
        $this->migrator->add('general.icp_number', '');
        $this->migrator->add('general.copyright', '');

        // 上传配置
        $this->migrator->add('upload.max_file_size', 10240);
        $this->migrator->add('upload.allowed_mimes', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip');
        $this->migrator->add('upload.default_disk', 'public');

        // 安全配置
        $this->migrator->add('security.login_throttle_max_attempts', 5);
        $this->migrator->add('security.login_throttle_decay_minutes', 15);
        $this->migrator->add('security.force_2fa', false);

        // 日志配置
        $this->migrator->add('log.activity_log_retention_days', 90);
        $this->migrator->add('log.login_log_retention_days', 180);
    }
};
