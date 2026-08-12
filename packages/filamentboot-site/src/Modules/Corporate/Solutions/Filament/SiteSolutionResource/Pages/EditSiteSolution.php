<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament\SiteSolutionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\CreatesRedirectOnSlugChange;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\HasPreviewAction;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\HasPublishWorkflowActions;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament\SiteSolutionResource;

/**
 * 编辑智能方案页
 */
class EditSiteSolution extends EditRecord
{
    use CreatesRedirectOnSlugChange;
    use HasPreviewAction;
    use HasPublishWorkflowActions;

    protected static string $resource = SiteSolutionResource::class;

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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->captureSlugBeforeSave();

        return $data;
    }

    /**
     * slug 变更后自动建 301 重定向（3.5 期 B 段，CreatesRedirectOnSlugChange）
     */
    protected function afterSave(): void
    {
        $this->createRedirectIfSlugChanged();
    }

    /**
     * 智能方案详情路由，参数只有 slug
     */
    protected function redirectRouteName(): string
    {
        return 'site.solutions.show';
    }
}
