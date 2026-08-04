<?php

namespace Filamentboot\FilamentbootSite\Console\Commands;

use Filamentboot\FilamentbootSite\Observers\SearchPushObserver;
use Filamentboot\FilamentbootSite\Services\BaiduPushService;
use Illuminate\Console\Command;

/**
 * 存量内容回推百度（B4）
 *
 * SearchPushObserver 只覆盖「今后发布的内容」。刚接入推送、换了域名、
 * 或者配额用尽导致漏推时，用这个命令把已发布内容整体补一遍。
 *
 * 同步执行不排队：这是运维手动跑的命令，结果要能当场看到。
 */
class PushBaiduCommand extends Command
{
    /** @var string */
    protected $signature = 'filamentboot-site:push-baidu
                            {--all : 推送全部已发布内容（不加此选项只做试运行，仅列出条数）}';

    /** @var string */
    protected $description = '把已发布的案例/方案/产品/资讯/页面 URL 主动推送给百度';

    public function handle(BaiduPushService $service): int
    {
        if (! $service->isEnabled()) {
            $this->error('未配置百度推送凭据，请先在后台「站点设置 → SEO 默认值 → 百度主动推送」填写 token 与站点域名。');

            return self::FAILURE;
        }

        $urls = SearchPushObserver::allPublishedUrls();

        if ($urls === []) {
            $this->warn('没有找到任何已发布内容，无需推送。');

            return self::SUCCESS;
        }

        // 默认试运行：3000/天的配额值得让人先看一眼要推多少条再决定
        if (! $this->option('all')) {
            $this->info(sprintf('试运行：共 %d 条已发布 URL 可推送。加 --all 真正执行。', count($urls)));

            return self::SUCCESS;
        }

        $accepted = $service->push($urls);

        $this->info(sprintf('已提交 %d 条，百度接受 %d 条。', count($urls), $accepted));

        // 接受数少于提交数通常是配额用尽或部分 URL 不属于登记站点，日志里有详情
        return $accepted > 0 ? self::SUCCESS : self::FAILURE;
    }
}
