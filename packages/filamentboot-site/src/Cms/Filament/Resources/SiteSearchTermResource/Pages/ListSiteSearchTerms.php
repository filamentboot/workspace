<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteSearchTermResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteSearchTermResource;

/**
 * 站内搜索词列表页
 *
 * 没有 getHeaderActions()：这张表不允许手工新建，理由见资源类注释。
 */
class ListSiteSearchTerms extends ListRecords
{
    protected static string $resource = SiteSearchTermResource::class;
}
