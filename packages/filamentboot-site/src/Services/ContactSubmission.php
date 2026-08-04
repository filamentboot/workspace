<?php

namespace Filamentboot\FilamentbootSite\Services;

use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Enums\ContactSubmissionResult;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 询盘提交动作（#29）
 *
 * 唯一一份「收到一次询盘该做什么」的实现：机器人识别 → IP 限流 → 字段校验 → 入库 → 通知。
 * 抽出来的理由是 #29 把公开页的表单从 Livewire 换成了无状态 POST 端点，而 Livewire 组件
 * 仍保留给想用它的宿主——两条入口各写一份判据，迟早会漂移成两套「已发布」的定义那种事。
 *
 * 归因（landing_url / referer / utm_*）从**请求体**读，不再读 session。公开页要能整页缓存
 * 就不能起 session，首触归因因此搬到客户端 localStorage（见 shared/components/attribution-store）。
 */
class ContactSubmission
{
    /**
     * 最短合理填表耗时（秒）
     *
     * 三秒连姓名带电话都敲不完。定得再高会误伤用浏览器自动填充的真实访客。
     *
     * ⚠️ 这个耗时现在由客户端上报，**可被脚本伪造**。整页缓存之后服务端渲染的时间戳会被
     * 冻结在缓存里（缓存 10 分钟，所有人拿到的都是 10 分钟前的值），这道校验在服务端
     * 已无从锚定——这是「整页缓存」与「服务端可信时间戳」的固有矛盾，不是实现疏漏。
     * 它降级为零摩擦的低成本启发式；蜜罐与 IP 限流才是真防线。
     */
    public const MIN_FILL_SECONDS = 3;

    /**
     * 每 IP 允许的提交次数与窗口（秒）
     */
    public const RATE_LIMIT = 3;

    public const RATE_WINDOW = 300;

    /**
     * 自定义字段答案的边界
     *
     * 与 Cms\Blocks\ContactFormBlock::MAX_FIELDS 对应，但**刻意不引用它**：
     * 那是「后台最多能配几个问题」，这是「服务端最多收几个答案」。
     * 两者数值相同是巧合而非约束——宿主自定义表单可以少配，
     * 而服务端的上限必须独立于任何前台配置成立。
     */
    public const MAX_EXTRA_ANSWERS = 6;

    public const EXTRA_LABEL_LENGTH = 50;

    public const EXTRA_VALUE_LENGTH = 500;

    /**
     * 归因字段白名单
     *
     * @var list<string>
     */
    public const ATTRIBUTION_KEYS = [
        'landing_url',
        'referer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /**
     * 处理一次提交
     *
     * @param  array<string, mixed>  $input  原始输入（请求体或 Livewire 组件属性）
     * @param  string|null  $ip  访客 IP，限流与审计用
     *
     * @throws ValidationException 字段校验失败
     */
    public function submit(array $input, ?string $ip): ContactSubmissionResult
    {
        // 机器人识别放在限流之前：不让脚本消耗真实访客共享的那点 IP 限流额度（C2）
        if ($this->looksAutomated($input)) {
            return ContactSubmissionResult::DISCARDED;
        }

        if ($this->isThrottled($ip)) {
            return ContactSubmissionResult::THROTTLED;
        }

        /** @var array{name: string, phone: string, message: string|null} $validated */
        $validated = Validator::make($input, [
            'name'    => ['required', 'string', 'max:50'],
            'phone'   => ['required', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:500'],
        ], [], [
            'name'    => '姓名',
            'phone'   => '电话',
            'message' => '留言',
        ])->validate();

        $record = ContactMessage::create([
            'name'    => $validated['name'],
            'phone'   => $validated['phone'],
            'message' => $validated['message'] ?? '',
            'status'  => ContactMessageStatus::UNREAD,
            'ip'      => $ip,
            'source'  => $this->normalizedSource($input['source'] ?? null),
            'extra'   => $this->extraAnswers($input['extra'] ?? null),
            ...$this->attribution($input),
        ]);

        // 通知走队列且内部吞掉全部异常，不会影响提交本身（A2）
        app(ContactMessageNotifier::class)->notify($record);

        return ContactSubmissionResult::CREATED;
    }

    /**
     * 这次提交看起来像机器人吗（C2）
     *
     * 两个零摩擦信号，不给真实访客加验证码负担：
     * - 蜜罐字段被填了（人类看不见也 Tab 不到）
     * - 从表单可交互到提交不足 MIN_FILL_SECONDS 秒
     *
     * elapsed 缺失或非正数时不做耗时判断，宁可放过也不误杀（宿主自行实例化、
     * 客户端 JS 被拦、时钟异常都会走到这里）。
     *
     * @param  array<string, mixed>  $input
     */
    protected function looksAutomated(array $input): bool
    {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            return true;
        }

        $elapsed = (int) ($input['elapsed'] ?? 0);

        return $elapsed > 0 && $elapsed < self::MIN_FILL_SECONDS;
    }

    /**
     * IP 限流：每 IP RATE_WINDOW 秒内最多 RATE_LIMIT 次（D-10-15，T-10-04-02）
     *
     * IP 取不到时不限流：没有 IP 就没有可靠的限流维度，按 IP 为空归一到同一个桶
     * 会让所有取不到 IP 的真实访客互相挤额度。
     */
    protected function isThrottled(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        $key = 'site-contact:'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT)) {
            return true;
        }

