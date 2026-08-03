<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 补充新询盘通知与统计代码注入设置项（A2 / A3）
 *
 * notify_emails：新询盘通知收件人，逗号分隔，留空关闭通知。
 *
 * baidu_tongji_id / ga_measurement_id：优先提供结构化 ID，由固定模板生成
 * 统计代码，避免让填写方直接写脚本。
 *
 * head_scripts / body_end_scripts：自定义代码块兜底，原样输出且不过 purifier，
 * 属于有意开放的例外——仅 manage_site_settings 权限可改且变更记审计。
 *
 * add() 对已存在键幂等，不覆盖既有值（T-10-01-03 防护）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // 新询盘通知收件人（多个用英文逗号分隔）
        $this->migrator->add('site.notify_emails', '');

        // 结构化统计 ID
        $this->migrator->add('site.baidu_tongji_id', '');
        $this->migrator->add('site.ga_measurement_id', '');

        // 自定义代码块（高风险字段，权限 + 审计双兜底）
        $this->migrator->add('site.head_scripts', '');
        $this->migrator->add('site.body_end_scripts', '');
    }
};
