<?php

namespace Filamentboot\FilamentbootSite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 访客首触归因采集中间件（A1）
 *
 * 在访客**首次**进入官网时把落地页、来源页与 UTM 参数写入 session，后续访问
 * 不再覆盖——这是「首触归因」的定义：访客从广告落地后往往要跳几个页面才提交
 * 表单，若每次请求都刷新，提交时拿到的会是站内最后一跳，渠道信息全部丢失。
 *
 * 用 session 而非 request 承载：Livewire 表单提交打的是 /livewire/update，
 * 那次请求既没有落地页 URL 也没有原始 Referer，session 是唯一能跨越
 * 「落地」与「提交」两次请求的载体。
 *
 * 仅挂在官网前台路由组上（routes/site.php），不影响宿主自身路由。
 */
class CaptureVisitorAttribution
{
    /**
     * session 中存放归因数据的键
     */
    public const SESSION_KEY = 'site.attribution';

    /**
     * 采集的 UTM 参数名
     *
     * @var list<string>
     */
    public const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /**
     * 处理请求
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldCapture($request)) {
            $request->session()->put(self::SESSION_KEY, $this->attribution($request));
        }

        return $next($request);
    }

    /**
     * 判断本次请求是否需要采集
     *
     * 仅对带 session 的普通 GET 页面请求采集，且 session 中尚无归因数据。
     */
    protected function shouldCapture(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->ajax()) {
            return false;
        }

        // session 未启动（无 session 中间件、控制台调用）时直接跳过，不抛异常
        if (! $request->hasSession()) {
            return false;
        }

        return ! $request->session()->has(self::SESSION_KEY);
    }

    /**
     * 组装归因数据
     *
     * UTM 参数取标量值并截断到列宽，防止超长查询串写坏后续入库。
     *
     * @return array<string, string|null>
     */
    protected function attribution(Request $request): array
    {
        $data = [
            'landing_url' => mb_substr($request->fullUrl(), 0, 1024),
            'referer'     => $this->trim($request->headers->get('referer'), 1024),
        ];

        foreach (self::UTM_KEYS as $key) {
            $value = $request->query($key);

            $data[$key] = is_scalar($value) ? $this->trim((string) $value, 255) : null;
        }

        return $data;
    }

    /**
     * 截断字符串，空值统一归一为 null
     */
    protected function trim(?string $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
