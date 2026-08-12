<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\CreatesRedirectOnSlugChange;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\HasPreviewAction;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\HasPublishWorkflowActions;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource;
use Filamentboot\FilamentbootSite\Cms\Rendering\BlockSanitizer;

/**
 * 编辑静态页面页
 *
 * 状态流转走 Header Action 而不是只靠表单里的 Select（#14）：Select 让「改内容」
 * 与「改发布状态」变成同一次提交，编辑随手把状态从 review 拨到 published
 * 就绕过了发布权限。Action 各自独立授权，且只暴露 PageStatus 允许的目标状态。
 * 状态机 Action 本体在 HasPublishWorkflowActions（批次 1.5a 抽出，供其余 6
 * 类内容复用），草稿预览 Action 本体在 HasPreviewAction（批次 1.5b 抽出，同样
 * 供全 7 类内容复用），本类只负责组装。
 *
 * 表单里那个状态 Select 保留，承担「新建时选初始状态」与「查看当前状态」；
 * 越权发布由 publish_site_page 权限点在 Policy 层挡住（#19）。
 */
class EditSitePage extends EditRecord
{
    use CreatesRedirectOnSlugChange;
    use HasPreviewAction;
    use HasPublishWorkflowActions;

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
     * 保存前净化区块 payload（#13）并记下旧 slug（#18，CreatesRedirectOnSlugChange）
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('blocks', $data)) {
            $data['blocks'] = app(BlockSanitizer::class)->sanitize($data['blocks']);
        }

        $this->captureSlugBeforeSave();

        return $data;
    }

    /**
     * slug 变更后自动建 301 重定向（#18，逻辑见 CreatesRedirectOnSlugChange）
     */
    protected function afterSave(): void
    {
        $this->createRedirectIfSlugChanged();
    }

    /**
     * 静态页面详情路由，参数只有 slug
     */
    protected function redirectRouteName(): string
    {
        return 'site.page';
    }
}
