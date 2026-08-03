<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource;

/**
 * 资讯文章列表页
 */
class ListNewsArticles extends ListRecords
{
    protected static string $resource = NewsArticleResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
