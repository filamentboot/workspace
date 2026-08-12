<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Modules\Corporate\AdSlots\Filament\AdSlotResource;

/**
 * 广告位编辑页
 *
 * 由 filamentboot-site:content-type:sync 按「ad_slot」内容类型声明生成。
 */
class EditAdSlot extends EditRecord
{
    protected static string $resource = AdSlotResource::class;

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
