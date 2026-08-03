<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource;

/**
 * 编辑资讯文章页
 */
class EditNewsArticle extends EditRecord
{
    protected static string $resource = NewsArticleResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
