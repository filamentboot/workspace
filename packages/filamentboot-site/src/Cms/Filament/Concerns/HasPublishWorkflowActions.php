<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Illuminate\Support\Carbon;

/**
 * 内容发布状态机 Header Action（批次 1.5a，从 EditSitePage 抽出）
 *
 * `PageStatus` 与 SitePage 零耦合，任何有 `status`（cast 为 PageStatus）与
 * `published_at` 两列的内容类型都能直接 `use` 本 trait 组出自己的
 * `getHeaderActions()`——不提供 `getHeaderActions()` 本身，因为各类型的
 * 其余 Header Action（Delete/ForceDelete/Restore 等）不尽相同，组装权留给
 * 调用方。草稿预览（previewAction）不在这里，在 HasPreviewAction（批次 1.5b）。
 *
 * 不做成覆盖 EditRecord 的某个钩子：多个内容类型的 EditXxxPage 已经用
 * CreatesRedirectOnSlugChange 这一套「显式调用」的写法，同一个类里两个
 * trait 都覆写同一钩子会互相吃掉，所以这里只提供方法，由调用方自己在
 * getHeaderActions() 里组装。
 */
trait HasPublishWorkflowActions
{
    /**
     * 通用状态流转 Action（不涉及发布权限的那几个）
     *
     * visible() 查 PageStatus::canTransitionTo()，非法边上的按钮不出现——
     * 让它出现再报错，等于把状态机规则留给用户去试。
     */
    protected function transitionAction(
        string $name,
        string $label,
        string $icon,
        string $color,
        PageStatus $target,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->canTransitionTo($target))
            ->action(function () use ($target): void {
                $this->transitionTo($target);
            });
    }

    /**
     * 立即发布
     *
     * 除状态机之外额外要求 publish_{resource}：内容编辑只能提交审核，
     * 发布是独立的一道权责。
     */
    protected function publishAction(): Action
    {
        return Action::make('publish')
            ->label('发布')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->authorize('publish')
            ->visible(fn (): bool => $this->canTransitionTo(PageStatus::PUBLISHED))
            ->action(function (): void {
                $record = $this->getRecord();

                // 定时发布排期后又点了立即发布：published_at 若留在未来，
                // scopePublished() 会判为未到期，前台仍然看不到——
                // 这正是「点了发布前台看不到」那类故障的来源，所以就地纠正。
                $publishedAt = $record->published_at;

                $this->transitionTo(
                    PageStatus::PUBLISHED,
                    $publishedAt !== null && $publishedAt->isFuture() ? ['published_at' => now()] : [],
                );
            });
    }

    /**
     * 定时发布（带发布时间选择）
     */
    protected function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label('定时发布')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->authorize('publish')
            ->visible(fn (): bool => $this->canTransitionTo(PageStatus::SCHEDULED))
            ->schema([
                DateTimePicker::make('published_at')
                    ->label('发布时间')
                    ->seconds(false)
                    ->required()
                    ->minDate(fn (): Carbon => now())
                    ->default(fn (): Carbon => now()->addHour())
                    ->helperText('到点后前台自动可见，不需要队列或定时任务'),
            ])
            ->action(function (array $data): void {
                $this->transitionTo(PageStatus::SCHEDULED, [
                    'published_at' => $data['published_at'],
                ]);
            });
    }

    /**
     * 当前记录能否转移到目标状态
     */
    protected function canTransitionTo(PageStatus $target): bool
    {
        return $this->getRecord()->status->canTransitionTo($target);
    }

    /**
     * 执行状态转移并刷新表单
     *
     * 走模型 update() 而不是直接改属性再 save()：全部 7 类内容都挂了
     * ContentRevisionObserver，绕过 update() 状态变更就不会留下版本快照。
     *
     * @param  array<string, mixed>  $extra  随状态一并写入的字段
     */
    protected function transitionTo(PageStatus $target, array $extra = []): void
    {
        $record = $this->getRecord();
        $from   = $record->status;

        // 二次校验：visible() 只管按钮显不显示，Action 仍可被直接调用
        if (! $from->canTransitionTo($target)) {
            Notification::make()
                ->danger()
                ->title('状态流转失败')
                ->body("不允许从「{$from->label()}」直接变更为「{$target->label()}」。")
                ->send();

            return;
        }

        $record->update([...$extra, 'status' => $target]);

        // 表单里的状态 Select 与发布时间要跟着变，否则页面上显示的还是旧值
        $this->fillForm();

        Notification::make()
            ->success()
            ->title('已变更为「'.$target->label().'」')
            ->send();
    }
}
