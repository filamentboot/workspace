<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理员 API 登录请求校验
 *
 * 支持 account（账号）或 email（邮箱）登录。
 */
class LoginRequest extends FormRequest
{
    /**
     * 所有 API 请求均允许（认证在控制器中处理）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 校验规则
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account'  => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    /**
     * 字段显示名称
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account'  => '账号或邮箱',
            'password' => '密码',
        ];
    }
}
