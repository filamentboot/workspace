<?php

namespace LaravelStack\FilamentAdminSite\Filament\Resources\SitePageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaravelStack\FilamentAdminSite\Filament\Resources\SitePageResource;

/**
 * 创建静态页面页
 */
class CreateSitePage extends CreateRecord
{
    protected static string $resource = SitePageResource::class;
}
