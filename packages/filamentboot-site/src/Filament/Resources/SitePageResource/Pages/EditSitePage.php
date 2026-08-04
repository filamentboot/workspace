<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Cms\Rendering\BlockSanitizer;
use Filamentboot\FilamentbootSite\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource;
use Filamentboot\FilamentbootSite\Http\Middleware\SiteRedirectMiddleware;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SiteRedirect;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * 编辑静态页面页
 *
 * 状态流转走 Header Action 而不是只靠表单里的 Select（#14）：Select 让「改内容」
 * 与「改发布状态」变成同一次提交，编辑随手把状态从 review 拨到 published
 * 就绕过了发布权限。Action 各自独立授权，且只暴露 PageStatus 允许的目标状态。
 *
 * 表单里那个状态 Select 保留，承担「新建时选初始状态」与「查看当前状态」；
 * 越权发布由 publish_site_page 权限点在 Policy 层挡住（#19）。
 */
class EditSitePage extends EditRecord
{
    protected static string $resource = SitePageResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            $this->transitionAction(
                name: 'submitForReview',
                label: '提交审核',
                icon: 'heroicon-o-paper-airplane',
                color: 'warning',
                target: PageStatus::REVIEW,
            ),
            $this->publishAction(),
            $this->scheduleAction(),
            $this->transitionAction(
                name: 'backToDraft',
                label: '退回草稿',
                icon: 'heroicon-o-arrow-uturn-left',
                color: 'gray',
                target: PageStatus::DRAFT,
            ),
            $this->transitionAction(
                name: 'archive',
                label: '归档',
                icon: 'heroicon-o-archive-box',
                color: 'danger',
                target: PageStatus::ARCHIVED,
            ),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * 草稿预览（#16）
     *
     * 生成 15 分钟有效的签名 URL 并新标签打开。签名而不是直接给 /preview/{id}：
     * 编辑经常要把未发布内容发给不登录后台的人过目，签名链接能过期是前提。
     *
     * 插件禁用时前台路由未注册，route() 会抛 —— 此时按钮直接不显示，
     * 而不是点了之后 500。
     */
    protected function previewAction(): Action
    {
        return Action::make('preview')
            ->label('预览')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn (): bool => $this->previewUrl() !== null)
            ->url(fn (): ?string => $this->previewUrl())
            ->openUrlInNewTab();
    }

    /**
     * 生成签名预览 URL，前台路由不可用时返回 null
     */
    protected function previewUrl(): ?string
    {
        return rescue(
            fn (): string => URL::temporarySignedRoute(
                'site.page.preview',
                now()->addMinutes(15),
                ['page' => $this->getRecord()->getKey()],
            ),
            null,
            report: false,
        );
    }

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
     * 除状态机之外额外要求 publish_site_page：内容编辑只能提交审核，
     * 发布是独立的一道权责（#19 的三层角色靠这个权限点分层）。
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
                /** @var SitePage $record */
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
        /** @var SitePage $record */
        $record = $this->getRecord();

        return $record->status->canTransitionTo($target);
    }

    /**
     * 执行状态转移并刷新表单
     *
     * 走模型 update() 而不是直接改属性再 save()：#15 的 SitePageObserver
     * 挂在模型事件上，绕过它状态变更就不会留下版本快照。
     *
     * @param  array<string, mixed>  $extra  随状态一并写入的字段
     */
    protected function transitionTo(PageStatus $target, array $extra = []): void
    {
        /** @var SitePage $record */
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

    /**
     * 保存前的旧 slug（#18 自动建重定向用）
     *
     * 必须在 mutateFormDataBeforeSave 阶段抓：afterSave 时模型已经是新值，
     * getOriginal() 在 EditRecord 的保存流程里也已经被同步过。
     */
    protected ?string $slugBeforeSave = null;

    /**
     * 保存前净化区块 payload（#13）并记下旧 slug（#18）
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('blocks', $data)) {
            $data['blocks'] = app(BlockSanitizer::class)->sanitize($data['blocks']);
        }

        /** @var SitePage $record */
        $record = $this->getRecord();

        $this->slugBeforeSave = (string) $record->slug;

        return $data;
    }

    /**
     * slug 变更后自动建 301 重定向（#18）
     *
     * **自动创建 + 通知里给撤销按钮**，而不是保存前弹确认框：
     * 默认永不丢旧 URL，比默认弹窗少一次点击、也少一次误关。
     * 已被搜索引擎收录的地址一旦 404，权重要几周才能重新积累回来。
     */
    protected function afterSave(): void
    {
        /** @var SitePage $record */
        $record = $this->getRecord();

        $from = SiteRedirectMiddleware::normalizePath((string) $this->slugBeforeSave);
        $to   = SiteRedirectMiddleware::normalizePath((string) $record->slug);

        // to == from 时不建不跳（自指重定向会让浏览器判为循环）
        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        // 已有同源记录就改指向：slug 从 a 改到 b 再改到 c 时，a 应当直指 c，
        // 而不是留下 a→b、b→c 两跳（多一跳就多一次权重损耗）
        $redirect = SiteRedirect::query()->updateOrCreate(
            ['from_path' => $from],
            ['to_path' => '/'.$to, 'status_code' => 301],
        );

        // 反向链（b→a）若存在必须删掉，否则新旧地址互相指形成死循环
        SiteRedirect::query()->where('from_path', $to)->where('to_path', '/'.$from)->delete();

        Notification::make()
            ->success()
            ->title('已创建 301 跳转')
            ->body("/{$from} → /{$to}，旧链接不会 404。")
            ->actions([
                Action::make('undoRedirect')
                    ->label('撤销')
                    ->color('danger')
                    ->action(function () use ($redirect): void {
                        $redirect->delete();

                        Notification::make()
                            ->warning()
                            ->title('已撤销跳转')
                            ->body('旧链接将返回 404。')
                            ->send();
                    }),
            ])
            ->persistent()
            ->send();
    }
}
