<?php

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Models\SiteRevision;
use Filamentboot\Models\AdminUser;

/**
 * 内容版本快照与回滚测试（#15，深度场景以 SitePage 为代表）
 *
 * 覆盖场景：
 * - 新建写基线快照（没有基线就回不到最初那一版）
 * - 被跟踪字段变化才写快照，未跟踪字段（sort）不写
 * - created_by 记当前管理员，CLI 场景为 null
 * - revisions_keep 上限裁剪
 * - 回滚恢复内容但**不动 status / published_at**
 * - 回滚本身产生新快照而不是删除历史
 *
 * 观察器（ContentRevisionObserver）由 SiteServiceProvider::boot() 无条件注册
 * 给全部 7 类内容（与插件启用状态无关），所以这里不需要手工挂载。共享逻辑
 * 只在 SitePage 这一个类型上深度验证，其余 6 类的接线覆盖见
 * SiteContentRevisionTest；泛化前的行为基线不变。
 *
 * @group site
 */

/**
 * 新建页面即写入基线快照
 */
it('新建页面写入基线快照', function () {
    $page = SitePage::factory()->draft()->create(['title_zh' => '初版标题']);

    $revisions = SiteRevision::where('revisionable_type', SitePage::class)
        ->where('revisionable_id', $page->getKey())
        ->get();

    expect($revisions)->toHaveCount(1)
        ->and($revisions[0]->payload['title_zh'])->toBe('初版标题')
        // status 存标量而非枚举实例：payload 是 JSON，两次读出来类型必须一致
        ->and($revisions[0]->payload['status'])->toBe('draft');
});

/**
 * 被跟踪字段变化写新快照
 */
it('内容变更写入新快照', function () {
    $page = SitePage::factory()->draft()->create(['title_zh' => '第一版']);

    $page->update(['title_zh' => '第二版']);
    $page->update(['content_zh' => '<p>新正文</p>']);

    $payloads = $page->revisions()->reorder('id')->pluck('payload');

    expect($payloads)->toHaveCount(3)
        ->and($payloads[0]['title_zh'])->toBe('第一版')
        ->and($payloads[1]['title_zh'])->toBe('第二版')
        ->and($payloads[2]['content_zh'])->toBe('<p>新正文</p>');
});

/**
 * 未跟踪字段变化不写快照
 *
 * 否则调一次排序就冒出一堆内容完全相同的版本。
 */
it('未跟踪字段变更不写快照', function () {
    $page = SitePage::factory()->draft()->create();

    $page->update(['sort' => 999]);

    expect($page->revisions()->count())->toBe(1);
});

/**
 * 状态流转也留下快照（Observer 覆盖 Filament Action，钩子做不到）
 */
it('状态变更写入快照', function () {
    $page = SitePage::factory()->draft()->create();

    $page->update(['status' => PageStatus::PUBLISHED]);

    $latest = $page->revisions()->first();

    expect($page->revisions()->count())->toBe(2)
        ->and($latest->payload['status'])->toBe('published');
});

/**
 * created_by 记录当前后台登录用户
 */
it('快照记录操作人', function () {
    $user = AdminUser::factory()->create();
    $this->actingAs($user, 'admin');

    $page = SitePage::factory()->draft()->create();

    expect($page->revisions()->first()->created_by)->toBe($user->getKey());
});

/**
 * 无登录用户（CLI / seeder）时 created_by 为 null 而不是报错
 */
it('CLI 场景操作人为空', function () {
    $page = SitePage::factory()->draft()->create();

    expect($page->revisions()->first()->created_by)->toBeNull();
});

/**
 * 超过 revisions_keep 的旧快照被裁剪，保留最近 N 条
 */
it('超过上限的旧快照被裁剪', function () {
    config()->set('filamentboot-site.revisions_keep', 3);

    $page = SitePage::factory()->draft()->create(['title_zh' => 'v0']);

    foreach (range(1, 5) as $i) {
        $page->update(['title_zh' => 'v'.$i]);
    }

    $titles = $page->revisions()->reorder('id')
        ->pluck('payload')
        ->map(fn (array $payload): string => $payload['title_zh'])
        ->all();

    // 共 6 条（1 基线 + 5 次更新），保留最近 3 条
    expect($titles)->toBe(['v3', 'v4', 'v5']);
});

/**
 * revisions_keep 为 0 时不裁剪
 */
it('上限为零时不裁剪', function () {
    config()->set('filamentboot-site.revisions_keep', 0);

    $page = SitePage::factory()->draft()->create();

    foreach (range(1, 4) as $i) {
        $page->update(['title_zh' => 'v'.$i]);
    }

    expect($page->revisions()->count())->toBe(5);
});

/**
 * 回滚恢复内容字段
 */
it('回滚恢复内容字段', function () {
    $page = SitePage::factory()->draft()->create([
        'title_zh'   => '原标题',
        'content_zh' => '<p>原正文</p>',
        'blocks'     => [['type' => 'hero', 'data' => ['title' => '原区块']]],
    ]);

    $baseline = $page->revisions()->firstOrFail();

    $page->update([
        'title_zh'   => '改后标题',
        'content_zh' => '<p>改后正文</p>',
        'blocks'     => [['type' => 'cta', 'data' => ['title' => '改后区块']]],
    ]);

    // 模拟 RevisionsRelationManager::rollbackTo() 的恢复集合
    $restore = [];
    foreach (SitePage::revisionRestorableFields() as $field) {
        if (array_key_exists($field, $baseline->payload)) {
            $restore[$field] = $baseline->payload[$field];
        }
    }
    $page->update($restore);

    $page->refresh();

    expect($page->title_zh)->toBe('原标题')
        ->and($page->content_zh)->toBe('<p>原正文</p>')
        ->and($page->blocks[0]['type'])->toBe('hero');
});

