<?php

namespace Filamentboot\FilamentbootSite\Services;

use Filamentboot\FilamentbootSite\Cms\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteDemoSeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteIntroCopySeeder;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteNewsSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * 后台「种入 / 清空演示数据」按钮背后的动作（批次 3）
 *
 * 种入直接复用既有 db:seed 流程，与命令行 `filamentboot-site:install --with-demo`
 * 及插件市场初始化走的是同一套 Seeder，不另起一套逻辑。
 *
 * 清空是这套流程的镜像：按 SiteDemoSeeder/SiteNewsSeeder 的 seededSlugs() 强删内容、
 * 按 key 删前台导航菜单、复位列表页导语——分别对应 run() 里播种内容、
 * SiteFrontMenuSeeder、SiteIntroCopySeeder 三件事。
 */
class DemoDataToggle
{
    /**
     * 种入：按当前主题播种演示内容与资讯（幂等，可重复点）
     */
    public function seed(): void
    {
        Artisan::call('db:seed', ['--class' => SiteDemoSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => SiteNewsSeeder::class, '--force' => true]);
    }

    /**
     * 清空：按当前主题的 slug 清单强删内容，重置前台菜单与列表页导语
     *
     * 必须 forceDelete()：内容模型全部 SoftDeletes，软删不触发 deleteAllMedia()，
     * 孤儿媒体就是这么来的（见 Concerns\SeedsMediaImages 的说明）。
     *
     * ⚠️ 前台菜单（SiteMenu 的 main/footer 两条）与列表页导语一旦被运营改过，
     * 这里的重置分不出「demo 原文」与「运营改过的真内容」，会一并清空——这是
     * 「不加 is_demo 列」这个设计取舍的直接代价，调用前必须让操作者知晓
     * （后台按钮的确认文案已经写了这一条，不要绕过确认直接调这个方法）。
     *
     * @return array<string, int> 各模型/菜单实际删除的行数，key 为模型短类名
     */
    public function clear(): array
    {
        $deleted = [];

        $slugsByModel = SiteDemoSeeder::seededSlugs() + SiteNewsSeeder::seededSlugs();

        foreach ($slugsByModel as $model => $slugs) {
            $records = $model::withTrashed()->whereIn('slug', $slugs)->get();
            $records->each(fn ($record) => $record->forceDelete());
            $deleted[class_basename($model)] = $records->count();
        }

        $deleted['SiteMenu'] = SiteMenu::query()->whereIn('key', ['main', 'footer'])->delete();

        SiteIntroCopySeeder::resetToEmpty();

        return $deleted;
    }
}
