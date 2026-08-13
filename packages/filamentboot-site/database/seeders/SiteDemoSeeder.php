<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Filamentboot\FilamentbootSite\Database\Seeders\Demo\DecorationDemoSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\Demo\SoftwareDemoSeeder;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Database\Seeder;

/**
 * 官网演示内容种子（按当前主题分发，批次 3）
 *
 * 类名与调用方式维持不变——`composer.json` 的 `extra.filamentboot.post_install.seeders`
 * 与既有测试都按 `SiteDemoSeeder::class` 调用，本类只做一件事：按
 * `SiteServiceProvider::resolveActiveTheme()` 分发到对应主题的具体 Seeder，
 * 具体内容（案例/方案/产品/页面/幻灯片）见 `Demo\DecorationDemoSeeder` /
 * `Demo\SoftwareDemoSeeder`，两套都是虚构主体，不含任何真实公司信息。
 *
 * 导航/页脚菜单（SiteFrontMenuSeeder）与列表页导语（SiteIntroCopySeeder）两套主题
 * 共用同一个类、内部按主题分岔，且都依赖具体 Seeder 建好的静态页，必须排在其后。
 */
class SiteDemoSeeder extends Seeder
{
    /**
     * 执行演示数据播种
     */
    public function run(): void
    {
        $theme = SiteServiceProvider::resolveActiveTheme();

        $this->call(match ($theme) {
            'software' => SoftwareDemoSeeder::class,
            default    => DecorationDemoSeeder::class,
        });

        $this->call(SiteFrontMenuSeeder::class);
        $this->call(SiteIntroCopySeeder::class);

        $this->command?->info("演示内容已按当前主题（{$theme}）播种：案例/方案/产品/静态页/导航/导语，按 slug 增量补种，已有记录不受影响");
    }

    /**
     * 当前主题会写入的内容 slug 清单，按模型分组（批次 3，供后台「清空演示数据」使用）
     *
     * @return array<class-string, list<string>>
     */
    public static function seededSlugs(): array
    {
        return match (SiteServiceProvider::resolveActiveTheme()) {
            'software' => SoftwareDemoSeeder::seededSlugs(),
            default    => DecorationDemoSeeder::seededSlugs(),
        };
    }
}
