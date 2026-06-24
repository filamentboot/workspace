<?php

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Department;
use Filamentboot\Models\LoginLog;
use Filamentboot\Models\Menu;
use Orchestra\Testbench\TestCase;

/**
 * 批量赋值守卫回归测试（L-03）
 *
 * 验证四个核心模型使用显式 $fillable 白名单后，
 * 受保护字段（id、login_failures、last_login_ip 等）
 * 无法通过批量赋值注入。
 *
 * 使用 Eloquent fill() 而非 create()，避免数据库依赖，
 * 直接断言模型属性是否被赋值。
 */
class MassAssignmentGuardTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentbootServiceProvider::class,
        ];
    }

    /**
     * AdminUser 受保护字段无法通过 fill() 批量赋值注入：
     * id、login_failures、last_login_ip 均不在 $fillable 中。
     */
    public function test_admin_user_guarded_fields_are_not_mass_assignable(): void
    {
        $user = new AdminUser;
        $user->fill([
            'account'  => 'testuser',
            'email'    => 'test@example.com',
            'password' => 'secret',
            'nickname' => '测试用户',
            // 以下为受保护字段，不应被注入
            'id'             => 999,
            'login_failures' => 99,
            'last_login_ip'  => '1.2.3.4',
            'last_login_at'  => '2024-01-01 00:00:00',
        ]);

        // 正常字段应被填充
        $this->assertSame('testuser', $user->account);
        $this->assertSame('test@example.com', $user->email);

        // 受保护字段不应被注入
        $this->assertNull($user->id, 'id 不在 $fillable 中，不应被批量赋值注入');
        $this->assertNull($user->getAttribute('login_failures'), 'login_failures 不在 $fillable 中，不应被批量赋值注入');
        $this->assertNull($user->getAttribute('last_login_ip'), 'last_login_ip 不在 $fillable 中，不应被批量赋值注入');
        $this->assertNull($user->getAttribute('last_login_at'), 'last_login_at 不在 $fillable 中，不应被批量赋值注入');
    }

    /**
     * Menu 受保护字段无法通过 fill() 批量赋值注入：
     * id 不在 $fillable 中。
     */
    public function test_menu_guarded_fields_are_not_mass_assignable(): void
    {
        $menu = new Menu;
        $menu->fill([
            'title'  => '测试菜单',
            'source' => 'core',
            'type'   => 'menu',
            // 受保护字段
            'id' => 888,
        ]);

        // 正常字段应被填充
        $this->assertSame('测试菜单', $menu->title);
        // id 不在 $fillable 中，不应被注入
        $this->assertNull($menu->id, 'id 不在 $fillable 中，不应被批量赋值注入');
    }

    /**
     * Department 受保护字段无法通过 fill() 批量赋值注入：
     * id 不在 $fillable 中。
     */
    public function test_department_guarded_fields_are_not_mass_assignable(): void
    {
        $dept = new Department;
        $dept->fill([
            'name' => '研发部',
            'code' => 'RD',
            'sort' => 1,
            // 受保护字段
            'id' => 777,
        ]);

        // 正常字段应被填充
        $this->assertSame('研发部', $dept->name);
        // id 不在 $fillable 中，不应被注入
        $this->assertNull($dept->id, 'id 不在 $fillable 中，不应被批量赋值注入');
    }

    /**
     * LoginLog 受保护字段无法通过 fill() 批量赋值注入：
     * id、created_at 不在 $fillable 中。
     */
    public function test_login_log_guarded_fields_are_not_mass_assignable(): void
    {
        $log = new LoginLog;
        $log->fill([
            'username'   => 'admin',
            'status'     => 'failed',
            'ip_address' => '127.0.0.1',
            // 受保护字段
            'id' => 666,
        ]);

        // 正常字段应被填充
        $this->assertSame('admin', $log->username);
        $this->assertSame('failed', $log->status);
        // id 不在 $fillable 中，不应被注入
        $this->assertNull($log->id, 'id 不在 $fillable 中，不应被批量赋值注入');
    }
}
