<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource;

/**
 * 编辑资讯分类页
 */
class EditNewsCategory extends EditRecord
{
    protected static string $resource = NewsCategoryResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
