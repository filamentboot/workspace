<?php

namespace Filamentboot\FilamentbootSite\Support;

/**
 * 地图嵌入地址白名单过滤
 *
 * 地图区块（Cms\Blocks\MapBlock）让作者粘贴一条地图服务商生成的嵌入地址，
 * 我们自己渲染 <iframe>，而不是让作者粘一整段 iframe HTML——粘 HTML 等于
 * 在页面里开一个任意标签的入口，而 iframe 能加载任何东西并全屏覆盖页面。
 *
 * 于是唯一需要防的就是「src 指向哪里」，两道闸：
 *   1. 只放行 https：http 的 iframe 在 https 页面上会被浏览器当混合内容直接拦掉，
 *      放行它等于放行一个必然不显示的地图，作者只会以为是我们的 bug
 *   2. host 必须**精确**命中 config('filamentboot-site.map.allowed_hosts')
 *
 * 用精确匹配而不是「以某域名结尾」：后者会被 map.baidu.com.evil.com 绕过，
 * 而写成 .baidu.com 结尾又把整个百度域名树都放进来了。
 * 宿主换地图服务商时在 config 里加一行，比放宽匹配规则安全得多。
 *
 * 不通过时返回 null，调用方据此**不渲染 iframe**（同 SafeUrl 的契约：
 * 不要降级成 about:blank 那种「框在但是空的」，那比不显示更让人困惑）。
 */
final class MapEmbed
{
    /**
     * 过滤嵌入地址，不安全时返回 null
     *
     * @param  string|null  $url  作者粘贴的原始地址
     */
    public static function sanitize(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // 控制字符会被浏览器忽略，靠它可以混淆掉前缀与 host 比对
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return null;
        }

        return in_array($host, self::allowedHosts(), true) ? $url : null;
    }

    /**
     * 允许的地图服务商 host（小写）
     *
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        /** @var array<int, mixed> $hosts */
        $hosts = config('filamentboot-site.map.allowed_hosts', []);

        $normalized = [];

        foreach ($hosts as $host) {
            if (is_string($host) && trim($host) !== '') {
                $normalized[] = strtolower(trim($host));
            }
        }

        return array_values(array_unique($normalized));
    }
}
