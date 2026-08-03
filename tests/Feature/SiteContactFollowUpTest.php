<?php

use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Filament\Exporters\ContactMessageExporter;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Models\ContactMessageNote;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 询盘跟进与导出测试（A4）
 *
 * 覆盖场景：
 * - 跟进备注时间线的关系、级联与置空语义
 * - 跟进人分配
 * - 导出列包含 A1 的来源与 UTM 字段
 *
 * 本文件只覆盖模型层语义；后台页面渲染与 Action 交互见
 * SiteContactResourcePageTest（那里手工把插件注册进面板并重跑 Filament 路由，
 * 以绕开「资源路由在 boot 时注册、测试库那时还没有 plugins 数据」的先后顺序问题）。
 *
 * @group site
 */

/**
 * 跟进备注写入并关联记录人与询盘
 */
it('跟进备注关联询盘与记录人', function () {
    $author = AdminUser::factory()->create(['nickname' => '销售小王']);

    $message = ContactMessage::create([
        'name'    => '张三',
        'phone'   => '13800138000',
        'message' => '咨询全屋智能',
        'status'  => ContactMessageStatus::UNREAD,
    ]);

    ContactMessageNote::create([
        'message_id'    => $message->getKey(),
        'admin_user_id' => $author->getKey(),
        'body'          => '已电话联系，客户希望下周三上门量房。',
    ]);

    $message->refresh();

    expect($message->notes)->toHaveCount(1)
        ->and($message->notes->first()->body)->toBe('已电话联系，客户希望下周三上门量房。')
        ->and($message->notes->first()->author->nickname)->toBe('销售小王');
});

/**
 * 跟进时间线按时间倒序返回（最新的跟进在最上面）
 */
it('跟进时间线按时间倒序返回', function () {
    $message = ContactMessage::create([
        'name'    => '李四',
        'phone'   => '13900139000',
        'message' => '',
        'status'  => ContactMessageStatus::UNREAD,
    ]);

    foreach (['第一次联系', '第二次联系', '第三次联系'] as $body) {
        ContactMessageNote::create(['message_id' => $message->getKey(), 'body' => $body]);
    }

    expect($message->refresh()->notes->pluck('body')->all())
        ->toBe(['第三次联系', '第二次联系', '第一次联系']);
});

/**
 * 跟进人可分配并通过关系读回
 */
it('询盘可分配跟进人', function () {
    $assignee = AdminUser::factory()->create(['nickname' => '销售小李']);

    $message = ContactMessage::create([
        'name'        => '王五',
        'phone'       => '13700137000',
        'message'     => '',
        'status'      => ContactMessageStatus::CONTACTED,
        'assigned_to' => $assignee->getKey(),
    ]);

    expect($message->refresh()->assignee->nickname)->toBe('销售小李');
});

/**
 * 记录人被删除后备注保留，仅记录人置空
 *
 * 线索与跟进记录是业务资产，不能因人员离职而消失。
 */
it('记录人删除后跟进备注仍保留', function () {
    $message = ContactMessage::create([
        'name'    => '赵六',
        'phone'   => '13600136000',
        'message' => '',
        'status'  => ContactMessageStatus::UNREAD,
    ]);

    $author = AdminUser::factory()->create();

    $note = ContactMessageNote::create([
        'message_id'    => $message->getKey(),
        'admin_user_id' => $author->getKey(),
        'body'          => '客户要求周末回电',
    ]);

    $author->forceDelete();

    expect($note->refresh()->body)->toBe('客户要求周末回电')
        ->and($note->admin_user_id)->toBeNull();
});

/**
 * 跟进人被删除后询盘保留，仅跟进人置空
 */
it('跟进人删除后询盘仍保留', function () {
    $assignee = AdminUser::factory()->create();

    $message = ContactMessage::create([
        'name'        => '孙七',
        'phone'       => '13500135000',
        'message'     => '',
        'status'      => ContactMessageStatus::CONTACTED,
        'assigned_to' => $assignee->getKey(),
    ]);

    $assignee->forceDelete();

    expect($message->refresh()->assigned_to)->toBeNull()
        ->and($message->name)->toBe('孙七');
});

/**
 * 询盘被删除时其跟进备注级联清理（备注离开询盘没有意义）
 */
it('询盘删除时跟进备注级联清理', function () {
    $message = ContactMessage::create([
        'name'    => '周八',
        'phone'   => '13400134000',
        'message' => '',
        'status'  => ContactMessageStatus::UNREAD,
    ]);

    ContactMessageNote::create([
        'message_id' => $message->getKey(),
        'body'       => '待跟进',
    ]);

    $message->delete();

    expect(ContactMessageNote::count())->toBe(0);
});

/**
 * 导出列包含来源与全部 UTM 字段
 *
 * 导出的用途就是把线索连同渠道信息交给投放侧核算效果，
 * 漏掉归因列等于导出一份无法归因的名单。
 */
it('导出列包含来源与渠道字段', function () {
    $names = collect(ContactMessageExporter::getColumns())
        ->map(fn ($column): string => $column->getName())
        ->all();

    expect($names)->toContain(
        'name',
        'phone',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'landing_url',
        'referer',
        'assignee.nickname',
    );
});
