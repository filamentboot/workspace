<?php

namespace Filamentboot\FilamentbootSite\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filamentboot\FilamentbootSite\Services\SiteHealthCheck;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * 官网设置页面
 *
 * 管理公司基本信息、SEO 默认值、主题切换及媒体上传（D-10-14）。
 * CMS v1 为中文单语言，表单不再提供英文字段（数据库列保留兼容既有数据）。
 *
 * 保存后执行 view:clear 确保主题切换立即生效（RESEARCH Pitfall 3）。
 * 上传字段使用 UploadSettings.default_disk 磁盘（SITE-04 跨切）。
 */
class SiteSettingsPage extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = '网站设置';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings/site';

    /**
     * 进入页面时提示尚未配置的发布前必填项
     */
    public function mount(): void
    {
        parent::mount();

        $this->notifyMissingSettings();
    }

    /**
     * 获取上传默认磁盘名称
     *
     * 读取 UploadSettings.default_disk，降级到 'public'（T-10-01-02 防护）。
     */
    protected function getDefaultDisk(): string
    {
        return rescue(
            fn () => app(UploadSettings::class)->default_disk,
            'public',
            report: false,
        );
    }

    /**
     * 可选主题列表（读 config 白名单，与前台视图解析同源）
     *
     * @return array<string, string>
     */
    protected function getThemeOptions(): array
    {
        /** @var array<string, string> $themes */
        $themes = config('filamentboot-site.themes', []);

        return $themes;
    }

    /**
     * 表单字段定义（基本信息 / SEO / 外观 三 Tab）
     */
    public function form(Schema $schema): Schema
    {
        $defaultDisk = $this->getDefaultDisk();

        return $schema->components([
            Tabs::make('网站设置')
                ->tabs([
                    Tab::make('基本信息')
                        ->schema([
                            Section::make('公司信息')
                                ->description('页脚、导航与结构化数据都会读取这里的内容，缺项会导致前台对应栏目整块不渲染。')
                                ->schema([
                                    TextInput::make('company_name_zh')
                                        ->label('公司名称')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('phone')
                                        ->label('联系电话')
                                        ->tel()
                                        ->maxLength(50)
                                        ->helperText('未填写时前台页脚不显示电话入口'),
                                    TextInput::make('address_zh')
                                        ->label('公司地址')
                                        ->maxLength(500),
                                    TextInput::make('icp_number')
                                        ->label('ICP 备案号')
                                        ->maxLength(100)
                                        ->helperText('国内站点对外发布必填'),
                                    TextInput::make('privacy_url')
                                        ->label('隐私政策链接')
                                        ->url()
                                        ->maxLength(500)
                                        ->helperText('填写后在页脚显示「隐私政策」入口'),
                                    FileUpload::make('wechat_qrcode')
                                        ->label('微信二维码')
                                        ->image()
                                        ->disk($defaultDisk)
                                        ->helperText('上传微信公众号或企业微信二维码图片'),
                                ]),
                        ]),
                    Tab::make('SEO 默认值')
                        ->schema([
                            Section::make('搜索与分享')
                                ->description('内容自身未填写 SEO 字段时的回退值。默认描述留空会退到包内兜底文案。')
                                ->schema([
                                    TextInput::make('seo_default_title_zh')
                                        ->label('默认页面标题')
                                        ->maxLength(70)
                                        ->helperText('建议不超过 70 个字符'),
                                    Textarea::make('seo_default_description_zh')
                                        ->label('默认页面描述')
                                        ->maxLength(160)
                                        ->rows(3)
                                        ->helperText('建议不超过 160 个字符'),
                                    FileUpload::make('og_default_image')
                                        ->label('默认 Open Graph 图')
                                        ->image()
                                        ->disk($defaultDisk)
                                        ->helperText('社交平台分享时的缩略图，建议 1200×630。未上传时前台不输出 og:image'),
                                ]),
                        ]),
                    Tab::make('外观')
                        ->schema([
                            Section::make('主题与品牌')
                                ->schema([
                                    Select::make('active_theme')
                                        ->label('前台主题')
                                        ->options($this->getThemeOptions())
                                        ->required()
                                        ->native(false)
                                        ->helperText('切换主题后自动清除视图缓存，下次访问即生效'),
                                    FileUpload::make('logo')
                                        ->label('公司 LOGO')
                                        ->image()
                                        ->disk($defaultDisk)
                                        ->helperText('建议使用 SVG 或 PNG 格式，透明背景'),
                                ]),
                        ]),
                ]),
        ]);
    }

    /**
     * 保存后清除视图缓存并复检发布前必填项（RESEARCH Pitfall 3）
     *
     * 主题切换后需清除视图缓存，确保新主题立即生效。
     */
    protected function afterSave(): void
    {
        Artisan::call('view:clear');

        $this->notifyMissingSettings();
    }

    /**
     * 缺少发布前必填项时给出可操作告警
     */
    protected function notifyMissingSettings(): void
    {
        $missing = app(SiteHealthCheck::class)->missing();

        if ($missing === []) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('站点尚未达到发布标准')
            ->body('还需补齐：'.implode('、', $missing).'。未配置的栏目不会在前台渲染。')
            ->persistent()
            ->send();
    }
}
