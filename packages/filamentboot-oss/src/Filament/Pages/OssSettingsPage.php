<?php

namespace Filamentboot\FilamentbootOss\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filamentboot\FilamentbootOss\Settings\OssSettings;
use UnitEnum;

/**
 * 阿里云 OSS 存储后台配置页面
 *
 * 提供 AccessKeyId / AccessKeySecret / Bucket / Endpoint / Region 五个配置字段，
 * 凭证经 OssSettings 加密存储在 settings 表。
 */
class OssSettingsPage extends SettingsPage
{
    /** 关联的 Settings 类 */
    protected static string $settings = OssSettings::class;

    /** 页面标题 */
    protected static ?string $title = 'OSS 存储配置';

    /** 导航图标 */
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud-arrow-up';

    /** 导航标签 */
    protected static ?string $navigationLabel = 'OSS 配置';

    /** 导航分组 */
    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    /** 导航排序权重 */
    protected static ?int $navigationSort = 10;

    /** 页面路由 slug */
    protected static ?string $slug = 'settings/oss';

    /**
     * 表单字段定义（5 个 OSS 凭证字段）
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('access_key_id')
                    ->label('AccessKeyId')
                    ->required(),
                TextInput::make('access_key_secret')
                    ->label('AccessKeySecret')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('bucket')
                    ->label('Bucket 名称')
                    ->required(),
                TextInput::make('endpoint')
                    ->label('Endpoint')
                    ->required()
                    ->helperText('示例：oss-cn-hangzhou.aliyuncs.com'),
                TextInput::make('region')
                    ->label('Region')
                    ->helperText('示例：cn-hangzhou'),
            ]);
    }
}
