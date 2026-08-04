<?php

namespace Filamentboot\FilamentbootSite\Support;

/**
 * 链接 scheme 白名单过滤（#13）
 *
 * 区块的 cta_url / button_url 与菜单项的 url 型 target 都是自由文本输入，
 * 校验规则只管长度（见 HeroBlock::rules()），能塞进 javascript: 伪协议。
 * 作者是可信管理员，所以这不是主要攻击面，但纵深防御成本只有十几行：
 * 内容编辑账号一旦被盗，页面里就能挂脚本，而页面是公开的。
 *
 * 放行清单刻意只有这几种，多一种就多一类可被滥用的跳转：
 * - / 开头     站内绝对路径
 * - # 开头     页内锚点
 * - http(s):// 外部链接
 * - tel:       电话（移动端转化入口要用）
 * - mailto:    邮件
 *
 * 其余（javascript: / data: / vbscript: / file: / 裸相对路径）一律返回 null，
 * 由调用方据此决定不渲染按钮或不渲染菜单项——**不要**降级成「渲染成 # 」，
 * 那会给访客一个点了没反应的按钮，比不显示更糟。
 */
final class SafeUrl
{
    /**
     * 允许的显式 scheme（小写比对）
     *
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'tel', 'mailto'];

    /**
     * 过滤链接，不安全时返回 null
     *
     * @param  string|null  $url  作者输入的原始链接
     */
    public static function sanitize(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // 控制字符会被浏览器忽略，`java\0script:` 一类混淆靠它绕过前缀比对
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        // 协议相对 URL（//evil.com）跟着当前页 scheme 走，等于无限制外链，
        // 且长得像站内路径，必须在「/ 开头」判断之前拦掉
        if (str_starts_with($url, '//')) {
            return null;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        // 无 scheme 且不以 / 或 # 开头：裸相对路径（about）或畸形输入。
        // 前者含义取决于当前页面路径，同一个菜单项在不同页面指向不同地址，
        // 不是作者想要的结果，因此不放行。
        if (! is_string($scheme) || $scheme === '') {
            return null;
        }

        return in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true) ? $url : null;
    }
}
