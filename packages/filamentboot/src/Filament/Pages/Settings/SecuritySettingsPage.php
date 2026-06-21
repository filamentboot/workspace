<?php

namespace FilamentAdmin\Filament\Pages\Settings;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use FilamentAdmin\Settings\SecuritySettings;
use UnitEnum;

/**
 * 安全配置页面
 *
 * 管理登录失败限制、锁定时长、双因素认证强制开关。
 */
class SecuritySettingsPage extends SettingsPage
{
    protected static string $settings = SecuritySettings::class;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = '安全配置';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort  = 3;

    protected static ?string $slug            = 'settings/security';

    /**
     * 表单字段定义
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('login_throttle_max_attempts')
                    ->label('登录失败限制次数')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText('0 表示不限制'),
                TextInput::make('login_throttle_decay_minutes')
                    ->label('锁定时长（分钟）')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                Toggle::make('force_2fa')
                    ->label('强制启用双因素认证'),
            ]);
    }
}
