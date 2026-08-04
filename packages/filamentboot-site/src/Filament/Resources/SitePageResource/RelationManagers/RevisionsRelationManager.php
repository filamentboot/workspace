<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SitePageRevision;
use Filamentboot\FilamentbootSite\Observers\SitePageObserver;
use Illuminate\Support\HtmlString;

/**
 * 页面版本快照关系管理器（#15）
 *
 * 只读列表 + 两个动作：查看字段级新旧对比、回滚。
 *
 * 快照不可变，因此没有新建 / 编辑 / 删除动作——版本历史是审计链路，
 * 能改就不成其为历史。裁剪由 SitePageObserver 按 revisions_keep 自动做。
 *
 * blocks 的对比只列区块 type 序列，不做全文 diff：区块 payload 是嵌套 JSON，
 * 做成可读 diff 的工作量远超它的价值，而「加了哪几个区块、顺序变没变」
 * 才是回滚决策真正需要的信息。
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = '版本历史';

    protected static ?string $modelLabel = '版本';

    protected static ?string $pluralModelLabel = '版本';

    /**
     * 字段名 → 中文标签（对比表与变更摘要共用）
     *
     * @var array<string, string>
     */
    protected const FIELD_LABELS = [
        'title_zh'        => '标题',
        'title_en'        => '英文标题',
        'slug'            => 'URL Slug',
        'template'        => '页面版式',
        'content_zh'      => '正文',
        'content_en'      => '英文正文',
        'blocks'          => '页面区块',
        'seo_title'       => 'SEO 标题',
        'seo_description' => 'SEO 描述',
        'seo_keywords'    => 'SEO 关键词',
        'seo_og_image'    => '社交分享图',
        'status'          => '发布状态',
        'published_at'    => '发布时间',
    ];

    /**
     * 版本列表
     */
    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label('操作人')
                    // CLI / seeder 场景 created_by 为 null，模型已允许
                    ->placeholder('系统'),
                TextColumn::make('id')
                    ->label('变更字段')
                    ->state(fn (SitePageRevision $record): string => $this->changeSummary($record))
                    ->wrap(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('查看')
                    ->modalHeading('版本内容对比')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭'),
                Action::make('rollback')
                    ->label('回滚')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('回滚到该版本')
                    ->modalDescription('将用该版本的内容覆盖当前页面。发布状态与发布时间不会被改动，且本次回滚会作为新版本记入历史。')
                    ->authorize(fn (): bool => $this->canRollback())
                    ->action(fn (SitePageRevision $record) => $this->rollbackTo($record)),
            ])
            // 快照不可变：没有新建、编辑、删除
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('暂无版本历史')
            ->emptyStateDescription('页面每次内容变更都会自动留下一份快照。');
    }

    /**
     * 查看 Modal：字段级新旧对比
     *
     * 与「当前页面」比而不是与上一版比：用户点开是为了决定要不要回滚到这一版，
     * 需要看的正是「回滚后会变成什么样」。
     */
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(fn (SitePageRevision $record): HtmlString => $this->comparisonTable($record)),
        ]);
    }

    /**
     * 当前登录管理员能否回滚
     */
    protected function canRollback(): bool
    {
        /** @var SitePage $page */
        $page = $this->getOwnerRecord();

        return auth('admin')->user()?->can('rollback', $page) ?? false;
    }

    /**
     * 执行回滚
     *
     * 用旧 payload update() 当前页面，SitePageObserver 会自然再写一条新快照，
     * 因此「回滚产生新版本而非删除历史」是免费的。
     *
     * 只恢复 SitePageObserver::RESTORABLE 里的字段——status 与 published_at
     * 不在其中：回滚一篇已归档页的旧版本不该把它偷偷重新发布。
     */
    protected function rollbackTo(SitePageRevision $revision): void
    {
        /** @var SitePage $page */
        $page = $this->getOwnerRecord();

        $payload = $revision->payload;
        $restore = [];

        foreach (SitePageObserver::RESTORABLE as $field) {
            if (array_key_exists($field, $payload)) {
                $restore[$field] = $payload[$field];
            }
        }

        if ($restore === []) {
            Notification::make()
                ->warning()
                ->title('该版本没有可恢复的内容字段')
                ->send();

            return;
        }

        $page->update($restore);

        Notification::make()
            ->success()
            ->title('已回滚到 '.$revision->created_at?->format('Y-m-d H:i:s').' 的版本')
            ->body('发布状态未改动，本次回滚已记入版本历史。')
            ->send();
    }

    /**
     * 该快照相对上一条快照变了哪些字段
     *
     * 上一条不存在（即基线快照）时显示「初始版本」，不列全部字段——
     * 新建时每个字段都算"变了"，列出来是噪音。
     */
    protected function changeSummary(SitePageRevision $revision): string
    {
        $previous = SitePageRevision::query()
            ->where('page_id', $revision->page_id)
            ->where('id', '<', $revision->getKey())
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return '初始版本';
        }

        $changed = [];

        foreach (self::FIELD_LABELS as $field => $label) {
            if (($previous->payload[$field] ?? null) !== ($revision->payload[$field] ?? null)) {
                $changed[] = $label;
            }
        }

        return $changed === [] ? '无内容变更' : implode('、', $changed);
    }

    /**
     * 渲染「该版本 → 当前」的字段对比表
     *
     * 手拼 HTML 而不是堆 Infolist 组件：对比表是一张两列表格，
     * 用 Entry 组件搭出来要十几个节点，且没法把「相同的字段折叠掉」。
     * 所有取自内容的值一律 e() 转义。
     */
    protected function comparisonTable(SitePageRevision $revision): HtmlString
    {
        /** @var SitePage $page */
        $page = $this->getOwnerRecord();

        $current = SitePageObserver::payloadOf($page);
        $rows    = '';

        foreach (self::FIELD_LABELS as $field => $label) {
            $old = $this->displayValue($field, $revision->payload[$field] ?? null);
            $new = $this->displayValue($field, $current[$field] ?? null);

            if ($old === $new) {
                continue;
            }

            $rows .= '<tr class="align-top">'
                .'<td class="py-2 pr-4 font-medium whitespace-nowrap">'.e($label).'</td>'
                .'<td class="py-2 pr-4 text-danger-600 dark:text-danger-400">'.e($old).'</td>'
                .'<td class="py-2 text-success-600 dark:text-success-400">'.e($new).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            return new HtmlString('<p class="text-sm">该版本与当前内容一致，回滚不会产生任何变化。</p>');
        }

        return new HtmlString(
            '<div class="overflow-x-auto"><table class="w-full text-sm">'
            .'<thead><tr class="text-left border-b">'
            .'<th class="pb-2 pr-4">字段</th><th class="pb-2 pr-4">该版本</th><th class="pb-2">当前</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
        );
    }

    /**
     * 把 payload 里的值转成可读字符串
     *
     * blocks 只输出区块 type 序列（如「hero → faq → cta」）：区块 payload 是
     * 嵌套 JSON，做成可读 diff 的成本远超价值，而「加了哪几个区块、顺序变没变」
     * 才是回滚决策需要的信息。
     */
    protected function displayValue(string $field, mixed $value): string
    {
        if ($field === 'blocks') {
            $types = [];

            foreach (is_array($value) ? $value : [] as $block) {
                if (is_array($block) && is_string($block['type'] ?? null)) {
                    $types[] = $block['type'];
                }
            }

            return $types === [] ? '（无区块）' : implode(' → ', $types);
        }

        if ($field === 'status' && is_string($value)) {
            return PageStatus::tryFrom($value)?->label() ?? $value;
        }

        if ($value === null || $value === '') {
            return '（空）';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        // 正文一类长文本截断显示：对比表要能一眼扫完，全文对比不是这里的职责
        return str($value)->stripTags()->squish()->limit(160)->value();
    }
}
