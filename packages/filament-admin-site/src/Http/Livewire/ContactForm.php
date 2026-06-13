<?php

namespace LaravelStack\FilamentAdminSite\Http\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\View\View;
use LaravelStack\FilamentAdminSite\Enums\ContactMessageStatus;
use LaravelStack\FilamentAdminSite\Models\ContactMessage;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * 询盘表单 Livewire 组件
 *
 * 功能：
 * - 收集访客姓名、电话、留言信息
 * - 速率限制：每 IP 5 分钟最多 3 次（D-10-15 安全硬要求，T-10-04-02）
 * - 写入 site_contact_messages 表，默认状态 UNREAD
 * - 记录提交 IP 地址（用于速率限制与审计）
 *
 * 组件别名：filament-admin-site::contact-form
 * 在 Blade 中使用：<livewire:filament-admin-site::contact-form />
 */
class ContactForm extends Component
{
    use WithRateLimiting;

    /**
     * 访客姓名
     *
     * @var string
     */
    #[Validate('required|string|max:50')]
    public string $name = '';

    /**
     * 联系电话
     *
     * @var string
     */
    #[Validate('required|string|max:20')]
    public string $phone = '';

    /**
     * 留言内容
     *
     * @var string
     */
    #[Validate('required|string|max:500')]
    public string $message = '';

    /**
     * 提交成功标志（切换视图为感谢提示）
     *
     * @var bool
     */
    public bool $submitted = false;

    /**
     * 提交询盘表单
     *
     * 1. 速率限制校验（每 IP 5 分钟最多 3 次）
     * 2. 字段格式校验
     * 3. 写入 site_contact_messages 表
     * 4. 标记提交成功，重置输入字段
     *
     * @return void
     */
    public function submit(): void
    {
        // 速率限制：每 IP 5 分钟（300 秒）最多 3 次（D-10-15，T-10-04-02）
        try {
            $this->rateLimit(3, 300);
        } catch (TooManyRequestsException) {
            $this->addError('phone', '提交过于频繁，请稍后再试。');

            return;
        }

        // 字段校验
        $this->validate();

        // 写入询盘记录（含 IP，防刷审计）
        ContactMessage::create([
            'name'    => $this->name,
            'phone'   => $this->phone,
            'message' => $this->message,
            'status'  => ContactMessageStatus::UNREAD,
            'ip'      => request()->ip(),
        ]);

        // 标记提交成功，重置输入
        $this->submitted = true;
        $this->name      = '';
        $this->phone     = '';
        $this->message   = '';
    }

    /**
     * 渲染组件视图
     *
     * 视图路径：filament-admin-site::livewire.contact-form
     * （由 SiteServiceProvider::registerThemeViews() 注册命名空间后，
     * 10-05 在 resources/views/livewire/ 下提供具体模板）
     *
     * @return View
     */
    public function render(): View
    {
        return view('filament-admin-site::livewire.contact-form');
    }
}
