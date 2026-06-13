<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 装修案例资源测试桩（SiteCaseResourceTest）
 *
 * Wave 0 安全网测试：
 * - test_site_cases_table_exists：由 Plan 10-02（数据层迁移）落地转绿
 * - test_site_case_factory_persists：由 Plan 10-02（SiteCase 模型工厂）落地转绿
 * - test_contact_message_default_status_unread：由 Plan 10-02（ContactMessage 模型）落地转绿
 * - test_site_case_resource_registered_and_list_accessible：由 Plan 10-03（Filament Resource）落地转绿
 *
 * @group site
 * @covers \LaravelStack\FilamentAdminSite\Models\SiteCase
 */
class SiteCaseResourceTest extends TestCase
{
    /**
     * 目标可观测信号：php artisan migrate 后 Schema::hasTable('site_cases') 为 true
     * 由 Plan 10-02 创建 create_site_cases_table 迁移后落地转绿。
     */
    public function test_site_cases_table_exists(): void
    {
        $this->markTestIncomplete(
            '待 10-02 落地：php artisan migrate 后 Schema::hasTable(\'site_cases\') 应为 true'
        );
    }

    /**
     * 目标可观测信号：SiteCase::factory()->create() 成功落库，DB 可查询到记录
     * 由 Plan 10-02 创建 SiteCase 模型与 SiteCaseFactory 后落地转绿。
     */
    public function test_site_case_factory_persists(): void
    {
        $this->markTestIncomplete(
            '待 10-02 落地：SiteCase::factory()->create() 应成功落库并可通过 DB 查询到记录'
        );
    }

    /**
     * 目标可观测信号：ContactMessage::factory()->create() 后 status 默认为 'unread'
     * 由 Plan 10-02 创建 ContactMessage 模型与默认 status 后落地转绿。
     */
    public function test_contact_message_default_status_unread(): void
    {
        $this->markTestIncomplete(
            '待 10-02 落地：ContactMessage 默认 status 应为 \'unread\'（询盘状态流转初始值）'
        );
    }

    /**
     * 目标可观测信号：SitePlugin::register() 后 SiteCaseResource 已注册，
     * 认证 admin 访问 /admin/site-cases 列表页返回 200
     * 由 Plan 10-03 实现 SiteCaseResource 并在 SitePlugin::register() 注册后落地转绿。
     */
    public function test_site_case_resource_registered_and_list_accessible(): void
    {
        $this->markTestIncomplete(
            '待 10-03 落地：SitePlugin register() 注册 SiteCaseResource 后，认证 admin 访问列表页应返回 200'
        );
    }
}
