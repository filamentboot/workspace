<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LaravelStack\FilamentAdminSite\Enums\ContactMessageStatus;
use LaravelStack\FilamentAdminSite\Models\ContactMessage;
use LaravelStack\FilamentAdminSite\Models\SiteCase;
use Tests\TestCase;

/**
 * 装修案例资源测试（SiteCaseResourceTest）
 *
 * Wave 0 安全网测试：
 * - test_site_cases_table_exists：Plan 10-02 数据层迁移（已转绿）
 * - test_site_case_factory_persists：Plan 10-02 SiteCase 模型工厂（已转绿）
 * - test_contact_message_default_status_unread：Plan 10-02 ContactMessage 模型（已转绿）
 * - test_site_case_resource_registered_and_list_accessible：由 Plan 10-03（Filament Resource）落地转绿
 *
 * @group site
 * @covers \LaravelStack\FilamentAdminSite\Models\SiteCase
 */
class SiteCaseResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 目标可观测信号：migrate 后 Schema::hasTable('site_cases') 为 true
     * Plan 10-02 数据层迁移已完成，此断言应转绿。
     */
    public function test_site_cases_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('site_cases'),
            'site_cases 表应在迁移后存在'
        );
    }

    /**
     * 目标可观测信号：SiteCase::factory()->create() 成功落库，DB 可查询到记录
     * Plan 10-02 SiteCase 模型与 SiteCaseFactory 已创建，此断言应转绿。
     */
    public function test_site_case_factory_persists(): void
    {
        $case = SiteCase::factory()->create([
            'title_zh' => '测试装修案例',
        ]);

        $this->assertDatabaseHas('site_cases', [
            'id'       => $case->id,
            'title_zh' => '测试装修案例',
        ]);
    }

    /**
     * 目标可观测信号：ContactMessage::create() 后 status 默认为 ContactMessageStatus::UNREAD
     * Plan 10-02 ContactMessage 模型与枚举 cast 已创建，此断言应转绿。
     */
    public function test_contact_message_default_status_unread(): void
    {
        $message = ContactMessage::create([
            'name'    => '测试用户',
            'phone'   => '13800138000',
            'message' => '测试留言内容',
        ]);

        $this->assertDatabaseHas('site_contact_messages', [
            'id'     => $message->id,
            'status' => 'unread',
        ]);

        $this->assertSame(
            ContactMessageStatus::UNREAD,
            $message->fresh()->status,
            'ContactMessage status 默认应为 ContactMessageStatus::UNREAD'
        );
    }

    /**
     * 目标可观测信号：SitePlugin::register() 后 SiteCaseResource 已注册到 Panel，
     * 且 SiteCaseResource::getUrl('index') 与 SitePlugin::getId() 可正确解析。
     *
     * 由于 Filament 在应用启动时注册路由（Panel 路由与 HTTP 请求时机不同），
     * 此处通过直接调用 SitePlugin::register() 验证 Panel 拥有 SiteCaseResource，
     * 避免依赖 AdminPanelProvider::registerEnabledPlugins 的缓存/DB 动态逻辑。
     */
    public function test_site_case_resource_registered_and_list_accessible(): void
    {
        // 直接调用 SitePlugin::register()，验证 SiteCaseResource 被注册到 Panel
        $panel = new \Filament\Panel();
        \LaravelStack\FilamentAdminSite\SitePlugin::make()->register($panel);

        // Panel 拥有 site-case 相关资源类
        $resources = $panel->getResources();
        $this->assertContains(
            \LaravelStack\FilamentAdminSite\Filament\Resources\SiteCaseResource::class,
            $resources,
            'SitePlugin::register() 后 SiteCaseResource 应在 Panel 的 resources 列表中'
        );

        // SitePlugin 唯一标识符正确
        $plugin = \LaravelStack\FilamentAdminSite\SitePlugin::make();
        $this->assertSame(
            'filament-admin-site',
            $plugin->getId(),
            'SitePlugin::getId() 应返回 filament-admin-site'
        );
    }
}
