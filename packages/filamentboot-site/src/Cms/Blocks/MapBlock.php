<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filamentboot\FilamentbootSite\Support\MapEmbed;

/**
 * 地图区块
 *
 * 给「联系我们」这类页面放一张地图。作者填的是地图服务商生成的**嵌入地址**，
 * iframe 由前台视图自己拼——不接受整段 iframe HTML，理由见 Support\MapEmbed。
 *
 * address 与地图并列而不是二选一：地图 iframe 会被广告拦截插件、企业网络策略
 * 与爬虫全部丢掉，地址文字是那些场景下唯一还能看到的信息，也是搜索引擎能读的那份。
 * 所以 address 不是「地图的说明」，是地图的降级路径。
 */
class MapBlock extends AbstractBlock
{
    /**
     * 可选嵌入高度（像素）
     *
     * 给固定档位而不是自由数字：地图容器高度直接影响首屏与 CLS，
     * 让人随手填 2000 不如给三档合理值。
     *
     * @var array<int, string>
     */
    protected const HEIGHTS = [
        320 => '矮（320px）',
        420 => '中（420px）',
        560 => '高（560px）',
    ];

    public function key(): string
    {
        return 'map';
    }

    public function label(): string
    {
        return '地图';
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
                ->helperText('留空则不渲染标题'),
            TextInput::make('embed_url')
                ->label('地图嵌入地址')
                ->required()
                ->maxLength(1000)
                ->rules([$this->hostRule()])
                ->helperText(
                    '地图服务商「生成代码」里 iframe 的 src 地址（只填地址，不要整段 HTML）。'
                    .'必须是 https，且域名在白名单内：'.implode('、', MapEmbed::allowedHosts())
                ),
            Textarea::make('address')
                ->label('文字地址')
                ->rows(2)
                ->maxLength(200)
                ->helperText('地图被拦截或加载失败时显示，也是搜索引擎能读到的那份，建议填'),
            Select::make('height')
                ->label('高度')
                ->options(self::HEIGHTS)
                ->default(420)
                ->native(false)
                ->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'     => ['nullable', 'string', 'max:120'],
            'embed_url' => ['required', 'string', 'max:1000', $this->hostRule()],
            'address'   => ['nullable', 'string', 'max:200'],
            'height'    => ['required', 'in:'.implode(',', array_keys(self::HEIGHTS))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'     => '',
            'embed_url' => '',
            'address'   => '',
            'height'    => 420,
        ];
    }

    /**
     * 嵌入地址白名单校验规则
     *
     * 保存时就拦住，而不是等渲染时静默不显示：作者填完保存成功、前台却空着一块，
     * 排查成本比当场报错高得多。渲染侧（视图里的 MapEmbed::sanitize）仍然要过一遍——
     * 库里可能躺着白名单收紧之前存下的地址。
     */
    protected function hostRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (MapEmbed::sanitize($value) === null) {
                $fail('地图嵌入地址必须是 https，且域名在白名单内：'.implode('、', MapEmbed::allowedHosts()));
            }
        };
    }
}
