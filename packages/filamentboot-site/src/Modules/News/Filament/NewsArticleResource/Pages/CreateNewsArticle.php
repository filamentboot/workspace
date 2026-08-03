<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource;

/**
 * 创建资讯文章页
 */
class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;
}
