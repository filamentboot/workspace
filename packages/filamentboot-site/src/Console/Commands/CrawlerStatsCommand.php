<?php

namespace Filamentboot\FilamentbootSite\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 从 Web 服务器访问日志统计爬虫抓取情况
 *
 * ## 为什么需要它
 *
 * 站长平台的抓取数据**滞后好几天**，而放开收录、提交站点地图、主动推送之后，
 * 最想立刻知道的就是「爬虫到底来没来、抓到的是 200 还是 404」。访问日志是
 * 唯一不滞后的事实来源——它记的是刚刚发生的事。
 *
 * 对 GEO 尤其重要：AI 抓取器有没有真的来过，除了看日志没有别的办法，
 * 那几家都不提供站长平台。
 *
 * ## 用法
 *
 *     php artisan filamentboot-site:crawler-stats --log=/var/log/nginx/access.log
 *     php artisan filamentboot-site:crawler-stats --since=2026-08-01 --paths
 *
 * 默认日志路径取 config('filamentboot-site.crawler_stats.access_log')。
 *
 * ## 只读、不落库
 *
 * 有意不建表：日志本身就是数据源，再同步一份到数据库等于维护两套真相，
 * 而这条命令要回答的问题（最近谁来了、抓成功没有）当场看一眼就够了。
 */
class CrawlerStatsCommand extends Command
{
    /** @var string */
    protected $signature = 'filamentboot-site:crawler-stats
                            {--log= : 访问日志路径，缺省取配置项}
                            {--since= : 只统计该日期之后的记录（Y-m-d）}
                            {--paths : 额外列出每个爬虫抓得最多的 5 个路径}
                            {--other : 把未识别的 UA 也归一档统计，用于发现新爬虫}';

    /** @var string */
    protected $description = '从 Web 服务器访问日志统计各搜索引擎与 AI 爬虫的抓取量与状态码分布';

    /**
     * UA 关键词 => 显示名
     *
     * 顺序有意义：**先匹配先赢**。Bingbot 的 UA 里带 "bingbot"，
     * 而 Google 的部分 UA 同时含 "Googlebot" 与 "Google-Extended"，
     * 把更具体的放前面才不会被泛化条目吃掉。
     *
     * @var array<string, string>
     */
    protected const AGENTS = [
        // 国内搜索
        'Baiduspider'       => '百度',
        'Sogou web spider'  => '搜狗',
        'Sogou inst spider' => '搜狗',
        '360Spider'         => '360',
        'HaosouSpider'      => '360',
        'YisouSpider'       => '神马',
        // 海外搜索
        'Google-Extended' => 'Google-Extended（AI 用途）',
        'Googlebot'       => 'Google',
        'bingbot'         => 'Bing',
        'YandexBot'       => 'Yandex',
        'DuckDuckBot'     => 'DuckDuckGo',
        // AI 抓取器
        'Bytespider'         => '字节 Bytespider（豆包）',
        'PetalBot'           => '华为 PetalBot',
        'OAI-SearchBot'      => 'OpenAI 搜索',
        'ChatGPT-User'       => 'ChatGPT 即时访问',
        'GPTBot'             => 'OpenAI GPTBot',
        'ClaudeBot'          => 'Anthropic ClaudeBot',
        'anthropic-ai'       => 'Anthropic ClaudeBot',
        'PerplexityBot'      => 'Perplexity',
        'Applebot'           => 'Apple',
        'CCBot'              => 'Common Crawl',
        'meta-externalagent' => 'Meta',
    ];

    /**
     * nginx combined 日志格式
     *
     * 只抓需要的四段（时间、请求、状态码、UA），其余用非捕获跳过。
     * 不匹配的行直接跳过——日志里混进别的格式是常态，不该因此中断。
     */
    protected const LINE = '/^\S+ \S+ \S+ \[([^\]]+)\] "(?:\S+) (\S+)[^"]*" (\d{3}) \S+ "[^"]*" "([^"]*)"/';

    public function handle(): int
    {
        $path = (string) ($this->option('log')
            ?: config('filamentboot-site.crawler_stats.access_log', ''));

        if ($path === '') {
            $this->error('未指定日志路径。用 --log=/var/log/nginx/access.log，或配置 filamentboot-site.crawler_stats.access_log。');

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error(sprintf('日志不可读：%s', $path));

            return self::FAILURE;
        }

        $since = $this->parseSince();

        if ($since === false) {
            $this->error('--since 需要 Y-m-d 格式，例如 --since=2026-08-01。');

            return self::FAILURE;
        }

        $stats = $this->collect($path, $since);

        if ($stats === []) {
            $this->warn('没有匹配到任何爬虫记录。日志格式不是 nginx combined 时本命令解析不了。');

            return self::SUCCESS;
        }

        $this->renderSummary($stats);

        if ($this->option('paths')) {
            $this->renderPaths($stats);
        }

        return self::SUCCESS;
    }

