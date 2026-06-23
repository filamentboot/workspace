<?php

use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Http\Livewire\ContactForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// 注册 filamentboot-site 视图命名空间指向包内视图目录（10-04 测试阶段，10-05 完整主题视图替代）
beforeEach(function () {
    $viewsPath = base_path('packages/filamentboot-site/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('filamentboot-site', $viewsPath);
    }
});

/**
 * 询盘表单测试（ContactFormTest）
 *
 * 覆盖场景：
 * - 表单提交后持久化到 site_contact_messages，status=UNREAD（D-10-15）
 * - 同 IP 超过 3 次提交触发速率限制，不写入新记录（D-10-15 安全，T-10-04-02）
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Http\Livewire\ContactForm
 */

/**
 * 询盘表单提交后持久化到数据库且默认状态为未读（D-10-15）
 */
it('询盘表单提交后持久化到数据库且默认状态为未读', function () {
    // 清除该测试的速率限制器（避免测试间干扰）
    RateLimiter::clear(
        'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1')
    );

    Livewire::test(ContactForm::class)
        ->set('name', '张三')
        ->set('phone', '13800138000')
        ->set('message', '我想了解你们的智能家居方案，请与我联系。')
        ->call('submit');

    // 断言 site_contact_messages 表新增了一条记录
    $this->assertDatabaseCount('site_contact_messages', 1);

    // 断言记录内容正确
    $this->assertDatabaseHas('site_contact_messages', [
        'name'   => '张三',
        'phone'  => '13800138000',
        'status' => ContactMessageStatus::UNREAD->value,
    ]);
});

/**
 * 询盘表单超过速率限制后拒绝提交，不写入新记录（D-10-15 安全，T-10-04-02）
 */
it('询盘表单超过速率限制后拒绝提交', function () {
    $rateLimitKey = 'livewire-rate-limiter:'.sha1(ContactForm::class.'|submit|127.0.0.1');

    // 清除速率限制，确保干净环境
    RateLimiter::clear($rateLimitKey);

    // 连续提交 3 次（达到上限），每次都应成功
    for ($i = 1; $i <= 3; $i++) {
        Livewire::test(ContactForm::class)
            ->set('name', "用户{$i}")
            ->set('phone', '13800138'.str_pad($i, 3, '0', STR_PAD_LEFT))
            ->set('message', "留言内容{$i}")
            ->call('submit');
    }

    // 3 条记录已写入
    $this->assertDatabaseCount('site_contact_messages', 3);

    // 第 4 次提交应触发速率限制，返回错误，不写入新记录
    $component = Livewire::test(ContactForm::class)
        ->set('name', '第四次提交')
        ->set('phone', '13800138999')
        ->set('message', '这次应该被限速')
        ->call('submit');

    // 断言速率限制错误已添加（错误信息在 phone 字段）
    $component->assertHasErrors(['phone']);

    // 断言数据库仍只有 3 条记录（第 4 次未写入）
    $this->assertDatabaseCount('site_contact_messages', 3);
});
