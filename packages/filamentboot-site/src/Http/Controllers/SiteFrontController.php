<?php

namespace Filamentboot\FilamentbootSite\Http\Controllers;

use Filamentboot\FilamentbootSite\Models\SiteCase;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Illuminate\Routing\Controller;
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
     * 展示精选案例、精选方案、精选产品，并传递全局 SEO 数据。
     */
    public function home(): View
    {
        $featuredCases     = SiteCase::published()->featured()->latest('published_at')->take(6)->get();
        $featuredSolutions = SiteSolution::published()->featured()->latest('published_at')->take(4)->get();
        $featuredProducts  = SiteProduct::published()->featured()->take(6)->get();

        $seoData = $this->buildHomeSeo();

        return view('filamentboot-site::home', compact(
            'featuredCases',
            'featuredSolutions',
            'featuredProducts',
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

        return view('filamentboot-site::cases.show', compact('record', 'seoData'));
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

        return view('filamentboot-site::solutions.show', compact('record', 'seoData'));
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

        return view('filamentboot-site::products.show', compact('record', 'seoData'));
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

        return view('filamentboot-site::pages.show', compact('record', 'seoData'));
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
        $descFallback  = isset($record->description_zh) ? ($record->description_zh ?: '') : '';

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
