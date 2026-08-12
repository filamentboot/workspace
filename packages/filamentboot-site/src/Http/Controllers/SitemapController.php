<?php

namespace Filamentboot\FilamentbootSite\Http\Controllers;

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Services\TagContent;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\CityDirectory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\Support\ContentTypeLabels;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     * 输出站点地图**索引**（/sitemap.xml）
     *
     * 分片的理由不是体积——内容 + 城市加起来四百来条，离 sitemaps.org 的 50000 条
     * 上限差着两个数量级。理由是**站长平台按提交的分片分别统计收录率**：
     * 三期观察期要用「城市页收录了多少」来决定铺不铺第二批，
     * 混在一份地图里那个数字根本读不出来。
     *
     * 城市分片在一条城市页都没发布时不列进来：交一个必然 404 的地址上去，
     * 站长平台会把它记成抓取失败。
     */
    public function sitemap(): Response
    {
        $sitemaps = [['loc' => route('site.sitemap.content')]];

        if (app(CityDirectory::class)->groupedByProvince() !== []) {
            $sitemaps[] = ['loc' => route('site.sitemap.city')];
        }

        $xml = view('filamentboot-site::sitemap-index', ['sitemaps' => $sitemaps])->render();

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * 输出城市页分片（/sitemap-city.xml）
     *
     * 收三种地址：城市总索引、有已发布城市页的省页、城市页本身。
     * 三者的可达性判断与控制器**必须是同一套**——控制器在空的时候 404，
     * 这里就不能把空的写进去，否则等于主动交一批死链上去。
     *
     * 优先级 0.7：与案例 / 方案同档。城市页是本站的长尾主力，
     * 不该比一条资讯还低。
     */
    public function sitemapCity(): Response
    {
        $directory = app(CityDirectory::class);
        $grouped   = $directory->groupedByProvince();

        if ($grouped === []) {
            return $this->xml([]);
        }

        $urls = [[
            'loc'        => route('site.city.index'),
            'lastmod'    => null,
            'changefreq' => 'weekly',
            'priority'   => '0.8',
        ]];

        foreach ($grouped as $group) {
            // 直辖市的省页就是城市页，它会在下面随城市页一起收，这里不重复
            if ($group['ownPage'] === null) {
                $urls[] = $this->entry(
                    route('site.city.province', ['province' => $group['region']->slug]),
                    null,
                    '0.7'
                );
            }
        }

        foreach ($directory->publishedPages() as $record) {
            if ($record->region === null) {
                continue;
            }

            $urls[] = $this->entry($record->url(), $record->updated_at, '0.7');
        }

        return $this->xml($urls);
    }

    /**
     * 输出内容分片（/sitemap-content.xml）
     *
     * 这一份就是分片之前 /sitemap.xml 的全部内容，一条没动。
     */
    public function sitemapContent(): Response
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
                // 套餐列表优先级给到 0.9：它是本站唯一一处「按户型能横向比、
                // 直接对应下单决策」的页面，权重高于方案与产品列表
                'loc'        => route('site.packages.index'),
                'lastmod'    => null,
                'changefreq' => 'weekly',
                'priority'   => '0.9',
            ],
            [
                'loc'        => route('site.products.index'),
                'lastmod'    => null,
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ],
            [
                'loc'        => route('site.news.index'),
                'lastmod'    => null,
                'changefreq' => 'daily',
                'priority'   => '0.8',
            ],
        ];

        foreach (SiteCase::published()->latest('published_at')->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.cases.show', $record->slug), $record->updated_at, '0.7');
        }

        foreach (SiteSolution::published()->latest('published_at')->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.solutions.show', $record->slug), $record->updated_at, '0.7');
        }

        foreach (SitePackage::published()->orderedForCompare()->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.packages.show', $record->slug), $record->updated_at, '0.8');
        }

        // 归档页不入站点地图：内容与列表页重复，只作站内浏览入口
        foreach (NewsArticle::published()->latest('published_at')->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.news.show', $record->slug), $record->updated_at, '0.7');
        }

        foreach (SiteProduct::published()->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.products.show', $record->slug), $record->updated_at, '0.6');
        }

        foreach (SitePage::published()->limit($limit)->get() as $record) {
            $urls[] = $this->entry(route('site.page', $record->slug), $record->updated_at, '0.5');
        }

        // 标签聚合页。**只收有已发布内容的标签**：SiteFrontController::tagShow()
        // 在分组为空时 404，把空标签写进站点地图等于主动交一批死链上去。
        // 后台随手建个标签、还没挂内容，是很正常的中间状态。
        //
        // lastmod 有意留 null：site_tags.updated_at 记的是「标签本身被改名」的时间，
        // 与聚合页内容变没变没有关系，填上去是个假信号。
        $tagContent = app(TagContent::class);

        foreach (SiteTag::query()->orderBy('id')->limit($limit)->get() as $record) {
            if (! $tagContent->hasContent($record)) {
                continue;
            }

            $urls[] = $this->entry(route('site.tags.show', $record->slug), null, '0.4');
        }

        return $this->xml($urls);
    }

    /**
     * 把一组条目渲染成 urlset 响应
     *
     * @param  list<array{loc: string, lastmod: string|null, changefreq: string, priority: string}>  $urls
     */
    protected function xml(array $urls): Response
    {
        $xml = view('filamentboot-site::sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * 输出 robots.txt（附带站点地图声明）
     *
     * AI 抓取器段由 config('filamentboot-site.robots') 决定，见该段注释。
     */
    public function robots(): Response
    {
        /** @var list<string> $disallow */
        $disallow = config('filamentboot-site.robots.disallow', []);

        $lines = ['User-agent: *'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines = [...$lines, ...$this->aiCrawlerGroup($disallow)];

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('site.sitemap');
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * AI 抓取器分组
     *
     * ⚠️ **这里最容易写错的一点**：robots.txt 的分组匹配是「只有最具体的那一组生效」。
     * 一旦为 GPTBot 单开一组，`User-agent: *` 那组的 Disallow 对它**就不再适用**，
     * 只写一行 `Allow: /` 等于把后台放给了它。所以 allow 策略下必须把通用段的
     * Disallow 在本组里原样重复一遍。
     *
     * 多个 User-agent 行共用一组规则是 robots.txt 的标准写法，比每个 UA 各写一段短得多。
     *
     * @param  list<string>  $disallow  通用段的 Disallow 路径
     * @return list<string>
     */
    protected function aiCrawlerGroup(array $disallow): array
    {
        $policy = (string) config('filamentboot-site.robots.ai_crawlers_policy', 'allow');

        /** @var list<string> $agents */
        $agents = config('filamentboot-site.robots.ai_crawlers', []);

        if ($agents === [] || ! in_array($policy, ['allow', 'disallow'], true)) {
            return [];
        }

        $lines = [''];

        foreach ($agents as $agent) {
            $lines[] = 'User-agent: '.$agent;
        }

        if ($policy === 'disallow') {
            $lines[] = 'Disallow: /';

            return $lines;
        }

        // allow：与通用段同规则。一条 Disallow 都没有时给一行空 Disallow，
        // 那是 robots.txt 里「全部允许」的规范写法，空组会被部分抓取器判为无效。
        if ($disallow === []) {
            $lines[] = 'Disallow:';

            return $lines;
        }

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        return $lines;
    }

    /**
     * 输出 /llms.txt
     *
     * llmstxt.org 提出的约定：站根放一份 Markdown 索引，告诉大模型这个站有什么、
     * 各部分在哪。它不替代 sitemap.xml——sitemap 是给爬虫的机器清单（全量、无描述），
     * llms.txt 是给模型看的**带说明的目录**，量要克制、每条要有一句人话。
     *
     * ⚠️ 这是新兴约定，收益不确定，目前没有哪家明确声明会读它。做它的理由是
     * 成本极低而位置是唯一的（站根一个固定路径），先占住不亏。
     *
     * 每类内容取前 llms_limit 条：全量会把这份索引撑成第二个 sitemap，
     * 那就失去它「简明目录」的意义了。
     */
    public function llms(): Response
    {
        /** @var int $limit */
        $limit = config('filamentboot-site.seo.llms_limit', 50);

        $settings = $this->settings();

        $name = trim((string) ($settings->company_name_zh ?? '')) ?: config('app.name', 'Site');
        $desc = trim((string) ($settings->seo_default_description_zh ?? ''))
            ?: (string) config('filamentboot-site.seo.fallback_description', '');

        $lines = ['# '.$name, ''];

        if ($desc !== '') {
            $lines[] = '> '.$desc;
            $lines[] = '';
        }

        $lines[] = '## 主要栏目';
        $lines[] = '';

        foreach ($this->sections() as $label => $url) {
            $lines[] = '- ['.$label.']('.$url.')';
        }

        foreach ($this->llmsGroups($limit) as $label => $items) {
            if ($items === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## '.$label;
            $lines[] = '';

            foreach ($items as $item) {
                $lines[] = '- ['.$item['title'].']('.$item['url'].')'
                    .($item['summary'] !== '' ? ': '.$item['summary'] : '');
            }
        }

        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * 栏目名 => 列表页地址
     *
     * @return array<string, string>
     */
    protected function sections(): array
    {
        $sections = [
            '首页'                        => route('site.home'),
            ContentTypeLabels::case()     => route('site.cases.index'),
            ContentTypeLabels::solution() => route('site.solutions.index'),
            ContentTypeLabels::package()  => route('site.packages.index'),
            ContentTypeLabels::product()  => route('site.products.index'),
            ContentTypeLabels::news()     => route('site.news.index'),
        ];

        // 城市页**只给索引入口、不逐条列**：llms.txt 是给模型看的简明目录，
        // 塞进几十条「XX 全屋智能」会把真正有回答价值的套餐与方案挤下去。
        // 一条都没发布时整项不出现——那时 /city 是 404
        if (app(CityDirectory::class)->groupedByProvince() !== []) {
            $sections[ContentTypeLabels::city()] = route('site.city.index');
        }

        return $sections;
    }

    /**
     * llms.txt 的分组内容
     *
     * 顺序按「对提问最有回答价值」排：套餐能直接回答「多少钱」，方案回答
     * 「能做什么」，资讯回答「怎么做」，案例是佐证。
     *
     * @return array<string, list<array{title: string, url: string, summary: string}>>
     */
    protected function llmsGroups(int $limit): array
    {
        return [
            ContentTypeLabels::package() => $this->llmsItems(
                SitePackage::published()->orderedForCompare()->limit($limit)->get(),
                'site.packages.show',
            ),
            ContentTypeLabels::solution() => $this->llmsItems(
                SiteSolution::published()->latest('published_at')->limit($limit)->get(),
                'site.solutions.show',
            ),
            ContentTypeLabels::news() => $this->llmsItems(
                NewsArticle::published()->latest('published_at')->limit($limit)->get(),
                'site.news.show',
            ),
            ContentTypeLabels::case() => $this->llmsItems(
                SiteCase::published()->latest('published_at')->limit($limit)->get(),
                'site.cases.show',
            ),
            ContentTypeLabels::product() => $this->llmsItems(
                SiteProduct::published()->limit($limit)->get(),
                'site.products.show',
            ),
        ];
    }

    /**
     * 把一批记录转成 llms.txt 的条目
     *
     * 摘要字段各内容类型不统一（资讯是 excerpt_zh，其余是 description_zh），
     * 这里按顺序取第一个非空的——字段命名统一是九期的事，不在这里绕。
     *
     * @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $records
     * @return list<array{title: string, url: string, summary: string}>
     */
    protected function llmsItems(iterable $records, string $routeName): array
    {
        $items = [];

        foreach ($records as $record) {
            $summary = trim((string) ($record->getAttribute('description_zh')
                ?? $record->getAttribute('excerpt_zh')
                ?? ''));

            $items[] = [
                'title' => (string) $record->getAttribute('title_zh'),
                'url'   => route($routeName, $record->getAttribute('slug')),
                // 换行会破坏 Markdown 列表结构，压成单行
                'summary' => (string) preg_replace('/\s+/u', ' ', $summary),
            ];
        }

        return $items;
    }

    /**
     * 站点设置，未安装或未初始化时返回 null
     */
    protected function settings(): ?SiteSettings
    {
        try {
            return app(SiteSettings::class);
        } catch (\Throwable) {
            return null;
        }
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
