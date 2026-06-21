<?php

namespace FilamentAdmin\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * 自定义登录页
 *
 * 支持 account 或 email 登录，
 * 使用统一错误消息防止用户枚举攻击。
 */
class Login extends BaseLogin
{
    /**
     * 自定义表单：以 login 字段代替 email 字段
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('login')
                    ->label('账号或邮箱')
                    ->required()
                    ->autofocus()
                    ->autocomplete('username'),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    /**
     * 获取认证凭据
     *
     * 根据输入自动判断是 email 还是 account。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login     = $data['login'];
        $loginType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'account';

        return [
            $loginType => $login,
            'password' => $data['password'],
        ];
    }

    /**
     * 自定义认证失败异常（防止用户枚举攻击）
     *
     * 对"不存在的用户"和"密码错误"返回相同的错误消息。
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => '用户名/邮箱或密码错误',
        ]);
    }
}
