<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource;

/**
 * 询盘列表页（只读，无新建按钮）
 */
class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    /**
     * 不提供新建 Action（canCreate=false）
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
