<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * 添加 logo_url 和 contact_email 到 general 配置分组
     *
     * 注意：SettingsMigration add() 无幂等保护，依赖标准 Laravel 迁移机制保证只运行一次。
     * （RESEARCH Pitfall 5）
     */
    public function up(): void
    {
        $this->migrator->add('general.logo_url', '');
        $this->migrator->add('general.contact_email', '');
    }
};
