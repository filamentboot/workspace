<?php

namespace Filamentboot\FilamentbootSite\Http\Controllers;

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Rendering\BlockRenderer;
use Filamentboot\FilamentbootSite\Cms\Services\RelatedContent;
use Filamentboot\FilamentbootSite\Cms\Services\SiteSearch;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\CaseStyle;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\HouseType;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Home\HomeSectionProvider;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View as ViewFacade;
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
     * 各区块的数据由 Modules\Corporate\Home\HomeSectionProvider 提供（#27）：
     * 首页展示什么是企业站模块的事，CMS 的通用控制器不该硬编码 SiteCase::featured()。
     * 宿主换内容模块时 bind 掉那个类即可，控制器与路由都不用动。
     */
    public function home(): View
    {
        $seoData = $this->buildHomeSeo();

        // Organization 只在首页输出：品牌词知识面板锚在首页，详情页里它已经
        // 作为 publisher / author 嵌在 Article 与 Product 节点内部。
        $seoData['jsonLd'] = [$this->organizationSchema()];

        return view('filamentboot-site::home', [
            ...app(HomeSectionProvider::class)->sections(),
            'seoData' => $seoData,
        ]);
    }

    /**
     * 装修案例列表页
     *
     * 风格与户型筛选走查询参数而非 Livewire（#29）：每个筛选组合各自是一个可缓存的
     * 静态 URL，而 Livewire 组件会把 livewire.js 拉进页面，那个 script 标签带 data-csrf
     * → 起 session → 整页缓存失效。资讯列表早就是这个做法，这次把案例列表对齐。
     *
     * 兼容旧的 ?houseType=：Livewire 的 #[Url] 属性用的是驼峰名，改成查询参数后
     * 规范是 house_type，但已被搜索引擎收录的旧地址不该静默丢掉筛选条件。
     */
    public function caseIndex(Request $request): View
    {
        $style     = $this->enumFilter($request->query('style'), CaseStyle::class);
        $houseType = $this->enumFilter(
            $request->query('house_type') ?? $request->query('houseType'),
            HouseType::class
        );

        $records = SiteCase::published()
            ->when($style !== null, fn (Builder $query): Builder => $query->where('style', $style))
            ->when($houseType !== null, fn (Builder $query): Builder => $query->where('house_type', $houseType))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $styleOptions     = $this->enumOptions(CaseStyle::class);
        $houseTypeOptions = $this->enumOptions(HouseType::class);

        $seoData = $this->buildListSeo('装修案例');

        return view('filamentboot-site::cases.index', compact(
            'records',
            'style',
            'houseType',
            'styleOptions',
            'houseTypeOptions',
            'seoData'
        ));
    }

    /**
     * 按枚举白名单过滤一个查询参数
     *
     * 不在白名单里就当没传，而不是拿它去查库——任意字符串进 where 虽然有参数绑定挡着
     * 不会注入，但会让 ?style=<script> 这类地址渲染出一个「筛选中：<script>」的空结果页，
     * 白送一个可被外部构造的 URL。
     *
     * @param  class-string<CaseStyle|HouseType>  $enum
     */
    protected function enumFilter(mixed $value, string $enum): ?string
    {
        if (! is_scalar($value) || (string) $value === '') {
            return null;
        }

        return $enum::tryFrom((string) $value)?->value;
    }

    /**
     * 枚举的「值 => 中文标签」映射，供筛选控件渲染
     *
     * @param  class-string<CaseStyle|HouseType>  $enum
     * @return array<string, string>
     */
    protected function enumOptions(string $enum): array
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
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

        // 案例的亲和维度按「看的人在意什么」排：同风格与同户型比同分类更能说明
        // 「这就是我要的那种」，分类只是后台的归档口径
        $related = app(RelatedContent::class)->for(
            SiteCase::published()->latest('published_at'),
            $record,
            [
                'style'       => $record->style,
                'house_type'  => $record->house_type,
                'category_id' => $record->category_id,
            ]
        );

        $breadcrumbs = $this->breadcrumbs([
            ['label' => '装修案例', 'url' => route('site.cases.index')],
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $seoData['jsonLd'] = [
            $this->articleSchema($seoData, $record->published_at, $record->updated_at),
            $this->breadcrumbSchema($breadcrumbs),
        ];

        return view('filamentboot-site::cases.show', compact('record', 'related', 'breadcrumbs', 'seoData'));
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

        // 方案没有分类也没有枚举维度，唯一的亲和信号是标签（由服务自行读取）；
        // 没打标签的方案就直接拿最新几条补齐，好过底部空一块
        $related = app(RelatedContent::class)->for(
            SiteSolution::published()->latest('published_at'),
            $record
        );

        $breadcrumbs = $this->breadcrumbs([
            ['label' => '智能方案', 'url' => route('site.solutions.index')],
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $seoData['jsonLd'] = [$this->breadcrumbSchema($breadcrumbs)];

        return view('filamentboot-site::solutions.show', compact('record', 'related', 'breadcrumbs', 'seoData'));
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

        // 产品没有 published_at，排序照列表页的口径走 sort，同 sort 再按新的在前。
        // 同品牌也算相关：买智能开关的人多半在比同一个品牌的其它型号
        $related = app(RelatedContent::class)->for(
            SiteProduct::published()->orderBy('sort')->latest('id'),
            $record,
            [
                'category_id' => $record->category_id,
                'brand'       => $record->brand,
            ]
        );

        $breadcrumbs = $this->breadcrumbs([
            ['label' => '智能产品', 'url' => route('site.products.index')],
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $seoData['jsonLd'] = [
            $this->productSchema($seoData, $record),
            $this->breadcrumbSchema($breadcrumbs),
        ];

        return view('filamentboot-site::products.show', compact('record', 'related', 'breadcrumbs', 'seoData'));
    }

    /**
     * 资讯列表页
     *
     * 分类筛选走查询参数而非 Livewire：阶段 4 要把公开页做成整页缓存，
     * 每个筛选组合各自是一个可缓存的静态 URL，比动态组件更划算。
     */
    public function newsIndex(Request $request): View
    {
        // 计数走 publishedArticles 关系而不是给 articles 套闭包：闭包参数只能被推成
        // Builder<Model>，published() 作用域在那里"不存在"。别名保持 articles_count，
        // 两套主题的 news/index 视图读的是这个名字。
        $categories = NewsCategory::query()
            ->withCount('publishedArticles as articles_count')
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
        // ?? 已经吞掉 null 基对象上的属性读取，再写 ?-> 是多余的（phpstan nullsafe.neverNull）
        $seoData = $this->buildListSeo(($activeCategory->name_zh ?? '') ?: '智能家居资讯');

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

        // ⚠️ 资讯**刻意不走 Cms\Services\RelatedContent**：那个服务会在亲和维度不足时
        // 用最新内容补齐，而「相关阅读」是阅读推荐，跨分类补进来的文章会误导读者。
        // 案例 / 方案 / 产品底部那三块是浏览出口，宁可给最新内容也不要断头路——
        // 两种取舍不同是有意的，别再来统一一次（tests/Feature/SiteNewsTest.php
        // 的「详情页相关阅读取同分类且排除自身」守着这条）。
        //
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
     * 键类型写 array-key 而非 string：groupBy() 的键在类型系统里一律是 int|string，
     * 即便回调声明了 : string 也收不窄。值写 int<0, max> 是因为 Collection 的 TValue
     * 不是协变的，声明成 int 而实返 count() 的 int<0, max> 会被判为不兼容。
     *
     * @return Collection<array-key, int<0, max>>
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
     * 站内搜索结果页（/search?q=）
     *
     * 返回 Response 而非 View 只为一件事：打 `X-Robots-Tag: noindex`。
     * 搜索页的 URL 空间是无限的（任意关键词 × 任意组合），被收录会产出成千上万
     * 低价值页面稀释整站权重——这是站内搜索的经典坑。canonical 一并关掉：
     * 已经 noindex 了，再自指一个规范地址是矛盾信号。
     *
     * 仍然可以被 CDN 缓存：q 是查询参数，每个关键词各自是一个静态 URL，
     * 且本方法不起 session（走 PUBLIC_STACK，同其它内容页）。
     */
    public function search(Request $request): Response
    {
        $search = app(SiteSearch::class);

        // 回显与查询用同一份归一结果，否则输入框里显示的词和实际搜的词会不一致
        $term   = $search->normalize((string) $request->query('q', ''));
        $groups = $search->search($term);

        $resultCount = array_sum(array_map(
            static fn (array $group): int => count($group['hits']),
            $groups
        ));

        $seoData = $this->buildListSeo($term !== '' ? '搜索「'.$term.'」' : '站内搜索');
        // 无限 URL 空间不该有自指 canonical
        $seoData['canonical'] = false;

        return response()
            ->view('filamentboot-site::search', compact('term', 'groups', 'resultCount', 'seoData'))
            ->header('X-Robots-Tag', 'noindex, follow');
    }

    /**
     * 静态页面（/{slug}，保留 slug 已在路由层排除）
     *
     * @param  string  $slug  页面 slug（参数绑定防注入，T-10-04-03）
     */
    public function page(string $slug): View
    {
        $record = SitePage::published()->where('slug', $slug)->firstOrFail();

        return view($this->pageTemplate($record), $this->pageViewData($record));
    }

    /**
     * 草稿预览（/preview/{page}，#16）
     *
     * 这是全站唯一**不走 scopePublished()** 的内容读取入口——它存在的理由
     * 就是让编辑在发布前看到草稿。软删除全局作用域保留：隐式绑定让已删除的
     * 页面直接 404，删掉的东西不该还能预览。
     *
     * 双通道授权，两条都不满足才 403：
     *   1. 带有效签名（后台生成的 15 分钟临时链接，可发给不登录后台的人过目）
     *   2. 已登录管理员且对该页面有 view 权限（编辑自己点进来不必先要签名）
     *
     * 只挂 signed 中间件会把已登录管理员挡在门外，所以签名校验在这里手工做。
     */
    public function preview(Request $request, SitePage $page): Response
    {
        $viaSignature = URL::hasValidSignature($request);
        $viaAdmin     = auth('admin')->check() && auth('admin')->user()?->can('view', $page);

        abort_unless($viaSignature || $viaAdmin, 403);

        $data = $this->pageViewData($page);

        // 预览页不输出 canonical / og:url：已经 noindex，再自指规范地址是矛盾信号
        $data['seoData']['canonical'] = false;

        return response()
            ->view($this->pageTemplate($page), $data)
            // 签名 URL 一旦外泄被抓取，草稿就进了搜索结果
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * 组装静态页视图数据（#13 区块渲染 + B1/B3 面包屑与结构化数据）
     *
     * 抽成独立方法供 page() 与 preview()（#16）共用：预览与正式渲染必须
     * 走同一套数据组装，否则预览看到的和发布后看到的会不是一回事。
     *
     * @return array<string, mixed>
     */
    protected function pageViewData(SitePage $record): array
    {
        $seoData = $this->buildSeo($record);

        $breadcrumbs = $this->breadcrumbs([
            ['label' => $record->title_zh, 'url' => null],
        ]);

        $renderer = app(BlockRenderer::class);

        // 区块贡献的结构化数据（目前是 faq → FAQPage）并入面包屑之后，
        // seo-meta 组件已支持节点列表，会逐个输出成独立的 ld+json 脚本
        $seoData['jsonLd'] = [
            $this->breadcrumbSchema($breadcrumbs),
            ...$renderer->structuredData($record->blocks),
        ];

        return [
            'record'      => $record,
            'breadcrumbs' => $breadcrumbs,
            'seoData'     => $seoData,
            'blocksHtml'  => $renderer->render($record->blocks),
        ];
    }

    /**
     * 解析页面模板视图名（#14）
     *
     * template 列的取值来自后台 Select（config 的 page_templates），但库里可能
     * 留着已被下架的模板标识，或宿主换了主题而新主题没提供那份视图。
     * 两种情况都回退 pages.show——一个模板缺失不该让已发布页面 404。
     */
    protected function pageTemplate(SitePage $record): string
    {
        $template = (string) ($record->template ?? '');

        // 字符集先卡一道：template 会拼进视图名，放宽等于给视图路径解析
        // 留下可被内容侧影响的入口（与 BlockRegistry 对 key 的约束同源）
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $template) !== 1 || $template === 'default') {
            return 'filamentboot-site::pages.show';
        }

        $candidate = 'filamentboot-site::pages.templates.'.$template;

        return ViewFacade::exists($candidate) ? $candidate : 'filamentboot-site::pages.show';
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
     * OG 图：记录 seo_og_image → 记录封面（ogImageUrl）→ 全局默认 OG 图（#20）
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

        // OG 图回退链（#20）：记录自填的 seo_og_image → 自身封面 → 全局默认
        //
        // seo_og_image 此前被完全忽略：这里只在 method_exists('ogImageUrl') 时取封面，
        // 而 SitePage 不是 media-library 模型没有该方法，于是后台「SEO」Tab 里填的
        // 「社交分享图 URL」从来没进过 og:image。页面级设置必须排在最前——
        // 它是作者对这一页的显式指定，优先级高于任何自动推导。
        $ogImage = (isset($record->seo_og_image) ? ($record->seo_og_image ?: '') : '') ?: null;

        if ($ogImage === null && method_exists($record, 'ogImageUrl')) {
            $ogImage = $record->ogImageUrl() ?: null;
        }

        $ogImage ??= $this->defaultOgImage();

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
