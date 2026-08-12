<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners;

use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Models\SiteBanner;
use Illuminate\Database\Eloquent\Collection;

/**
 * 幻灯片取数服务
 *
 * 前台视图直接 `app(BannerProvider::class)->forPosition(...)`，与
 * `components/nav.blade.php` / `footer.blade.php` 取 MenuResolver 是同一条路子。
 *
 * 为什么不从控制器传参：投放位置有五个、分布在首页与四个列表页，走控制器要改
 * 五个方法并给每个视图约定一个变量名；而首页那份还得挂到 HomeSectionProvider
 * 上，等于同一件事两套机制。视图侧解析让「哪个位置放幻灯片」完全是视图的事，
 * 加一个投放位置不需要动 PHP。
 *
 * 单请求内按位置 memo：首页要先问「有没有 HOME_TOP」再取列表，
 * 不缓存就是两次同样的查询。公开页整页缓存之后这点开销本就有限，
 * 但预览与后台请求不走缓存，省下来是实的。
 */
class BannerProvider
{
    /**
     * 本次请求已解析过的位置
     *
     * @var array<string, Collection<int, SiteBanner>>
     */
    protected array $resolved = [];

    /**
     * 取某个投放位置当前生效的幻灯片
     *
     * @return Collection<int, SiteBanner>
     */
    public function forPosition(BannerPosition $position): Collection
    {
        return $this->resolved[$position->value] ??= SiteBanner::query()
            ->active()
            ->forPosition($position)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }
}