        RateLimiter::hit($key, self::RATE_WINDOW);

        return false;
    }

    /**
     * 过滤转化入口标识
     *
     * 只保留小写字母、数字与连字符并截断到列宽。该值来自客户端，
     * 不过滤会把任意字符串原样带进后台列表与导出文件。
     * 空串归一为 null，便于后台按「无来源」筛选。
     */
    protected function normalizedSource(mixed $source): ?string
    {
        if (! is_scalar($source)) {
            return null;
        }

        $value = mb_substr(
            preg_replace('/[^a-z0-9\-]/', '', mb_strtolower((string) $source)) ?? '',
            0,
            50
        );

        return $value !== '' ? $value : null;
    }

    /**
     * 整理自定义字段答案（询盘表单区块配的「额外问题」）
     *
     * 键是问题文本，值是访客填的内容，直接落进 site_contact_messages.extra。
     *
     * ⚠️ 这里做的是**边界**约束，不是「按表单配置校验」。端点是无状态的（#29），
     * 收到的只是一份键值对，无从知道是哪份区块配置渲染出来的表单，因此
     * 「某个问题必填」只能靠浏览器（理由见 Cms\Blocks\ContactFormBlock 的类注释）。
     * 边界必须卡住：不卡的话任何人都能往这一列灌任意大小的 JSON。
     *
     * 值只收标量：多选、文件那类结构化答案不在支持范围（同上），
     * 收到数组直接丢掉那一条而不是 json_encode 塞进去——后台会显示成一坨没人看得懂的东西。
     *
     * ⚠️ **存成有序列表 `[{label, value}]` 而不是 `{label: value}` 映射。** 请求体里来的是
     * 映射（前端 form.extra 以问题文本为键），但 MySQL 的 JSON 类型会**规范化对象、丢掉键顺序**
     * （实测：两个答案存进去再读出来顺序被重排）。而答案顺序是有意义的——它就是表单上
     * 问题的先后。JSON 数组的顺序 MySQL 会保留，所以这里转成列表。
     *
     * 顺带解决重名：映射里同名键本来就只剩一个，列表则原样保留请求顺序去重后的结果。
     *
     * @param  mixed  $extra  请求体里的 extra（问题文本 => 答案）
     * @return list<array{label: string, value: string}>|null 无有效答案时返回 null，便于后台按「有无额外答案」区分
     */
    protected function extraAnswers(mixed $extra): ?array
    {
        if (! is_array($extra)) {
            return null;
        }

        $answers = [];
        $seen    = [];

        foreach ($extra as $label => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $label = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $label));
            $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value));

            if ($label === '' || $value === '') {
                continue;
            }

            $label = mb_substr($label, 0, self::EXTRA_LABEL_LENGTH);

            // 截断后可能撞名（两个超长问题前 50 字相同），第一个胜出
            if (isset($seen[$label])) {
                continue;
            }

            $seen[$label] = true;
            $answers[]    = [
                'label' => $label,
                'value' => mb_substr($value, 0, self::EXTRA_VALUE_LENGTH),
            ];

            if (count($answers) >= self::MAX_EXTRA_ANSWERS) {
                break;
            }
        }

        return $answers !== [] ? $answers : null;
    }

    /**
     * 从输入里逐键取出归因字段
     *
     * 显式按键取值，不把整段输入摊进 create()——ContactMessage 的 $guarded 为空，
     * 直接展开等于给任意请求字段开了批量赋值入口。
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    protected function attribution(array $input): array
    {
        $data = [];

        foreach (self::ATTRIBUTION_KEYS as $key) {
            $value = $input[$key] ?? null;

            // 列宽：landing_url / referer 是 1024，utm_* 是 255
            $limit = in_array($key, ['landing_url', 'referer'], true) ? 1024 : 255;

            $data[$key] = is_scalar($value) && (string) $value !== ''
                ? mb_substr((string) $value, 0, $limit)
                : null;
        }

        return $data;
    }
}
