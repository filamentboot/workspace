<?php

namespace Filamentboot\FilamentbootCos\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filamentboot\FilamentbootCos\Settings\CosSettings;
use UnitEnum;

/**
 * 腾讯云 COS 存储配置页面
 *
 * 提供 SecretId/SecretKey/AppId/Bucket/Region 五个配置字段，
 * 超管可在后台直接填写 COS 凭证，无需修改 .env 文件。
 */
class CosSettingsPage extends SettingsPage
{
    protected static string $settings = CosSettings::class;

    protected static ?string $title = 'COS 存储配置';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $navigationLabel = 'COS 配置';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'settings/cos';

    /**
     * 表单字段定义
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('secret_id')
                    ->label('SecretId')
                    ->required(),
                TextInput::make('secret_key')
                    ->label('SecretKey')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('app_id')
                    ->label('AppId')
                    ->required()
                    ->helperText('腾讯云账户 AppId（数字，见控制台账号信息）'),
                TextInput::make('bucket')
                    ->label('Bucket 名称')
                    ->required()
                    ->helperText('不含 AppId 后缀，例如：my-bucket'),
                TextInput::make('region')
                    ->label('Region')
                    ->helperText('示例：ap-guangzhou、ap-shanghai、ap-beijing'),
            ]);
    }
}
