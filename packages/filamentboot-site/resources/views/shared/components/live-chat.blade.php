{{--
 * 在线客服代码位（跨主题共享）
 *
 * 与 analytics 同类：注入点，不是视觉组件，所以放在 shared/ 不受「双主题各一份」约束。
 *
 * ⚠️ 原样输出、**不过 purifier**（过滤会破坏脚本），等于开放前台任意 JS 执行能力。
 * 与 head_scripts / body_end_scripts 同一套信任模型：仅 manage_site_settings
 * 权限可改、变更写操作日志、后台字段旁明示风险。
 *
 * 开关与代码分成两个设置项：关掉时代码仍然留在库里，只是不输出——
 * 换供应商或临时无人值守时不用把一大段脚本剪出去存别处。
 *
 * 期望变量：
 *   $siteSettings — SiteSettings 实例（可为 null，由 shareSiteSettings() 注入）
 --}}
@php
    $liveChat = ($siteSettings->live_chat_enabled ?? false)
        ? trim((string) ($siteSettings->live_chat_script ?? ''))
        : '';
@endphp

@if($liveChat !== '')
{!! $liveChat !!}
@endif
