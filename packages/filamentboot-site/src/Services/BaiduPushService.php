<?php

namespace Filamentboot\FilamentbootSite\Services;

use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 百度主动推送服务（B4）
 *
 * 新内容进入已发布态时把 URL 推给百度，比等蜘蛛来抓快一个数量级。
 * 这是国内 SEO 特有的手段，海外搜索引擎没有对应物。
 *
 * **绝不向外抛异常**：推送失败是运维问题，不能让后台保存内容变成 500。
 * 与 ContactMessageNotifier 同一条纪律——异常统一 report() 进日志。
 *
 * 未配置 token 即视为关闭：直接返回，不发请求也不写日志。大多数下游装了
 * 这个包并不会用百度推送，不该让他们的日志被「未配置」刷屏。
 */
class BaiduPushService
{
    /**
     * 百度普通收录 API 端点
     *
     * 官方只提供 http，没有 https 版本。
     */
    protected const ENDPOINT = 'http://data.zz.baidu.com/urls';

    /**
     * 单次请求的 URL 条数上限（官方限制 2000）
     */
    protected const CHUNK_SIZE = 2000;

    /**
     * 推送一批 URL
     *
     * @param  list<string>  $urls  绝对 URL，重复项会被去掉
     * @return int 百度接受的条数（未配置或全部失败时为 0）
     */
    public function push(array $urls): int
    {
        $urls = array_values(array_unique(array_filter($urls)));

        if ($urls === [] || ! $this->isEnabled()) {
            return 0;
        }

        $accepted = 0;

        foreach (array_chunk($urls, self::CHUNK_SIZE) as $chunk) {
            $accepted += $this->pushChunk($chunk);
        }

        return $accepted;
    }

    /**
     * 是否已配置推送凭据
     */
    public function isEnabled(): bool
    {
        return $this->token() !== '' && $this->site() !== '';
    }

    /**
     * 推送单批（不超过 2000 条）
     *
     * @param  list<string>  $urls
     */
    protected function pushChunk(array $urls): int
    {
        try {
            $response = Http::withBody(implode("\n", $urls), 'text/plain')
                ->timeout(10)
                ->post(self::ENDPOINT.'?'.http_build_query([
                    'site'  => $this->site(),
                    'token' => $this->token(),
                ]));

            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];

            // 接口即使 HTTP 200 也可能在 body 里回错误码（token 无效、站点不匹配等）
            if (isset($body['error'])) {
                Log::warning('百度主动推送被拒绝', [
                    'error'   => $body['error'],
                    'message' => $body['message'] ?? '',
                    'count'   => count($urls),
                ]);

                return 0;
            }

            $accepted = (int) ($body['success'] ?? 0);

            // remain 是当天剩余配额（普通站 3000/天）。归零后后续推送会被静默丢弃，
            // 与「推送成功但没收录」表现一样，不记一笔日后无从查起。
            if (array_key_exists('remain', $body) && (int) $body['remain'] <= 0) {
                Log::warning('百度主动推送当日配额已用尽', ['accepted' => $accepted]);
            }

            return $accepted;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * 准入密钥（settings 表未迁移时降级为空，Pitfall 2）
     */
    protected function token(): string
    {
        return trim((string) rescue(
            fn (): string => app(SiteSettings::class)->baidu_push_token,
            '',
            report: false,
        ));
    }

    /**
     * 推送站点域名
     *
     * 未配置时退回 APP_URL 的主机名。必须与站长平台登记的站点完全一致
     * （含 www 与否），否则接口返回 not_same_site。
     */
    protected function site(): string
    {
        $configured = trim((string) rescue(
            fn (): string => app(SiteSettings::class)->baidu_push_site,
            '',
            report: false,
        ));

        if ($configured !== '') {
            return $configured;
        }

        return (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }
}
