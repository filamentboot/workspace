<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 新增搜狗站长平台验证串设置项
 *
 * 此前只有百度 / Google / Bing 三家，搜狗要验证只能改模板。
 *
 * 补它的直接原因不是搜狗自身的搜索份额，而是**它是腾讯元宝的检索源之一**：
 * 生成式引擎能不能引用本站，前提是本站在它的检索源里被收录。
 *
 * 默认空串：装上包不该凭空多出一个 meta 标签，行为与此前保持一致。
 *
 * add() 对已存在键幂等，不覆盖既有值（T-10-01-03 防护）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.sogou_verify_code', '');
    }
};
