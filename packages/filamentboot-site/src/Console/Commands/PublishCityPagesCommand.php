<?php

namespace Filamentboot\FilamentbootSite\Console\Commands;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 批量发布 / 取消发布城市页
 *
 * ## 为什么要有这个命令
 *
 * 城市页是**一次建齐、分批发布**的：库里躺着几百条 `published_at` 为空的记录，
 * 观察期看完第一批的收录与询盘数据，再决定放不放第二批。
 *
 * 没有这个命令，扩量就得改种子重新部署一次——而扩量恰恰是**要看数据才做的决定**，
 * 不该和发版绑在一起。有了它，扩量是服务器上敲一行。
 *
 * ## 只动 `published_at` 与 `status`，别的一个字段不碰
 *
 * 后台改过的标题、正文、排序全部保留。这个命令的语义是「让它可见」，
 * 不是「重新生成」。批次 1.5a 起城市页也走状态机，`scopePublished()`
 * 同时判 `status=published` 与 `published_at`——只清空后者不推进
 * 前者，页面还是不可见，所以两个字段必须一起动。
 *
 * ## 用法
 *
 * ```bash
 * # 看看有多少条待发布，不写库
 * php artisan filamentboot-site:publish-city-pages --dry-run
 *
 * # 按省放量：把江苏所有未发布的城市页放出去
 * php artisan filamentboot-site:publish-city-pages --province=320000
 *
 * # 按条数放量：从未发布的里面挑 50 条（按 sort、code 排，结果可预期）
 * php artisan filamentboot-site:publish-city-pages --limit=50
 *
 * # 指定城市
 * php artisan filamentboot-site:publish-city-pages --code=420100 --code=430100
 *
 * # 收回（比如某城数据被发现是错的）
 * php artisan filamentboot-site:publish-city-pages --code=420100 --unpublish
 * ```
 *
 * ⚠️ **发布之后必须 `php artisan cache:clear`**：公开页走整页缓存，
 * 新放出来的城市页在旧缓存过期前不会出现在列表页和站点地图里。
 */
class PublishCityPagesCommand extends Command
{
    /** @var string */
    protected $signature = 'filamentboot-site:publish-city-pages
                            {--code=* : 指定区划代码，可重复}
                            {--province= : 某个省级区划代码下的全部城市}
                            {--limit= : 最多处理多少条}
                            {--unpublish : 反过来，把 published_at 清空}
                            {--dry-run : 只报告，不写库}';

    /** @var string */
    protected $description = '批量发布或取消发布城市页（分批放量用，不必重新部署）';

    public function handle(): int
    {
        $unpublish = (bool) $this->option('unpublish');

        $query = SiteCityPage::query()
            // 发布时只看没发布的、取消发布时只看发布了的：
            // 已经是目标状态的不该被算进「本次处理了 N 条」，也不该刷新时间戳
            ->when(! $unpublish, fn (Builder $q): Builder => $q->whereNull('published_at'))
            ->when($unpublish, fn (Builder $q): Builder => $q->whereNotNull('published_at'));

        /** @var list<string> $codes */
        $codes = array_filter((array) $this->option('code'));

        if ($codes !== []) {
            $query->whereIn('region_code', $codes);
        }

        $province = (string) $this->option('province');

        if ($province !== '') {
            $region = SiteRegion::query()->where('code', $province)->first();

            if ($region === null) {
                $this->error("区划代码 {$province} 不存在。");

                return self::FAILURE;
            }

            $query->whereIn(
                'region_code',
                SiteRegion::query()->where('parent_code', $province)->pluck('code'),
            );
        }

        // 排序写死：分批放量要能复现「上次放到哪了」，随机顺序做不到
        $query->orderBy('sort')->orderBy('region_code');

        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $query->limit($limit);
        }

        /** @var Collection<int, SiteCityPage> $pages */
        $pages = $query->with('region')->get();

        if ($pages->isEmpty()) {
            $this->info('没有符合条件的城市页，什么都没做。');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%s %d 条：%s%s',
            $unpublish ? '将取消发布' : '将发布',
            $pages->count(),
            $pages->take(8)->map(fn (SiteCityPage $p): string => $p->region?->displayName() ?? $p->region_code)->implode('、'),
            $pages->count() > 8 ? sprintf('…等 %d 城', $pages->count()) : '',
        ));

        if ($this->option('dry-run')) {
            $this->comment('--dry-run，没有写库。');

            return self::SUCCESS;
        }

        SiteCityPage::query()
            ->whereIn('id', $pages->modelKeys())
            ->update([
                'status'       => $unpublish ? PageStatus::DRAFT : PageStatus::PUBLISHED,
                'published_at' => $unpublish ? null : now(),
            ]);

        $this->info(sprintf('已%s %d 条。', $unpublish ? '取消发布' : '发布', $pages->count()));
        $this->comment('别忘了 php artisan cache:clear —— 公开页走整页缓存，不清的话列表页和站点地图还是旧的。');

        return self::SUCCESS;
    }
}
