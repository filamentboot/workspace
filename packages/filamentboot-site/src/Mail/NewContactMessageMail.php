<?php

namespace Filamentboot\FilamentbootSite\Mail;

use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 新询盘通知邮件（A2）
 *
 * 实现 ShouldQueue 走队列异步发送：营销站线索响应速度是转化的头号变量，
 * 但通知链路绝不能反过来阻断访客提交，因此既异步、又在调用侧包异常。
 */
class NewContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  ContactMessage  $message  触发通知的询盘记录
     */
    public function __construct(public ContactMessage $message) {}

    /**
     * 邮件信封（主题带来源，便于在收件箱直接判断线索质量）
     */
    public function envelope(): Envelope
    {
        $source = $this->message->sourceLabel();

        return new Envelope(
            subject: '【新询盘】'.$this->message->name.($source !== null ? '（来自'.$source.'）' : ''),
        );
    }

    /**
     * 邮件正文
     *
     * 视图落在包内视图根，由 SiteServiceProvider::registerThemeViews() 的
     * 第 4 优先级路径解析，不依赖当前激活主题。
     */
    public function content(): Content
    {
        return new Content(
            view: 'filamentboot-site::mail.new-contact-message',
            with: ['record' => $this->message],
        );
    }
}
