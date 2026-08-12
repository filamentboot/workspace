<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Filamentboot\FilamentbootSite\Database\Seeders\Demo\DecorationNewsSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\Demo\SoftwareNewsSeeder;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Database\Seeder;

/**
 * 资讯演示内容种子（按当前主题分发，批次 3）
 *
 * 类名与调用方式维持不变——`composer.json` 的 `extra.filamentboot.post_install.seeders`
 * 与既有测试都按 `SiteNewsSeeder::class` 调用，本类只做一件事：按
 * `SiteServiceProvider::resolveActiveTheme()` 分发到对应主题的具体 Seeder。
 * 具体文章见 `Demo\DecorationNewsSeeder` / `Demo\SoftwareNewsSeeder`。
 */
class SiteNewsSeeder extends Seeder
{
    /**
     * 执行资讯演示数据播种
     */
    public function run(): void
    {
        $this->call(match (SiteServiceProvider::resolveActiveTheme()) {
            'software' => SoftwareNewsSeeder::class,
            default    => DecorationNewsSeeder::class,
        });
    }
}
