<?php

namespace FilamentAdmin\Filament\Pages\Settings;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use FilamentAdmin\Settings\GeneralSettings;
use UnitEnum;

/**
 * 基础配置页面
 *
 * 管理站点名称、后台标题、备案信息等基础设置。
 */
class GeneralSettingsPage extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = '基础配置';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort  = 1;

    protected static ?string $slug            = 'settings/general';

    /**
     * 表单字段定义
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('site_name')
                    ->label('站点名称')
                    ->required()
                    ->maxLength(100),
                TextInput::make('admin_title')
                    ->label('后台标题')
                    ->required()
                    ->maxLength(100),
                TextInput::make('icp_number')
                    ->label('备案号')
                    ->maxLength(50),
                TextInput::make('copyright')
                    ->label('版权信息')
                    ->maxLength(200),
            ]);
    }
}
