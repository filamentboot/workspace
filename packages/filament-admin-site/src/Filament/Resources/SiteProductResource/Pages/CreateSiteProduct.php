<?php

namespace LaravelStack\FilamentAdminSite\Filament\Resources\SiteProductResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaravelStack\FilamentAdminSite\Filament\Resources\SiteProductResource;

/**
 * 创建智能产品页
 */
class CreateSiteProduct extends CreateRecord
{
    protected static string $resource = SiteProductResource::class;
}
