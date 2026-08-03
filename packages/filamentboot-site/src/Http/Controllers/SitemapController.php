<?php

namespace Filamentboot\FilamentbootSite\Http\Controllers;

use Filamentboot\FilamentbootSite\Models\SiteCase;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Models\SiteSolution;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * 站点地图与 robots.txt 控制器
 *
 * 两条路由必须注册在动态 /{slug} 之前，且 sitemap.xml / robots.txt 已列入
 * config('filamentboot-site.route.reserved_slugs')，防止被页面路由吞掉。
 *
 * 仅输出已发布内容：所有查询均走对应模型的 published() scope，
 * 草稿与未到发布时间的内容不会泄露到站点地图（T-10-04-04）。
 *
 * 宿主 public/robots.txt 若存在会优先于本路由被 Web 服务器返回，
 * 需要动态 robots 时应删除该静态文件。
 */
class SitemapController extends Controller
{
    /**
     * 输出 XML 站点地图
     */
    public function sitemap(): Response
    {
        /** @var int $limit */
        $limit = config('filamentboot-site.seo.sitemap_limit', 2000);

        $urls = [
            [
                'loc'        => route('site.home'),
                'lastmod'    => null,
                'changefreq' => 'daily',
                'priority'   => '1.0',
            ],
            [
                'loc'        => route('site.cases.index'),
                'lastmod'    => null,
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ],
            [
                'loc'        => route('site.solutions.index'),
                'lastmod'    => null,
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ],
            [
                'loc'        => route('site.products.index'),
                'lastmod'    => null,
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ],
        ];

        foreach (SiteCase::published()->latest('published_at')->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.cases.show', $record->slug), $record->updated_at, '0.7');
        }

        foreach (SiteSolution::published()->latest('published_at')->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.solutions.show', $record->slug), $record->updated_at, '0.7');
        }

        foreach (SiteProduct::published()->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.products.show', $record->slug), $record->updated_at, '0.6');
        }

        foreach (SitePage::published()->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.page', $record->slug), $record->updated_at, '0.5');
        }

        $xml = view('filamentboot-site::sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * 输出 robots.txt（附带站点地图声明）
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /livewire',
            '',
            'Sitemap: '.route('site.sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * 构造单条站点地图记录
     *
     * @return array{loc: string, lastmod: string|null, changefreq: string, priority: string}
     */
    protected function entry(string $loc, ?Carbon $lastmod, string $priority): array
    {
        return [
            'loc'        => $loc,
            'lastmod'    => $lastmod?->toAtomString(),
            'changefreq' => 'monthly',
            'priority'   => $priority,
        ];
    }
}
