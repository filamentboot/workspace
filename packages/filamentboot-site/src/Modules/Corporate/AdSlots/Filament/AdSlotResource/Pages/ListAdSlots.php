<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource;

/**
 * 广告位列表页
 *
 * 由 filamentboot-site:content-type:sync 按「ad_slot」内容类型声明生成。
 */
class ListAdSlots extends ListRecords
{
    protected static string $resource = AdSlotResource::class;

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