    /**
     * 逐行扫描日志并按爬虫归类
     *
     * 用 fgets 逐行读而不是 file()：线上访问日志动辄几百 MB，
     * 一次读进内存会直接 OOM。
     *
     * @return array<string, array{total: int, status: array<int, int>, paths: array<string, int>}>
     */
    protected function collect(string $path, ?Carbon $since): array
    {
        $stats  = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            if (preg_match(self::LINE, $line, $m) !== 1) {
                continue;
            }

            [, $time, $requestPath, $status, $agent] = $m;

            if ($since !== null && ! $this->afterSince($time, $since)) {
                continue;
            }

            $name = $this->classify($agent);

            if ($name === null) {
                continue;
            }

            $stats[$name] ??= ['total' => 0, 'status' => [], 'paths' => []];

            $stats[$name]['total']++;
            $stats[$name]['status'][(int) $status] = ($stats[$name]['status'][(int) $status] ?? 0) + 1;

            if ($this->option('paths')) {
                $stats[$name]['paths'][$requestPath] = ($stats[$name]['paths'][$requestPath] ?? 0) + 1;
            }
        }

        fclose($handle);

        return $stats;
    }

    /**
     * UA 归类，未识别时返回 null（或 --other 下归入「其它」）
     */
    protected function classify(string $agent): ?string
    {
        foreach (self::AGENTS as $needle => $label) {
            if (stripos($agent, $needle) !== false) {
                return $label;
            }
        }

        // 未知 UA 里也可能藏着新爬虫，但默认不统计——否则真人浏览器会把表刷爆
        return $this->option('other') ? '其它（未识别）' : null;
    }

    /**
     * 该行时间是否不早于 --since
     *
     * nginx 的时间形如 `07/Aug/2026:08:15:32 +0800`。解析失败一律保留该行：
     * 宁可多统计一条，也不要因为时区或格式差异悄悄丢数据。
     */
    protected function afterSince(string $time, Carbon $since): bool
    {
        $parsed = Carbon::createFromFormat('d/M/Y:H:i:s O', $time);

        return $parsed === false || $parsed->greaterThanOrEqualTo($since);
    }

    /**
     * 解析 --since，未提供返回 null，格式非法返回 false
     */
    protected function parseSince(): Carbon|false|null
    {
        $since = $this->option('since');

        if ($since === null || $since === '') {
            return null;
        }

        $parsed = Carbon::createFromFormat('Y-m-d', (string) $since);

        return $parsed === false ? false : $parsed->startOfDay();
    }

    /**
     * 输出抓取量与状态码分布
     *
     * @param  array<string, array{total: int, status: array<int, int>, paths: array<string, int>}>  $stats
     */
    protected function renderSummary(array $stats): void
    {
        uasort($stats, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $rows = [];

        foreach ($stats as $name => $data) {
            $status = $data['status'];
            krsort($status);

            $ok      = $this->countByPrefix($status, 2);
            $errors  = $this->countByPrefix($status, 4) + $this->countByPrefix($status, 5);
            $summary = [];

            foreach ($status as $code => $count) {
                $summary[] = $code.'×'.$count;
            }

            $rows[] = [
                $name,
                $data['total'],
                $data['total'] > 0 ? round($ok / $data['total'] * 100).'%' : '—',
                $errors > 0 ? (string) $errors : '',
                implode('  ', $summary),
            ];
        }

        $this->table(['爬虫', '抓取次数', '2xx 占比', '4xx/5xx', '状态码分布'], $rows);
        $this->line('');
        $this->comment('2xx 占比明显偏低说明爬虫在撞死链或被拦，比抓取次数少更值得先查。');
    }

    /**
     * 某一档状态码的合计（2 => 2xx）
     *
     * @param  array<int, int>  $status
     */
    protected function countByPrefix(array $status, int $prefix): int
    {
        $total = 0;

        foreach ($status as $code => $count) {
            if (intdiv($code, 100) === $prefix) {
                $total += $count;
            }
        }

        return $total;
    }

    /**
     * 输出每个爬虫抓得最多的路径
     *
     * @param  array<string, array{total: int, status: array<int, int>, paths: array<string, int>}>  $stats
     */
    protected function renderPaths(array $stats): void
    {
        foreach ($stats as $name => $data) {
            $paths = $data['paths'];
            arsort($paths);

            $this->line('');
            $this->info($name.' 抓得最多的路径：');

            foreach (array_slice($paths, 0, 5, true) as $path => $count) {
                $this->line(sprintf('  %5d  %s', $count, $path));
            }
        }
    }
}
