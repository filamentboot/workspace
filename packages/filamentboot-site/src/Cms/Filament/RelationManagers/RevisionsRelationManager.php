<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SiteRevision;
use Filamentboot\FilamentbootSite\Cms\Revisions\Revisionable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * 内容版本快照关系管理器（批次 1.5c，从仅挂在 SitePageResource 的版本泛化，供全部
 * 7 类内容共用同一个类——挂在哪个 Resource 由各 Resource 的 getRelations() 决定，
 * 本类不认识任何具体内容类型，字段清单全部来自 owner 记录的 Revisionable 实现）
 *
 * 只读列表 + 两个动作：查看字段级新旧对比、回滚。
 *
 * 快照不可变，因此没有新建 / 编辑 / 删除动作——版本历史是审计链路，
 * 能改就不成其为历史。裁剪由 ContentRevisionObserver 按 revisions_keep 自动做。
 *
 * blocks（仅 SitePage 有）的对比只列区块 type 序列，不做全文 diff：区块 payload
 * 是嵌套 JSON，做成可读 diff 的工作量远超它的价值，而「加了哪几个区块、顺序变
 * 没变」才是回滚决策真正需要的信息。其余类型没有这个字段，displayValue() 的
 * 这条分支天然不会被触碰到。
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = '版本历史';

    protected static ?string $modelLabel = '版本';

    protected static ?string $pluralModelLabel = '版本';

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
                    ->state(fn (SiteRevision $record): string => $this->changeSummary($record))
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
                    ->modalDescription('将用该版本的内容覆盖当前记录。发布状态与发布时间不会被改动，且本次回滚会作为新版本记入历史。')
                    ->authorize(fn (): bool => $this->canRollback())
                    ->action(fn (SiteRevision $record) => $this->rollbackTo($record)),
            ])
            // 快照不可变：没有新建、编辑、删除
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('暂无版本历史')
            ->emptyStateDescription('内容每次变更都会自动留下一份快照。');
    }

    /**
     * 查看 Modal：字段级新旧对比
     *
     * 与「当前记录」比而不是与上一版比：用户点开是为了决定要不要回滚到这一版，
     * 需要看的正是「回滚后会变成什么样」。
     */
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(fn (SiteRevision $record): HtmlString => $this->comparisonTable($record)),
        ]);
    }

    /**
     * 当前登录管理员能否回滚
     */
    protected function canRollback(): bool
    {
        return auth('admin')->user()?->can('rollback', $this->ownerRecord()) ?? false;
    }

    /**
     * 执行回滚
     *
     * 用旧 payload update() 当前记录，ContentRevisionObserver 会自然再写一条
     * 新快照，因此「回滚产生新版本而非删除历史」是免费的。
     *
     * 只恢复 owner 记录 revisionRestorableFields() 里的字段——status 与
     * published_at 不在其中：回滚一条已归档记录的旧版本不该把它偷偷重新发布。
     */
    protected function rollbackTo(SiteRevision $revision): void
    {
        $owner = $this->ownerRecord();

        $payload = $revision->payload;
        $restore = [];

        foreach ($owner::revisionRestorableFields() as $field) {
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

        $owner->update($restore);

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
    protected function changeSummary(SiteRevision $revision): string
    {
        $previous = SiteRevision::query()
            ->where('revisionable_type', $revision->revisionable_type)
            ->where('revisionable_id', $revision->revisionable_id)
            ->where('id', '<', $revision->getKey())
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return '初始版本';
        }

        $changed = [];

        foreach ($this->ownerRecord()::revisionFieldLabels() as $field => $label) {
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
    protected function comparisonTable(SiteRevision $revision): HtmlString
    {
        $owner = $this->ownerRecord();

        $current = $owner->revisionPayload();
        $rows    = '';

        foreach ($owner::revisionFieldLabels() as $field => $label) {
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
     * 才是回滚决策需要的信息。这个字段只有 SitePage 有，其余类型走不到这条分支。
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

    /**
     * 取 owner 记录，类型收窄成 Model&Revisionable 供静态方法调用
     */
    protected function ownerRecord(): Model&Revisionable
    {
        /** @var Model&Revisionable $record */
        $record = $this->getOwnerRecord();

        return $record;
    }
}