/**
 * 回滚不改动 status —— 回滚一篇已归档页的旧版本不该把它偷偷重新发布
 */
it('回滚不恢复发布状态', function () {
    $page = SitePage::factory()->create(['title_zh' => '发布时的标题']);

    $publishedRevision = $page->revisions()->firstOrFail();

    expect($publishedRevision->payload['status'])->toBe('published');

    // 页面随后被归档并改了标题
    $page->update(['status' => PageStatus::ARCHIVED, 'title_zh' => '归档后的标题']);

    $restore = [];
    foreach (SitePage::revisionRestorableFields() as $field) {
        if (array_key_exists($field, $publishedRevision->payload)) {
            $restore[$field] = $publishedRevision->payload[$field];
        }
    }
    $page->update($restore);

    $page->refresh();

    expect($page->title_zh)->toBe('发布时的标题')
        // 关键断言：内容回到了旧版，但状态仍是归档
        ->and($page->status)->toBe(PageStatus::ARCHIVED)
        ->and(SitePage::published()->whereKey($page->getKey())->exists())->toBeFalse();
});

/**
 * RESTORABLE 不含 status 与 published_at（结构约束，防日后误加）
 */
it('可恢复字段集合不含发布状态', function () {
    expect(SitePage::revisionRestorableFields())->not->toContain('status')
        ->and(SitePage::revisionRestorableFields())->not->toContain('published_at')
        // 但快照本身要记录它们，否则历史里看不到状态怎么变的
        ->and(SitePage::revisionTrackedFields())->toContain('status')
        ->and(SitePage::revisionTrackedFields())->toContain('published_at');
});

/**
 * 回滚一条「删列之前」写下的快照
 *
 * 2026-08-08 删掉了全套 `_en` 列，但**历史快照的 payload 有意没有改写**
 * ——快照是审计链路，SiteRevision 连 updated_at 都没有。于是库里长期
 * 存在一批 payload 带 title_en / content_en 而列已经不存在的旧快照。
 *
 * 回滚遍历的是 revisionRestorableFields() 而非 payload 的键，所以这批遗留键
 * 天然被滤掉。这条用例锁的就是这个滤除：**日后谁把 `_en` 加回该方法，或者把
 * 遍历改成按 payload 的键来，回滚这批旧快照就会 update 一个不存在的列，
 * 当场 500**。
 */
it('回滚带 _en 遗留键的旧快照不打不存在的列', function () {
    $page = SitePage::factory()->draft()->create([
        'title_zh'   => '现标题',
        'content_zh' => '<p>现正文</p>',
    ]);

    // 手工造一条删列之前的快照：payload 形状与当年一致
    $legacy = SiteRevision::create([
        'revisionable_type' => SitePage::class,
        'revisionable_id'   => $page->getKey(),
        'payload'           => [
            'title_zh'   => '旧标题',
            'title_en'   => 'Legacy Title',
            'slug'       => $page->slug,
            'template'   => $page->template,
            'content_zh' => '<p>旧正文</p>',
            'content_en' => '<p>Legacy body</p>',
            'blocks'     => null,
            'status'     => 'draft',
        ],
    ]);

    // 模拟 RevisionsRelationManager::rollbackTo() 的恢复集合
    $restore = [];
    foreach (SitePage::revisionRestorableFields() as $field) {
        if (array_key_exists($field, $legacy->payload)) {
            $restore[$field] = $legacy->payload[$field];
        }
    }

    expect($restore)->not->toHaveKey('title_en')
        ->and($restore)->not->toHaveKey('content_en');

    // 真的写一次：列不存在时这里会抛 SQL 异常，用例即失败
    $page->update($restore);
    $page->refresh();

    expect($page->title_zh)->toBe('旧标题')
        ->and($page->content_zh)->toBe('<p>旧正文</p>');
});

/**
 * 回滚产生新快照而非删除历史
 */
it('回滚产生新版本', function () {
    $page = SitePage::factory()->draft()->create(['title_zh' => 'A']);
    $page->update(['title_zh' => 'B']);
    $page->update(['title_zh' => 'C']);

    expect($page->revisions()->count())->toBe(3);

    $first = $page->revisions()->reorder('id')->firstOrFail();
    $page->update(['title_zh' => $first->payload['title_zh']]);

    expect($page->revisions()->count())->toBe(4)
        ->and($page->refresh()->title_zh)->toBe('A');
});

/**
 * 快照挂在 revisions 关联上，按 id 倒序（trait 已声明 latest('id')）
 */
it('版本关联按时间倒序', function () {
    $page = SitePage::factory()->draft()->create(['title_zh' => '一']);
    $page->update(['title_zh' => '二']);

    $titles = $page->revisions()->get()->map(fn (SiteRevision $r): string => $r->payload['title_zh'])->all();

    expect($titles)->toBe(['二', '一']);
});
