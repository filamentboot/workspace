<?php

namespace Filamentboot\FilamentbootSite\Jobs;

use Filamentboot\FilamentbootSite\Services\BaiduPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 百度主动推送队列任务（B4）
 *
 * 走队列而非同步：推送要发一次外网 HTTP，同步做会把后台「保存并发布」
 * 的响应时间挂在百度的接口耗时上。
 *
 * 只重试一次。推送失败对 SEO 的影响是「收录慢几天」，不值得为它反复占用
 * 队列——真要补，用 filamentboot-site:push-baidu --all 全量回推更省事。
 */
class PushUrlsToBaidu implements ShouldQueue
{
    use Queueable;

    /**
     * 失败前的最大尝试次数
     */
    public int $tries = 2;

    /**
     * @param  list<string>  $urls  待推送的绝对 URL
     */
    public function __construct(protected array $urls) {}

    /**
     * 执行推送
     *
     * Service 内部已吞掉全部异常，此处不需要再包一层。
     */
    public function handle(BaiduPushService $service): void
    {
        $service->push($this->urls);
    }
}
