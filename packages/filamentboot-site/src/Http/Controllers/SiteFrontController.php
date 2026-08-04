<?php

namespace Filamentboot\FilamentbootSite\Http\Controllers;

use Filamentboot\FilamentbootSite\Models\SiteCase;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 官网前台控制器
 *
 * 负责读取已发布内容并传递视图数据（含 SEO 回退链）。
 * 所有方法仅返回已发布（scopePublished）的内容，防止草稿泄露（T-10-04-04）。
 * slug 参数通过 Eloquent where() 参数绑定传递，防止 SQL 注入（T-10-04-03）。
 *
 * 站点设置由 SiteServiceProvider::shareSiteSettings() 以 $siteSettings 注入全部
 * 前台视图，控制器不再重复传参。
 *
 * 视图命名空间 'filamentboot-site::' 由 SiteServiceProvider::registerThemeViews()
 * 按「宿主发布覆盖 → 包内主题 → 共享层 → 包内视图根」的顺序解析。
 */
class SiteFrontController extends Controller
{
    /**
     * 官网首页
     *
     * 展示精选案例、精选方案、精选产品、业主见证，并传递全局 SEO 数据。
     *
     * 见证不取 featured 案例：置顶与否是编辑对案例本身的排期判断，
     * 跟这条案例有没有配业主原话是两回事，用 featured 过滤会让大量见证白填。
     */
    public function home(): View
    {
        $featuredCases     = SiteCase::published()->featured()->latest('published_at')->take(6)->get();
        $featuredSolutions = SiteSolution::published()->featured()->latest('published_at')->take(4)->get();
        $featuredProducts  = SiteProduct::published()->featured()->take(6)->get();

        $testimonials = SiteCase::published()
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->whereNotNull('customer_quote')
            ->where('customer_quote', '!=', '')
            ->latest('published_at')
            ->take(6)
            ->get();

        $seoData = $this->buildHomeSeo();

        // Organization 只在首页输出：品牌词知识面板锚在首页，详情页里它已经
        // 作为 publisher / author 嵌在 Article 与 Product 节点内部。
        $seoData['jsonLd'] = [$this->organizationSchema()];

        return view('filamentboot-site::home', compact(
            'featuredCases',
            'featuredSolutions',
            'featuredProducts',
            'testimonials',
            'seoData'
        ));
    }

    /**
     * 装修案例列表页
     */
    public function caseIndex(): View
    {
        $records = SiteCase::published()->latest('published_at')->paginate(12);
        $seoData = $this->buildListSeo('装修案例');

        return view('filamentboot-site::cases.index', compact('records', 'seoData'));
    }

