<?php

namespace FilamentAdmin\Filament\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * 个人资料页
 *
 * 扩展 Filament 内置的 EditProfile，替换为自定义字段：
 * - account（账号，唯一）
 * - nickname（昵称）
 * - email（邮箱，唯一）
 * - mobile（手机号）
 * - 修改密码（复用基类的密码组件）
 */
class Profile extends EditProfile
{
    /**
     * 个人资料表单
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('account')
                    ->label('账号')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('nickname')
                    ->label('昵称')
                    ->required()
                    ->maxLength(255),
                $this->getEmailFormComponent(),
                TextInput::make('mobile')
                    ->label('手机号')
                    ->tel()
                    ->maxLength(20),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
