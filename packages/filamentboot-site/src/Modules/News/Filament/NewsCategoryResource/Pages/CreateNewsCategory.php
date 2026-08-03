<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource;

/**
 * 创建资讯分类页
 */
class CreateNewsCategory extends CreateRecord
{
    protected static string $resource = NewsCategoryResource::class;
}
