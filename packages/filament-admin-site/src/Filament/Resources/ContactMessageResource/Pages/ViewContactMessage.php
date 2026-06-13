<?php

namespace LaravelStack\FilamentAdminSite\Filament\Resources\ContactMessageResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use LaravelStack\FilamentAdminSite\Filament\Resources\ContactMessageResource;

/**
 * 查看询盘详情页
 */
class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
