<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filamentboot\FilamentbootSite\Cms\Services\GatedAssetRegistry;

/**
 * 资料索取区块（gated content：手册换联系方式）
 *
 * 访客留下联系方式后才拿到下载链接。这是营销站把「看内容的人」转成「可跟进的线索」
 * 的标准手段，也是本站唯一一处「先给东西、再要东西」反过来的地方。
 *
 * ## 门为什么关得住
 *
 * 资料文件存在 config 的 gated.disk 上（默认 local = storage/app，**Web 根之外**），
 * 前台 HTML 里从头到尾没有文件路径，只有一个不透明 key。提交成功后由服务端
 * 现签一条有时限的下载链接返回——所以「看网页源码直接下走」这条路不通。
 *
 * ⚠️ 宿主若把 gated.disk 指向 public，这道门就形同虚设：文件会有一个人人可猜的
 * /storage/... 地址。config 那一段写明了这件事。
 *
 * ## 与询盘表单区块的区别
 *
 * 那个是「留个联系方式」，这个是「用联系方式换一份资料」。表单控件共用同一份
 * shared/components/contact-form，差别只在多送一个 asset key、成功态多一个下载按钮。
 * 分成两个区块而不是给询盘区块加个开关：后台选区块时「资料索取」是一个能被认出来的
 * 东西，而「询盘表单（勾了附件的那种）」不是。
 */
class GatedDownloadBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'gated-download';
    }

    public function label(): string
    {
        return '资料索取';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('资料名称')
                ->required()
                ->maxLength(120)
                ->helperText('会出现在标题、下载按钮和后台线索里，写清楚是什么，如「全屋智能选型手册 2026」'),
            Textarea::make('description')
                ->label('资料介绍')
                ->rows(3)
                ->maxLength(500)
                ->helperText('说明这份资料能解决什么问题——访客判断值不值得留电话，全靠这段'),
            FileUpload::make('file')
                ->label('资料文件')
                ->required()
                ->disk($this->gatedDisk())
                ->directory('gated-assets')
                ->acceptedFileTypes(self::ACCEPTED_TYPES)
                ->maxSize(20480)
                ->helperText(
                    '上传到非公开磁盘（'.$this->gatedDisk().'），只能通过提交表单后拿到的限时链接下载。'
                    .'最大 20MB，支持 PDF / Word / Excel / PPT / ZIP'
                ),
            TextInput::make('button_label')
                ->label('按钮文字')
                ->maxLength(30)
                ->helperText('留空用「提交后下载」'),
            TextInput::make('source')
                ->label('来源标识')
                ->maxLength(50)
                ->rules(['regex:/^[a-z0-9\-]*$/'])
                ->helperText('区分线索来自哪份资料，只允许小写字母、数字与连字符，如 ebook-selection'),
        ];
    }

    /**
     * 允许上传的 MIME 类型
     *
     * 白名单而不是黑名单，且不放行 html / svg：它们会被浏览器当页面渲染，
     * 而这个磁盘上的文件是要下发给公众的——等于在自己域名下托管别人的 HTML。
     *
     * @var list<string>
     */
    public const ACCEPTED_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:500'],
            'file'         => ['required', 'string', 'max:500'],
            'button_label' => ['nullable', 'string', 'max:30'],
            'source'       => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9\-]*$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'        => '',
            'description'  => '',
            'file'         => '',
            'button_label' => '',
            'source'       => '',
        ];
    }

    /**
     * 本区块声明的资料对应的不透明 key
     *
     * 前台视图拿这个值随表单提交，服务端据此在登记表里查真实路径——
     * **前台从头到尾不出现文件路径**。
     *
     * 文件未上传时返回空串，视图据此退化成普通询盘表单（不出下载按钮），
     * 而不是渲染一个点了拿不到东西的按钮。
     *
     * @param  array<string, mixed>  $data
     */
    public function assetKey(array $data): string
    {
        $path = trim((string) ($data['file'] ?? ''));

        return $path !== '' ? GatedAssetRegistry::key($path) : '';
    }

    /**
     * 资料文件所在磁盘
     *
     * 刻意**不**用 AbstractBlock::defaultDisk()（那是 UploadSettings 的默认磁盘，
     * 通常是 public）：资料放到公开磁盘上，这道门就没有意义了。
     */
    protected function gatedDisk(): string
    {
        return (string) config('filamentboot-site.gated.disk', 'local');
    }
}