    /**
     * 装修案例详情页
     *
     * @param  string  $slug  案例 slug（参数绑定防注入，T-10-04-03）
     */
    public function caseShow(string $slug): View
    {
        $record  = SiteCase::published()->where('slug', $slug)->firstOrFail();
        $seoData = $this->buildSeo($record);

        $breadcrumbs = $this->breadcrumbs([
            ['label' => '装修案例', 'url' => route('site.cases.index')],
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $seoData['jsonLd'] = [
            $this->articleSchema($seoData, $record->published_at, $record->updated_at),
            $this->breadcrumbSchema($breadcrumbs),
        ];

        return view('filamentboot-site::cases.show', compact('record', 'breadcrumbs', 'seoData'));
    }

    /**
     * 智能方案列表页
     */
    public function solutionIndex(): View
    {
        $records = SiteSolution::published()->latest('published_at')->paginate(12);
        $seoData = $this->buildListSeo('智能方案');

        return view('filamentboot-site::solutions.index', compact('records', 'seoData'));
    }

    /**
     * 智能方案详情页
     *
     * @param  string  $slug  方案 slug（参数绑定防注入，T-10-04-03）
     */
    public function solutionShow(string $slug): View
    {
        $record  = SiteSolution::published()->where('slug', $slug)->firstOrFail();
        $seoData = $this->buildSeo($record);

        $breadcrumbs = $this->breadcrumbs([
            ['label' => '智能方案', 'url' => route('site.solutions.index')],
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $seoData['jsonLd'] = [$this->breadcrumbSchema($breadcrumbs)];

        return view('filamentboot-site::solutions.show', compact('record', 'breadcrumbs', 'seoData'));
    }

    /**
     * 智能产品列表页
     */
    public function productIndex(): View
    {
        $records = SiteProduct::published()->paginate(12);
        $seoData = $this->buildListSeo('智能产品');

        return view('filamentboot-site::products.index', compact('records', 'seoData'));
    }

    /**
     * 智能产品详情页
     *
     * @param  string  $slug  产品 slug（参数绑定防注入，T-10-04-03）
     */
    public function productShow(string $slug): View
    {
        $record  = SiteProduct::published()->where('slug', $slug)->firstOrFail();
        $seoData = $this->buildSeo($record);

        $breadcrumbs = $this->breadcrumbs([
            ['label' => '智能产品', 'url' => route('site.products.index')],
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $seoData['jsonLd'] = [
            $this->productSchema($seoData, $record),
            $this->breadcrumbSchema($breadcrumbs),
        ];

        return view('filamentboot-site::products.show', compact('record', 'breadcrumbs', 'seoData'));
    }

    /**
     * 资讯列表页
     *
     * 分类筛选走查询参数而非 Livewire：阶段 4 要把公开页做成整页缓存，
     * 每个筛选组合各自是一个可缓存的静态 URL，比动态组件更划算。
     */
    public function newsIndex(Request $request): View
    {
        $categories = NewsCategory::query()
            ->withCount(['articles' => fn (Builder $query): Builder => $query->published()])
            ->orderBy('sort')
            ->get();

        // 用全量分类做查找：0 篇文章的分类前台不出筛选按钮，但直链进来仍应正确显示标题
        $activeSlug     = (string) $request->query('category', '');
        $activeCategory = $activeSlug !== '' ? $categories->firstWhere('slug', $activeSlug) : null;

        $records = NewsArticle::published()
            ->when(
                $activeCategory !== null,
                fn (Builder $query): Builder => $query->where('category_id', $activeCategory?->id)
            )
            ->with('category')
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $archiveMonths = $this->newsArchiveMonths();
        $seoData       = $this->buildListSeo(($activeCategory?->name_zh ?? '') ?: '智能家居资讯');

        return view('filamentboot-site::news.index', compact(
            'records',
            'categories',
            'activeCategory',
            'archiveMonths',
            'seoData'
        ));
    }

    /**
     * 资讯详情页
     *
     * @param  string  $slug  文章 slug（参数绑定防注入，T-10-04-03）
     */
    public function newsShow(string $slug): View
    {
        $record = NewsArticle::published()->with(['category', 'tags'])->where('slug', $slug)->firstOrFail();

        // 未归类的文章取全站最新几篇兜底，好过详情页底部空一块
        $related = NewsArticle::published()
            ->whereKeyNot($record->getKey())
            ->when(
                $record->category_id !== null,
                fn (Builder $query): Builder => $query->where('category_id', $record->category_id)
            )
            ->with('category')
            ->latest('published_at')
            ->take(3)
            ->get();

        $seoData = $this->buildSeo($record);

        // 未归类文章跳过分类层，不留一个指向 /news?category= 的空链接
        $breadcrumbs = $this->breadcrumbs(array_values(array_filter([
            ['label' => '资讯中心', 'url' => route('site.news.index')],
            $record->category !== null ? [
                'label' => $record->category->name_zh,
                'url'   => route('site.news.index', ['category' => $record->category->slug]),
            ] : null,
            ['label' => $record->title_zh, 'url' => null],
        ])));

        $seoData['jsonLd'] = [
            $this->articleSchema($seoData, $record->published_at, $record->updated_at),
            $this->breadcrumbSchema($breadcrumbs),
        ];

        return view('filamentboot-site::news.show', compact('record', 'related', 'breadcrumbs', 'seoData'));
    }

    /**
     * 资讯归档页（按年月）
     *
     * 年月格式已由路由 where 约束（4 位年 + 01-12 月），此处只做区间换算。
     *
     * @param  string  $year  四位年份
     * @param  string  $month  两位月份
     */
    public function newsArchive(string $year, string $month): View
    {
        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $records = NewsArticle::published()
            ->whereBetween('published_at', [$start, $end])
            ->with('category')
            ->latest('published_at')
            ->paginate(12);

        $archiveMonths = $this->newsArchiveMonths();
        $seoData       = $this->buildListSeo($start->format('Y 年 n 月').'资讯归档');

        $breadcrumbs = $this->breadcrumbs([
            ['label' => '资讯中心', 'url' => route('site.news.index')],
            ['label' => $start->format('Y 年 n 月'), 'url' => null],
        ]);

        $seoData['jsonLd'] = [$this->breadcrumbSchema($breadcrumbs)];

        return view('filamentboot-site::news.archive', compact('records', 'archiveMonths', 'start', 'breadcrumbs', 'seoData'));
    }

    /**
     * 归档月份及各月文章数（键为 Y-m，按时间倒序）
     *
     * 在 PHP 里分组而非交给数据库的日期函数：MySQL 的 DATE_FORMAT 与 SQLite 的
     * strftime 语法不同，宿主换驱动就会炸。取最近 500 篇封顶，够撑十几年的更新量。
     *
     * @return Collection<string, int>
     */
    protected function newsArchiveMonths(): Collection
    {
        return NewsArticle::published()
            ->latest('published_at')
            ->limit(500)
            ->get(['id', 'published_at'])
            ->groupBy(fn (NewsArticle $article): string => $article->published_at?->format('Y-m') ?? '')
            ->map(fn (Collection $group): int => $group->count());
    }

    /**
     * 静态页面（/{slug}，保留 slug 已在路由层排除）
     *
     * @param  string  $slug  页面 slug（参数绑定防注入，T-10-04-03）
     */
    public function page(string $slug): View
    {
        $record  = SitePage::published()->where('slug', $slug)->firstOrFail();
        $seoData = $this->buildSeo($record);

        $breadcrumbs = $this->breadcrumbs([
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $seoData['jsonLd'] = [$this->breadcrumbSchema($breadcrumbs)];

        return view('filamentboot-site::pages.show', compact('record', 'breadcrumbs', 'seoData'));
    }

    /**
     * 构建面包屑数组（B3）
     *
     * 统一在控制器建、视图只渲染：同一个数组同时喂给两套主题的面包屑组件和
     * B1 的 BreadcrumbList 结构化数据，一次构建两处消费，不会出现「页面上显示
     * 三级、结构化数据里只有两级」这类前后不一致。
     *
     * 首页由本方法统一补在最前，调用方只传首页之后的层级。
     *
     * @param  list<array{label: string, url: string|null}>  $trail  末项为当前页，url 传 null 表示不出链接
     * @return list<array{label: string, url: string|null}>
     */
    protected function breadcrumbs(array $trail): array
    {
        return [
            ['label' => '首页', 'url' => route('site.home')],
            ...$trail,
        ];
    }

    /**
     * BreadcrumbList 结构化数据（B1，数据源即 breadcrumbs()）
     *
     * 末项当前页的 url 为 null，item 用当前 URL 补齐——BreadcrumbList 要求
     * 每个 ListItem 都有 item，缺了整段会被判为无效。
     *
     * @param  list<array{label: string, url: string|null}>  $breadcrumbs
     * @return array<string, mixed>
     */
    protected function breadcrumbSchema(array $breadcrumbs): array
    {
        $items = [];

        foreach ($breadcrumbs as $index => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => $crumb['label'],
                'item'     => $crumb['url'] ?? url()->current(),
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * 内容页 SEO 回退链构建器（Pattern 5）
     *
     * 标题：记录 seo_title → 记录 title_zh → 全局默认标题 → app.name
     * 描述：记录 seo_description → 记录 description_zh → 全局默认描述 → config 兜底文案
     *
     * @param  object  $record  内容记录（含 seo_title/seo_description/seo_keywords 字段）
     * @return array{title: string, description: string, keywords: string, ogTitle: string, ogDescription: string, ogImage: string|null, ogType: string}
     */
    protected function buildSeo(object $record): array
    {
        // 使用 isset() 检查 Eloquent 动态属性（property_exists 对 __get 魔术属性无效）
        $titleFallback = isset($record->title_zh) ? ($record->title_zh ?: '') : '';

        // 案例/方案/产品用 description_zh 承载简介，资讯用 excerpt_zh
        $descFallback = isset($record->description_zh) ? ($record->description_zh ?: '') : '';

        if ($descFallback === '') {
            $descFallback = isset($record->excerpt_zh) ? ($record->excerpt_zh ?: '') : '';
        }

        $title = ($record->seo_title ?: '')
            ?: $titleFallback
            ?: $this->defaultTitle();

        $description = ($record->seo_description ?: '')
            ?: $descFallback
            ?: $this->defaultDescription();

        // 内容页优先使用自身封面作为 OG 图，无封面时回退全局默认
        $ogImage = method_exists($record, 'ogImageUrl')
            ? ($record->ogImageUrl() ?: $this->defaultOgImage())
            : $this->defaultOgImage();

        return [
            'title'         => $title,
            'description'   => $description,
            'keywords'      => (string) ($record->seo_keywords ?? ''),
            'ogTitle'       => $title,
            'ogDescription' => $description,
            'ogImage'       => $ogImage,
            'ogType'        => 'article',
        ];
    }

    /**
     * 首页 SEO 数据（无具体记录，直接读全局设置）
     *
     * @return array{title: string, description: string, keywords: string, ogTitle: string, ogDescription: string, ogImage: string|null, ogType: string}
     */
    protected function buildHomeSeo(): array
    {
        $title       = $this->defaultTitle();
        $description = $this->defaultDescription();

        return [
            'title'         => $title,
            'description'   => $description,
            'keywords'      => '',
            'ogTitle'       => $title,
            'ogDescription' => $description,
            'ogImage'       => $this->defaultOgImage(),
            'ogType'        => 'website',
        ];
    }

    /**
     * 列表页 SEO 数据（全局默认 + 列表类型名称）
     *
     * 描述始终经 defaultDescription() 兜底，确保列表页 meta description 不为空。
     *
     * @param  string  $label  列表名称（如 '装修案例'）
     * @return array{title: string, description: string, keywords: string, ogTitle: string, ogDescription: string, ogImage: string|null, ogType: string}
     */
    protected function buildListSeo(string $label): array
    {
        $title       = $label.' - '.$this->defaultTitle();
        $description = $this->defaultDescription();

        return [
            'title'         => $title,
            'description'   => $description,
            'keywords'      => '',
            'ogTitle'       => $title,
            'ogDescription' => $description,
            'ogImage'       => $this->defaultOgImage(),
            'ogType'        => 'website',
        ];
    }

    /**
     * Article 结构化数据（资讯文章与装修案例共用）
     *
     * 案例也用 Article 而非 CreativeWork：案例本质是配图长文，
     * Article 是搜索引擎支持最完整的类型，能拿到发布时间与作者展示。
     *
     * @param  array{title: string, description: string, ogImage: string|null, ...}  $seo  已构建的 SEO 数据，避免重复跑一遍回退链
     * @param  Carbon|null  $publishedAt  发布时间
     * @param  Carbon|null  $updatedAt  最后修改时间
     * @return array<string, mixed>
     */
    protected function articleSchema(array $seo, ?Carbon $publishedAt, ?Carbon $updatedAt): array
    {
        $organization = $this->organizationNode();

        return array_filter([
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => $seo['title'],
            'description'      => $seo['description'],
            'image'            => $seo['ogImage'] !== null ? [$seo['ogImage']] : null,
            'datePublished'    => $publishedAt?->toAtomString(),
            'dateModified'     => $updatedAt?->toAtomString(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => url()->current(),
            ],
            'author'    => $organization,
            'publisher' => $organization,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Product 结构化数据
     *
     * 无价格时不输出 offers：Google 会因缺 offers 降级为普通 Product（仅告警），
     * 但填一个假价格属于结构化数据造假，风险大得多。
     *
     * @param  array{title: string, description: string, ogImage: string|null, ...}  $seo  已构建的 SEO 数据
     * @return array<string, mixed>
     */
    protected function productSchema(array $seo, SiteProduct $record): array
    {
        // 图集与封面合并去重，Product 的 image 支持多图
        $images = $record->galleryUrls('og');

        if ($seo['ogImage'] !== null) {
            array_unshift($images, $seo['ogImage']);
        }

        $images = array_values(array_unique($images));

        $offers = $record->price !== null ? [
            '@type'         => 'Offer',
            'price'         => number_format((float) $record->price, 2, '.', ''),
            'priceCurrency' => 'CNY',
            'availability'  => 'https://schema.org/InStock',
            'url'           => url()->current(),
        ] : null;

        return array_filter([
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $seo['title'],
            'description' => $seo['description'],
            'image'       => $images !== [] ? $images : null,
            'brand'       => ($record->brand ?? '') !== '' ? ['@type' => 'Brand', 'name' => $record->brand] : null,
            'offers'      => $offers,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * 完整的 Organization 结构化数据（B1，仅首页输出）
     *
     * 与 organizationNode() 的区别：那个是嵌在 Article / Product 里的 publisher
     * 片段，不带 @context 也不带联系方式；这个是独立顶层节点，直接影响品牌词
     * 搜索的知识面板，所以把电话与地址一并给出。
     *
     * 站点设置未填的字段一律不输出——结构化数据里出现空字符串比缺字段更糟。
     *
     * @return array<string, mixed>
     */
    protected function organizationSchema(): array
    {
        $settings = $this->resolveSettings();

        // ?? 已经吞掉 null 上的属性读取，再写 ?-> 是多余的（phpstan nullsafe.neverNull）
        $phone   = trim((string) ($settings->phone ?? ''));
        $address = trim((string) ($settings->address_zh ?? ''));
        $logo    = $settings?->logo;

        return array_filter([
            '@context'  => 'https://schema.org',
            '@type'     => 'Organization',
            'name'      => $this->defaultTitle(),
            'url'       => route('site.home'),
            'logo'      => ($logo !== null && $logo !== '') ? $logo : null,
            'telephone' => $phone !== '' ? $phone : null,
            'address'   => $address !== '' ? [
                '@type'          => 'PostalAddress',
                'streetAddress'  => $address,
                'addressCountry' => 'CN',
            ] : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * 发布方 / 作者节点（取站点设置的公司名与 Logo）
     *
     * @return array<string, mixed>
     */
    protected function organizationNode(): array
    {
        $settings = $this->resolveSettings();
        $logo     = $settings?->logo;

        return array_filter([
            '@type' => 'Organization',
            'name'  => $this->defaultTitle(),
            'logo'  => ($logo !== null && $logo !== '') ? $logo : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * 全局默认标题：站点设置 → 公司名称 → app.name
     */
    protected function defaultTitle(): string
    {
        $settings = $this->resolveSettings();

        return ($settings?->seo_default_title_zh ?: '')
            ?: ($settings?->company_name_zh ?: '')
            ?: (string) config('app.name', '');
    }

    /**
     * 全局默认描述：站点设置 → config 兜底文案
     *
     * 站点设置未填写时回退到 config('filamentboot-site.seo.fallback_description')，
     * 保证任何页面的 meta description 都不为空。
     */
    protected function defaultDescription(): string
    {
        $settings = $this->resolveSettings();

        return ($settings?->seo_default_description_zh ?: '')
            ?: (string) config('filamentboot-site.seo.fallback_description', '');
    }

    /**
     * 全局默认 Open Graph 图片
     *
     * 未配置时返回 null，视图据此不输出 og:image，
     * 避免像此前那样硬编码到一个并不存在的 /img/og-default.jpg。
     */
    protected function defaultOgImage(): ?string
    {
        $image = $this->resolveSettings()?->og_default_image;

        return ($image !== null && $image !== '') ? $image : null;
    }

    /**
     * 解析 SiteSettings 实例（settings 表未迁移时降级为 null，Pitfall 2）
     */
    protected function resolveSettings(): ?SiteSettings
    {
        try {
            return app(SiteSettings::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
