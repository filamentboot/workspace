{{--
 * 新询盘通知邮件正文（A2）
 *
 * 不依赖 $siteSettings 或当前主题：队列 worker 里渲染，尽量少依赖运行时状态。
 * 后台详情链接用 rescue() 包住——面板未注册（如纯 API 部署）时路由不存在，
 * 不能因为拼不出一个链接就让整封通知发不出去。
 *
 * 期望变量：$record — ContactMessage 实例
 --}}
@php
    $rows = array_filter([
        '姓名'       => $record->name,
        '电话'       => $record->phone,
        '留言'       => $record->message,
        '转化入口'   => $record->sourceLabel(),
        '渠道来源'   => $record->utm_source,
        '渠道媒介'   => $record->utm_medium,
        '推广活动'   => $record->utm_campaign,
        '关键词'     => $record->utm_term,
        '创意标识'   => $record->utm_content,
        '首次落地页' => $record->landing_url,
        '来源页'     => $record->referer,
        'IP 地址'    => $record->ip,
        '提交时间'   => $record->created_at?->format('Y-m-d H:i:s'),
    ], fn ($value) => $value !== null && $value !== '');

    $adminUrl = rescue(
        fn () => route('filament.admin.resources.contact-messages.view', ['record' => $record->getKey()]),
        null,
        report: false
    );
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>新询盘通知</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f5;font-family:-apple-system,'Segoe UI','Microsoft YaHei',sans-serif;color:#1f2937;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;padding:24px;">
        <h1 style="margin:0 0 16px;font-size:18px;">收到一条新询盘</h1>

        <table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
            @foreach($rows as $label => $value)
                <tr>
                    <td style="padding:8px 12px 8px 0;color:#6b7280;white-space:nowrap;vertical-align:top;width:96px;">
                        {{ $label }}
                    </td>
                    <td style="padding:8px 0;border-bottom:1px solid #f3f4f6;word-break:break-all;">
                        {{ $value }}
                    </td>
                </tr>
            @endforeach
        </table>

        @if($adminUrl)
            <p style="margin:24px 0 0;">
                <a href="{{ $adminUrl }}"
                   style="display:inline-block;padding:10px 20px;background:#2563eb;color:#ffffff;
                          text-decoration:none;border-radius:6px;font-size:14px;">
                    在后台查看并跟进
                </a>
            </p>
        @endif

        <p style="margin:24px 0 0;font-size:12px;color:#9ca3af;">
            本邮件由官网询盘表单自动发送。收件人可在后台「网站设置 → 新询盘通知邮箱」中调整。
        </p>
    </div>
</body>
</html>
