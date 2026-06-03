<?php

namespace FilamentAdmin\Filament\Pages\Settings;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use FilamentAdmin\Settings\UploadSettings;
use UnitEnum;

/**
 * 上传配置页面
 *
 * 管理文件上传大小限制、允许类型、默认存储磁盘。
 */
class UploadSettingsPage extends SettingsPage
{
    protected static string $settings = UploadSettings::class;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = '上传配置';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort  = 2;

    protected static ?string $slug            = 'settings/upload';

    /**
     * 表单字段定义
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('max_file_size')
                    ->label('最大文件大小（KB）')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('allowed_mimes')
                    ->label('允许的文件类型（逗号分隔扩展名）')
                    ->required()
                    ->helperText('例如：jpg,jpeg,png,pdf'),
                Select::make('default_disk')
                    ->label('默认存储磁盘')
                    ->options([
                        'public' => 'public（本地公开）',
                        'local'  => 'local（本地私有）',
                    ])
                    ->required(),
            ]);
    }
}
