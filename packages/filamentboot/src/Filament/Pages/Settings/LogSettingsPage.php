<?php

namespace FilamentAdmin\Filament\Pages\Settings;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use FilamentAdmin\Settings\LogSettings;
use UnitEnum;

/**
 * 日志配置页面
 *
 * 管理操作日志和登录日志的保留天数。
 */
class LogSettingsPage extends SettingsPage
{
    protected static string $settings = LogSettings::class;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '日志配置';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort  = 4;

    protected static ?string $slug            = 'settings/log';

    /**
     * 表单字段定义
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('activity_log_retention_days')
                    ->label('操作日志保留天数')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText('0 表示永久保留'),
                TextInput::make('login_log_retention_days')
                    ->label('登录日志保留天数')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText('0 表示永久保留'),
            ]);
    }
}
