<?php

use Filamentboot\FilamentbootSite\Enums\PageStatus;

/**
 * 页面状态机测试（#14）
 *
 * 转移规则写在枚举上而不是 Filament Action 里，正是为了能在这里脱离 Filament
 * 单测全矩阵——Action 的 visible() 只是它的一层消费者。
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Enums\PageStatus
 */

/**
 * 全矩阵：5×5 共 25 条边，逐条比对期望值
 *
 * 用一张完整表而不是只测几条允许边：漏测的是「不该允许却允许了」那一半，
 * 而那一半才是安全边界（例如 archived 直接跳到 published 等于一键复活归档页）。
 */
it('状态转移矩阵与预期一致', function () {
    $expected = [
        'draft'     => ['review', 'scheduled', 'published'],
        'review'    => ['draft', 'scheduled', 'published'],
        'scheduled' => ['draft', 'published'],
        'published' => ['draft', 'archived'],
        'archived'  => ['draft'],
    ];

    foreach (PageStatus::cases() as $from) {
        foreach (PageStatus::cases() as $to) {
            $shouldAllow = in_array($to->value, $expected[$from->value], true);

            $this->assertSame(
                $shouldAllow,
                $from->canTransitionTo($to),
                "从 {$from->value} 到 {$to->value} 的转移判定与预期不符",
            );
        }
    }
});

/**
 * 转移到自身一律为 false
 *
 * 转移到自己不是一个动作，后台 Action 据此隐藏按钮，
 * 避免出现「当前已是草稿」却还显示「退回草稿」。
 */
it('不允许转移到自身', function () {
    foreach (PageStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse();
    }
});

/**
 * allowedTransitions() 与 canTransitionTo() 是同一份判据
 */
it('允许列表与单条判定一致', function () {
    foreach (PageStatus::cases() as $from) {
        foreach ($from->allowedTransitions() as $to) {
            expect($from->canTransitionTo($to))->toBeTrue();
        }

        expect($from->allowedTransitions())->not->toContain($from);
    }
});

/**
 * 每个状态都至少能回到草稿（archived 之外的死角检查）
 *
 * 任一状态若没有出边，页面就会永久卡在那里，只能改库救回来。
 */
it('每个状态都有出边', function () {
    foreach (PageStatus::cases() as $status) {
        expect($status->allowedTransitions())->not->toBeEmpty();
    }
});

/**
 * 已发布不能直接排期：给已发布页面设未来时间等于悄悄下线它
 */
it('已发布不能直接改为定时发布', function () {
    expect(PageStatus::PUBLISHED->canTransitionTo(PageStatus::SCHEDULED))->toBeFalse();
});

/**
 * 归档只能回草稿，重新上线必须过一遍编辑
 */
it('归档只能回到草稿', function () {
    expect(PageStatus::ARCHIVED->allowedTransitions())->toBe([PageStatus::DRAFT]);
});
