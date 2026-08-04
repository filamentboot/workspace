<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 补充搜索引擎站长验证与百度主动推送设置项（B4）
 *
 * 三个 *_verify_code：站长平台下发的验证串，做成设置项后换验证方式不必改模板。
 * 值会拼进 <meta content>，输出前由 seo-meta 组件正则校验字符集
 * （同 A3 统计 ID 的纪律）。
 *
 * baidu_push_token / baidu_push_site：百度主动推送 API 凭据。新内容进入
 * 已发布态时主动 push，比等抓取快一个数量级（国内 SEO 特有）。
 * 未配置 token 时推送服务直接返回，不排队、不报错。
 *
 * add() 对已存在键幂等，不覆盖既有值（T-10-01-03 防护）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // 站长平台验证 meta
        $this->migrator->add('site.baidu_verify_code', '');
        $this->migrator->add('site.google_verify_code', '');
        $this->migrator->add('site.bing_verify_code', '');

        // 百度主动推送
        $this->migrator->add('site.baidu_push_token', '');
        $this->migrator->add('site.baidu_push_site', '');
    }
};
