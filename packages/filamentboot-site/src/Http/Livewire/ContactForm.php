<?php

namespace Filamentboot\FilamentbootSite\Http\Livewire;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Http\Middleware\CaptureVisitorAttribution;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Services\ContactMessageNotifier;
use Illuminate\View\View;
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
 * - 记录转化入口（source）与首触渠道归因（landing_url / referer / utm_*，A1）
 * - 触发新询盘邮件通知（A2，走队列且失败不阻断提交）
 * - 提交成功后派发浏览器事件供统计侧上报转化（A3）
 *
 * 组件别名：filamentboot-site::contact-form
 * 在 Blade 中使用：<livewire:filamentboot-site::contact-form />
 */
class ContactForm extends Component
{
    use WithRateLimiting;

    /**
     * 访客姓名
     */
    #[Validate('required|string|max:50')]
    public string $name = '';

    /**
     * 联系电话
     */
    #[Validate('required|string|max:20')]
    public string $phone = '';

    /**
     * 留言内容（选填，与视图 placeholder "选填" 保持一致）
     */
    #[Validate('nullable|string|max:500')]
    public ?string $message = null;

    /**
     * 转化入口标识（A1）
     *
     * 由视图的 x-effect 从全局 Alpine store `$store.contactPanel.source` 同步进来，
     * 取值即各 CTA 的 data-contact-trigger（floating / hero / nav-desktop 等）。
     *
     * 该值完全由客户端提供，入库前一律经 normalizedSource() 过滤字符集，
     * 不做校验拒绝——访客并未填写此字段，为它报错只会让人无从修正。
     */
    public string $source = '';

    /**
     * 提交成功标志（切换视图为感谢提示）
     */
    public bool $submitted = false;

    /**
     * 蜜罐字段（C2）
     *
     * 人类看不见也 Tab 不到，只有按 name 盲填的脚本会写进来。
     * 视图里用屏外定位而非 display:none —— 后者是已知特征，成熟脚本会跳过。
     */
    public string $website = '';

    /**
     * 表单渲染时刻的 Unix 时间戳（C2）
     *
     * 与提交时刻的间隔小于阈值即判为机器。Livewire 对公开属性做 checksum 校验，
     * 客户端改不动这个值，不需要额外签名。
     */
    public int $renderedAt = 0;

    /**
     * 最短合理填表耗时（秒）
     *
     * 三秒连姓名带电话都敲不完。定得再高会误伤用浏览器自动填充的真实访客。
     */
    protected const MIN_FILL_SECONDS = 3;

    /**
     * 组件挂载
     */
    public function mount(): void
    {
        $this->renderedAt = now()->getTimestamp();
    }

    /**
     * 提交询盘表单
     *
     * 1. 速率限制校验（每 IP 5 分钟最多 3 次）
     * 2. 字段格式校验
     * 3. 写入 site_contact_messages 表（含来源与首触归因）
     * 4. 标记提交成功，重置输入字段
     */
    public function submit(): void
    {
        // 机器人识别放在限流之前：不让脚本消耗真实访客共享的那点 IP 限流额度（C2）
        if ($this->looksAutomated()) {
            $this->markSubmitted();

            return;
        }

        // 速率限制：每 IP 5 分钟（300 秒）最多 3 次（D-10-15，T-10-04-02）
        try {
            $this->rateLimit(3, 300);
        } catch (TooManyRequestsException) {
            $this->addError('phone', '提交过于频繁，请稍后再试。');

            return;
        }

        // 字段校验
        $this->validate();

        // 写入询盘记录（含 IP，防刷审计；message 为选填，null 时存空字符串）
        $record = ContactMessage::create([
            'name'    => $this->name,
            'phone'   => $this->phone,
            'message' => $this->message ?? '',
            'status'  => ContactMessageStatus::UNREAD,
            'ip'      => request()->ip(),
            'source'  => $this->normalizedSource(),
            ...$this->attribution(),
        ]);

        // 通知走队列且内部吞掉全部异常，不会影响下面的成功态（A2）
        app(ContactMessageNotifier::class)->notify($record);

        $this->markSubmitted();

        // 通知前端上报转化事件（A3：百度统计 / GA 由 analytics 组件监听）
        // 只在真正入库时派发：机器人提交若也上报，投放后台的转化数会被灌水
        $this->dispatch('site-contact-submitted');
    }

    /**
     * 这次提交看起来像机器人吗（C2）
     *
     * 两个零摩擦信号，不给真实访客加验证码负担：
     * - 蜜罐字段被填了（人类看不见也 Tab 不到）
     * - 从渲染到提交不足 MIN_FILL_SECONDS 秒
     *
     * renderedAt 为 0 说明组件没走 mount()（例如宿主自行实例化），
     * 此时不做耗时判断，宁可放过也不误杀。
     */
    protected function looksAutomated(): bool
    {
        if (trim($this->website) !== '') {
            return true;
        }

        return $this->renderedAt > 0
            && (now()->getTimestamp() - $this->renderedAt) < self::MIN_FILL_SECONDS;
    }

    /**
     * 切到成功态并清空输入
     *
     * 机器人命中时走的也是这里——**不告诉它失败了**。回一个错误等于在教脚本
     * 怎么绕过；回成功则让它以为得手，不会换策略重试。
     */
    protected function markSubmitted(): void
    {
        $this->submitted = true;
        $this->name      = '';
        $this->phone     = '';
        $this->message   = null;
        $this->website   = '';
    }

    /**
     * 过滤转化入口标识
     *
     * 只保留小写字母、数字与连字符并截断到列宽。该值来自客户端，
     * 不过滤会把任意字符串原样带进后台列表与导出文件。
     * 空串归一为 null，便于后台按「无来源」筛选。
     */
    protected function normalizedSource(): ?string
    {
        $source = mb_substr(preg_replace('/[^a-z0-9\-]/', '', mb_strtolower($this->source)) ?? '', 0, 50);

        return $source !== '' ? $source : null;
    }

    /**
     * 读取 session 中的首触归因数据
     *
     * 由 CaptureVisitorAttribution 中间件在访客首次落地时写入。显式按键取值，
     * 不把整段 session 数据摊进 create()——ContactMessage 的 $guarded 为空，
     * 直接展开等于给任意 session 键开了批量赋值入口。
     *
     * @return array<string, string|null>
     */
    protected function attribution(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = session(CaptureVisitorAttribution::SESSION_KEY, []);

        $keys = ['landing_url', 'referer', ...CaptureVisitorAttribution::UTM_KEYS];

        $data = [];

        foreach ($keys as $key) {
            $value = $stored[$key] ?? null;

            $data[$key] = is_scalar($value) && (string) $value !== '' ? (string) $value : null;
        }

        return $data;
    }

    /**
     * 渲染组件视图
     *
     * 视图路径：filamentboot-site::livewire.contact-form
     * （由 SiteServiceProvider::registerThemeViews() 注册命名空间后，
     * 10-05 在 resources/views/livewire/ 下提供具体模板）
     */
    public function render(): View
    {
        return view('filamentboot-site::livewire.contact-form');
    }
}
