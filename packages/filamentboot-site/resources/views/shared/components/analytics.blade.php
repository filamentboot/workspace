{{--
 * 统计代码注入组件（跨主题共享，A3）
 *
 * 此前全站没有任何 analytics 挂载点，想挂百度统计 / GA 就得改两套主题的 base 布局。
 *
 * 两级设计：
 *   1. 结构化 ID（baidu_tongji_id / ga_measurement_id）——由固定模板生成代码，
 *      不给填写方写脚本的机会，这是常规路径
 *   2. 自定义代码块（head_scripts / body_end_scripts）——兜底，原样输出
 *
 * ⚠️ 自定义代码块**不过 purifier**（过滤会破坏脚本），等于开放前台任意 JS 执行。
 * 约束见 SiteSettings 对应属性的注释：仅 manage_site_settings 权限可改，
 * 变更写操作日志，后台字段旁明示风险。
 *
 * 在线客服代码不在这里——它有独立开关，见 shared/components/live-chat.blade.php。
 *
 * 期望变量：
 *   $position     — 'head' 或 'body'
 *   $siteSettings — SiteSettings 实例（可为 null，由 shareSiteSettings() 注入）
 --}}
@php
    $position = $position ?? 'head';

    $baiduId  = trim((string) ($siteSettings->baidu_tongji_id ?? ''));
    $gaId     = trim((string) ($siteSettings->ga_measurement_id ?? ''));
    $custom   = trim((string) ($position === 'head'
        ? ($siteSettings->head_scripts ?? '')
        : ($siteSettings->body_end_scripts ?? '')));
@endphp

@if($position === 'head')
    @if($baiduId !== '')
        {{-- 百度统计（hm.js），ID 只允许十六进制串，非法值直接不输出 --}}
        @if(preg_match('/^[a-f0-9]{8,64}$/i', $baiduId))
        <script>
            var _hmt = _hmt || [];
            (function () {
                var hm = document.createElement('script');
                hm.src = 'https://hm.baidu.com/hm.js?{{ $baiduId }}';
                var s = document.getElementsByTagName('script')[0];
                s.parentNode.insertBefore(hm, s);
            })();
        </script>
        @endif
    @endif

    @if($gaId !== '' && preg_match('/^G-[A-Z0-9]{4,20}$/i', $gaId))
        {{-- Google Analytics 4 --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}');
        </script>
    @endif
@endif

{{-- 自定义代码块（原样输出，风险见文件头注释） --}}
@if($custom !== '')
{!! $custom !!}
@endif

@if($position === 'body')
    {{-- 表单提交成功的转化事件上报（供广告投放侧回传）。
         事件由 shared/components/contact-form.blade.php 的 Alpine 提交逻辑
         直接 window.dispatchEvent('site-contact-submitted')（#29 之前是 Livewire 转发的）。--}}
    <script>
        window.addEventListener('site-contact-submitted', function () {
            if (typeof window._hmt !== 'undefined') {
                window._hmt.push(['_trackEvent', 'contact', 'submit', 'site-contact-form']);
            }

            if (typeof window.gtag === 'function') {
                window.gtag('event', 'generate_lead', { method: 'site-contact-form' });
            }
        });
    </script>
@endif
