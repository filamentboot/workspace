<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\HasPreviewAction;
use Filamentboot\FilamentbootSite\Cms\Filament\Concerns\HasPublishWorkflowActions;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource;

/**
 * 编辑城市页
 */
class EditSiteCityPage extends EditRecord
{
    use HasPreviewAction;
    use HasPublishWorkflowActions;

    protected static string $resource = SiteCityPageResource::class;

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
}
