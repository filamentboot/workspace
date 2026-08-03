<?php

namespace Filamentboot\FilamentbootSite\Services;

use Filamentboot\FilamentbootSite\Mail\NewContactMessageMail;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Support\Facades\Mail;

/**
 * 新询盘通知发送服务（A2）
 *
 * 收件人取 SiteSettings.notify_emails（英文逗号分隔），留空即视为关闭通知。
 *
 * **整个发送过程绝不向外抛异常**：Mail::queue() 在队列后端不可用时会抛，
 * 而访客侧的提交成功提示不能依赖通知结果——线索已经落库，通知失败是运维问题，
 * 不该让访客看到一个失败页面然后重复提交。异常统一 report() 进日志。
 */
class ContactMessageNotifier
{
    /**
     * 发送新询盘通知
     *
     * @param  ContactMessage  $message  已落库的询盘记录
     * @return bool 是否已投递到队列（未配置收件人或发送失败均返回 false）
     */
    public function notify(ContactMessage $message): bool
    {
        try {
            $recipients = $this->recipients();

            if ($recipients === []) {
                return false;
            }

            Mail::to($recipients)->queue(new NewContactMessageMail($message));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * 解析收件人列表
     *
     * 逗号分隔后逐个过滤非法地址，避免一个填错的地址让整批通知发不出去。
     * settings 表未迁移时降级为空数组（Pitfall 2）。
     *
     * @return list<string>
     */
    protected function recipients(): array
    {
        /** @var string $configured */
        $configured = rescue(
            fn () => app(SiteSettings::class)->notify_emails,
            '',
            report: false
        );

        $addresses = array_map('trim', explode(',', $configured));

        return array_values(array_filter(
            $addresses,
            static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
