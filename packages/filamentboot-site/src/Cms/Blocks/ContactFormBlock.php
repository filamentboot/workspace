<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

/**
 * 内嵌询盘表单区块（#12）
 *
 * 把询盘表单直接放进页面正文，用于落地页这种「不希望访客再去点悬浮按钮」的场景。
 *
 * source 是 A1 的转化入口标识：同一个表单出现在不同页面时，
 * 靠它区分线索是从哪个落地页进来的。字符集与 ContactSubmission::normalizedSource()
 * 的过滤规则保持一致，否则填了也会被入库时剥掉。
 *
 * ## 额外问题（不同活动问不同问题）
 *
 * 姓名 / 电话 / 留言三项固定；额外问题由本区块配置，答案存进
 * site_contact_messages.extra（键即问题文本）。
 *
 * ⚠️ **必填只在浏览器里生效。** 提交端点是无状态的（#29：公开页零 session），
 * 它收到的只是一份键值对，无从知道是哪一份区块配置渲染出来的表单——除非把配置
 * 连签名一起随请求发出，而那要么引入随机数（毁掉整页缓存的确定性），要么再加一套
 * HMAC 校验。服务端因此只做**边界**约束（条数、键长、值长，见 Services\ContactSubmission），
 * 不做「这个问题必须答」的判断。绕过必填的代价是收到一条答得不全的线索，
 * 不是数据被污染；而每在提交链路上多加一个环节，就多一处可能静默丢线索的地方。
 */
class ContactFormBlock extends AbstractBlock
{
    /**
     * 额外问题条数上限
     *
     * 六个已经很多了。表单每多一个字段转化率就掉一截，这个上限存在的意义
     * 一半是防呆、一半是给服务端的边界校验一个对应的数（见 ContactSubmission）。
     */
    public const MAX_FIELDS = 6;

    /**
     * 单个下拉的选项上限
     */
    public const MAX_OPTIONS = 20;

    /**
     * 支持的答题方式
     *
     * 刻意不做多选与文件上传：多选的答案是数组，落进 extra 后后台展示与导出
     * 都要多一层摊平；文件上传要处理存储、清理与病毒面，都不是「多问一个问题」
     * 该顺带引入的东西。
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'text'     => '单行文本',
        'textarea' => '多行文本',
        'select'   => '下拉选择',
    ];

    public function key(): string
    {
        return 'contact-form';
    }

    public function label(): string
    {
        return '询盘表单';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('标题')
                ->maxLength(120)
                ->default('留下联系方式'),
            Textarea::make('description')
                ->label('说明文字')
                ->rows(2)
                ->maxLength(300),
            TextInput::make('source')
                ->label('来源标识')
                ->maxLength(50)
                ->rules(['regex:/^[a-z0-9\-]*$/'])
                ->helperText('用于区分线索来自哪个页面，只允许小写字母、数字与连字符，如 landing-spring'),
            Repeater::make('fields')
                ->label('额外问题')
                ->helperText(
                    '姓名、电话、留言三项固定存在，这里配的是本次活动想额外问的问题，'
                    .'最多 '.self::MAX_FIELDS.' 个。每多问一个问题都会掉转化率，只问真正会影响跟进动作的。'
                )
                ->addActionLabel('加一个问题')
                ->maxItems(self::MAX_FIELDS)
                ->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->default([])
                ->schema([
                    TextInput::make('label')
                        ->label('问题')
                        ->required()
                        ->maxLength(50)
                        ->helperText('同时是后台看到的字段名，别用两个一样的'),
                    Select::make('type')
                        ->label('答题方式')
                        ->options(self::TYPES)
                        ->default('text')
                        ->native(false)
                        ->required()
                        ->live(),
                    Textarea::make('options')
                        ->label('可选项')
                        ->rows(3)
                        ->maxLength(500)
                        ->visible(fn (Get $get): bool => $get('type') === 'select')
                        ->helperText('一行一个，最多 '.self::MAX_OPTIONS.' 个'),
                    Toggle::make('required')
                        ->label('必填')
                        ->default(false)
                        ->helperText('⚠️ 只在浏览器里生效：本站表单走无状态端点，服务端无从核对是哪份表单配置提交的，因此必填不会在服务端二次校验'),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'             => ['nullable', 'string', 'max:120'],
            'description'       => ['nullable', 'string', 'max:300'],
            'source'            => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9\-]*$/'],
            'fields'            => ['nullable', 'array', 'max:'.self::MAX_FIELDS],
            'fields.*.label'    => ['required', 'string', 'max:50'],
            'fields.*.type'     => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'fields.*.options'  => ['nullable', 'string', 'max:500'],
            'fields.*.required' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'       => '留下联系方式',
            'description' => '',
            'source'      => '',
            'fields'      => [],
        ];
    }

    /**
     * 把区块 payload 里的额外问题整理成视图能直接渲染的形状
     *
     * 放在区块类而不是视图里：两套主题的区块视图都要用，且「一行一个选项」
     * 这种解析逻辑写在 Blade 里没法单测。
     *
     * 非法条目直接丢弃而不是修正：一个配错的问题不显示，比显示一个没有选项的
     * 下拉框（访客选不了、必填还提交不了）好。
     *
     * @param  array<string, mixed>  $data  区块 data 部分
     * @return list<array{label: string, type: string, required: bool, options: list<string>}>
     */
    public function normalizedFields(array $data): array
    {
        $fields = $data['fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        $normalized = [];
        $seen       = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $type  = (string) ($field['type'] ?? 'text');

            // 重名会让后台答案互相覆盖（键就是问题文本），后来的那个丢掉
            if ($label === '' || isset($seen[$label]) || ! isset(self::TYPES[$type])) {
                continue;
            }

            $options = $type === 'select' ? $this->parseOptions($field['options'] ?? null) : [];

            // 下拉却没有可选项 = 访客选不了，整条丢掉
            if ($type === 'select' && $options === []) {
                continue;
            }

            $seen[$label]  = true;
            $normalized[]  = [
                'label'    => mb_substr($label, 0, 50),
                'type'     => $type,
                'required' => (bool) ($field['required'] ?? false),
                'options'  => $options,
            ];

            if (count($normalized) >= self::MAX_FIELDS) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * 解析「一行一个」的选项文本
     *
     * @return list<string>
     */
    protected function parseOptions(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        $options = [];

        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && ! in_array($line, $options, true)) {
                $options[] = mb_substr($line, 0, 100);
            }

            if (count($options) >= self::MAX_OPTIONS) {
                break;
            }
        }

        return $options;
    }
}
