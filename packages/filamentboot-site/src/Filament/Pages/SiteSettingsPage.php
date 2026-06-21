<?php

namespace Filamentboot\FilamentbootSite\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Support\Facades\Artisan;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use UnitEnum;

/**
 * 官网设置页面
 *
 * 管理公司基本信息、SEO 默认值、主题切换及 LOGO/微信二维码上传（D-10-14）。
 * 保存后执行 view:clear 确保主题切换立即生效（RESEARCH Pitfall 3）。
 *
 * 上传字段（logo/wechat_qrcode）使用 UploadSettings.default_disk 磁盘（SITE-04 跨切）。
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
     * 表单字段定义（三 Tab：基本信息/SEO 默认值/外观）
     */
    public function form(Schema $schema): Schema
    {
        $defaultDisk = $this->getDefaultDisk();

        return $schema->components([
            Tabs::make('网站设置')
                ->tabs([
                    Tab::make('基本信息')
                        ->schema([
                            TextInput::make('company_name_zh')
                                ->label('公司名称（中文）')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('company_name_en')
                                ->label('公司名称（英文）')
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('联系电话')
                                ->maxLength(50),
                            TextInput::make('phone_en')
                                ->label('联系电话（英文）')
                                ->maxLength(50),
                            TextInput::make('address_zh')
                                ->label('公司地址（中文）')
                                ->maxLength(500),
                            TextInput::make('address_en')
                                ->label('公司地址（英文）')
                                ->maxLength(500),
                            TextInput::make('icp_number')
                                ->label('ICP 备案号')
                                ->maxLength(100),
                            // 微信二维码上传字段（D-10-14）
                            // 使用 UploadSettings.default_disk 磁盘（SITE-04 跨切）
                            FileUpload::make('wechat_qrcode')
                                ->label('微信二维码')
                                ->image()
                                ->disk($defaultDisk)
                                ->helperText('上传微信公众号或企业微信二维码图片'),
                        ]),
                    Tab::make('SEO 默认值')
                        ->schema([
                            TextInput::make('seo_default_title_zh')
                                ->label('默认页面标题（中文）')
                                ->maxLength(70)
                                ->helperText('建议不超过 70 个字符'),
                            TextInput::make('seo_default_title_en')
                                ->label('默认页面标题（英文）')
                                ->maxLength(70),
                            Textarea::make('seo_default_description_zh')
                                ->label('默认页面描述（中文）')
                                ->maxLength(160)
                                ->rows(3)
                                ->helperText('建议不超过 160 个字符'),
                            Textarea::make('seo_default_description_en')
                                ->label('默认页面描述（英文）')
                                ->maxLength(160)
                                ->rows(3),
                        ]),
                    Tab::make('外观')
                        ->schema([
                            Select::make('active_theme')
                                ->label('前台主题')
                                ->options([
                                    'decoration' => '科技装修（深色）',
                                    'tech-product' => '科技产品（浅色）',
                                ])
                                ->required()
                                ->helperText('切换主题后需清除视图缓存（保存时自动执行）'),
                            // 公司 LOGO 上传字段（D-10-14）
                            // 使用 UploadSettings.default_disk 磁盘（SITE-04 跨切）
                            FileUpload::make('logo')
                                ->label('公司 LOGO')
                                ->image()
                                ->disk($defaultDisk)
                                ->helperText('建议使用 SVG 或 PNG 格式，透明背景'),
                        ]),
                ]),
        ]);
    }

    /**
     * 保存后清除视图缓存（RESEARCH Pitfall 3）
     *
     * 主题切换后需清除视图缓存，确保新主题立即生效。
     */
    protected function afterSave(): void
    {
        Artisan::call('view:clear');
    }
}
