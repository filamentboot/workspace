<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource;

/**
 * 资讯分类列表页
 */
class ListNewsCategories extends ListRecords
{
    protected static string $resource = NewsCategoryResource::class;

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
