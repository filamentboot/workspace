<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 新增在线客服代码位设置项
 *
 * 客服代码本可以塞进已有的 body_end_scripts，独立成两个字段的理由只有一个：
 * 开关要能与统计代码分开。换供应商、大促没人值守、气泡挡了移动端底部操作条，
 * 运营都需要「留着代码但先关掉」，而不是把一大段脚本剪出去存别处。
 *
 * 默认 false：装上包不该凭空多出一个第三方脚本。
 *
 * add() 对已存在键幂等，不覆盖既有值（T-10-01-03 防护）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.live_chat_enabled', false);
        $this->migrator->add('site.live_chat_script', '');
    }
};
