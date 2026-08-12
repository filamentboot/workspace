<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Filament\FriendLinkResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\FriendLinks\Filament\FriendLinkResource;

/**
 * 友情链接列表页
 *
 * 由 filamentboot-site:content-type:sync 按「friend_link」内容类型声明生成。
 */
class ListFriendLinks extends ListRecords
{
    protected static string $resource = FriendLinkResource::class;

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
