<?php

/**
 * 询盘表单测试桩（ContactFormTest）
 *
 * Wave 0 安全网测试，由 Plan 10-04（Livewire ContactForm 组件）落地转绿。
 * 覆盖 D-10-15 询盘安全要求：速率限制防刷（danharrin/livewire-rate-limiting）。
 *
 * @group site
 * @covers \LaravelStack\FilamentAdminSite\Http\Livewire\ContactForm
 */

/**
 * 目标可观测信号：Livewire::test(ContactForm)->set('name','张三')->set('phone','13800138000')
 * ->set('message','留言内容')->call('submit') 后 site_contact_messages 表新增 status=unread 的记录
 * （由 Plan 10-04 实现 Livewire ContactForm 组件后落地转绿）
 */
it('询盘表单提交后持久化到数据库且默认状态为未读', function () {
    $this->markTestIncomplete(
        '待 10-04 落地：ContactForm->submit() 后 site_contact_messages 应新增 status=unread 记录'
    );
});

/**
 * 目标可观测信号：同一 IP 连续超过 3 次提交后触发 livewire-rate-limiting 限制，
 * 不写入新记录（D-10-15 询盘安全，防刷保护）
 * （由 Plan 10-04 在 ContactForm 集成 danharrin/livewire-rate-limiting 后落地转绿）
 */
it('询盘表单超过速率限制后拒绝提交', function () {
    $this->markTestIncomplete(
        '待 10-04 落地（D-10-15 安全）：同一 IP 连续超 3 次提交应触发速率限制，不写入新记录'
    );
});
