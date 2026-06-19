<?php

namespace LaravelStack\FilamentAdminSite\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use LaravelStack\FilamentAdminSite\Models\SiteCase;
use LaravelStack\FilamentAdminSite\Models\SitePage;
use LaravelStack\FilamentAdminSite\Models\SiteProduct;
use LaravelStack\FilamentAdminSite\Models\SiteSolution;
use LaravelStack\FilamentAdminSite\Settings\SiteSettings;

/**
 * 官网前台控制器
 *
 * 负责读取已发布内容并传递视图数据（含 SEO 回退链）。
 * 所有方法仅返回已发布（scopePublished）的内容，防止草稿泄露（T-10-04-04）。
 * slug 参数通过 Eloquent where() 参数绑定传递，防止 SQL 注入（T-10-04-03）。
 *
 * 视图命名空间 'filament-admin-site::' 由 SiteServiceProvider::registerThemeViews()
 * 动态指向当前主题目录（resources/views/themes/{active_theme}/），
 * 10-05 在主题目录下提供具体 Blade 模板。
 */
class SiteFrontController extends Controller
{
    /**
     * 官网首页
     *
     * 展示精选案例、精选方案、精选产品，并传递全局 SEO 数据。
     *
     * @return View
     */
    public function home(): View
    {
        $settings  = $this->resolveSettings();
        $locale    = app()->getLocale();
        $featuredCases     = SiteCase::published()->featured()->latest('published_at')->take(6)->get();
        $featuredSolutions = SiteSolution::published()->featured()->latest('published_at')->take(4)->get();
        $featuredProducts  = SiteProduct::published()->featured()->take(6)->get();

        $seoData = $this->buildHomeSeo($settings, $locale);

        return view('filament-admin-site::home', compact(
            'featuredCases',
            'featuredSolutions',
            'featuredProducts',
            'seoData',
            'locale',
            'settings'
        ));
    }

    /**
     * 装修案例列表页
     *
     * @return View
     */
    public function caseIndex(): View
    {
        $settings = $this->resolveSettings();
        $locale   = app()->getLocale();
        $records  = SiteCase::published()->latest('published_at')->paginate(12);
        $seoData  = $this->buildListSeo('案例', 'Cases', $settings, $locale);

        return view('filament-admin-site::cases.index', compact('records', 'seoData', 'locale', 'settings'));
    }

    /**
     * 装修案例详情页
     *
     * @param string $slug 案例 slug（参数绑定防注入，T-10-04-03）
     * @return View
     */
    public function caseShow(string $slug): View
    {
        $record   = SiteCase::published()->where('slug', $slug)->firstOrFail();
        $settings = $this->resolveSettings();
        $locale   = app()->getLocale();
        $seoData  = $this->buildSeo($record, $settings, $locale);

        return view('filament-admin-site::cases.show', compact('record', 'seoData', 'locale', 'settings'));
    }

    /**
     * 智能方案列表页
     *
     * @return View
     */
    public function solutionIndex(): View
    {
        $settings = $this->resolveSettings();
        $locale   = app()->getLocale();
        $records  = SiteSolution::published()->latest('published_at')->paginate(12);
        $seoData  = $this->buildListSeo('方案', 'Solutions', $settings, $locale);

        return view('filament-admin-site::solutions.index', compact('records', 'seoData', 'locale', 'settings'));
    }

    /**
     * 智能方案详情页
     *
     * @param string $slug 方案 slug（参数绑定防注入，T-10-04-03）
     * @return View
     */
    public function solutionShow(string $slug): View
    {
        $record   = SiteSolution::published()->where('slug', $slug)->firstOrFail();
        $settings = $this->resolveSettings();
        $locale   = app()->getLocale();
        $seoData  = $this->buildSeo($record, $settings, $locale);

        return view('filament-admin-site::solutions.show', compact('record', 'seoData', 'locale', 'settings'));
    }

    /**
     * 智能产品列表页
     *
     * @return View
     */
    public function productIndex(): View
    {
        $settings = $this->resolveSettings();
        $locale   = app()->getLocale();
        $records  = SiteProduct::published()->paginate(12);
        $seoData  = $this->buildListSeo('产品', 'Products', $settings, $locale);

        return view('filament-admin-site::products.index', compact('records', 'seoData', 'locale', 'settings'));
    }

    /**
     * 智能产品详情页
     *
     * @param string $slug 产品 slug（参数绑定防注入，T-10-04-03）
     * @return View
     */
    public function productShow(string $slug): View
    {
        $record   = SiteProduct::published()->where('slug', $slug)->firstOrFail();
        $settings = $this->resolveSettings();
        $locale   = app()->getLocale();
        $seoData  = $this->buildSeo($record, $settings, $locale);

        return view('filament-admin-site::products.show', compact('record', 'seoData', 'locale', 'settings'));
    }

    /**
     * 静态页面（/{slug}，排除 en，Pitfall 4）
     *
     * @param string $slug 页面 slug（参数绑定防注入，T-10-04-03）
     * @return View
     */
    public function page(string $slug): View
    {
        $record   = SitePage::published()->where('slug', $slug)->firstOrFail();
        $settings = $this->resolveSettings();
        $locale   = app()->getLocale();
        $seoData  = $this->buildSeo($record, $settings, $locale);

        return view('filament-admin-site::pages.show', compact('record', 'seoData', 'locale', 'settings'));
    }

    /**
     * SEO 回退链构建器（Pattern 5）
     *
     * 三层回退：记录 SEO 字段 → 记录标题字段 → SiteSettings 全局默认值 → config('app.name')
     * 按当前语言环境（zh/en）选择对应字段。
     *
     * @param object $record 内容记录（含 seo_title/seo_description/seo_keywords 字段）
     * @param object|null $settings SiteSettings 实例（降级时为 null）
     * @param string $locale 当前语言环境（'zh' 或 'en'）
     * @return array{title: string, description: string, keywords: string, ogTitle: string, ogDescription: string}
     */
    protected function buildSeo(object $record, mixed $settings, string $locale): array
    {
        $isEn = $locale === 'en';

        // 标题回退：记录 seo_title → 记录标题字段 → 全局默认 → app.name
        // 使用 isset() 检查 Eloquent 动态属性（property_exists 对 __get 魔术属性无效）
        $titleFallback = $isEn
            ? (isset($record->title_en) ? ($record->title_en ?: '') : '')
            : (isset($record->title_zh) ? ($record->title_zh ?: '') : '');

        $globalTitle = $settings
            ? ($isEn ? ($settings->seo_default_title_en ?: '') : ($settings->seo_default_title_zh ?: ''))
            : '';

        $title = ($record->seo_title ?: '')
            ?: $titleFallback
            ?: $globalTitle
            ?: config('app.name', '');

        // 描述回退：记录 seo_description → 记录 description 字段 → 全局默认
        // 使用 isset() 检查 Eloquent 动态属性
        $descFallback = '';
        if (isset($record->description_zh) || isset($record->description_en)) {
            $descFallback = $isEn
                ? (isset($record->description_en) ? ($record->description_en ?: '') : '')
                : (isset($record->description_zh) ? ($record->description_zh ?: '') : '');
        }

        $globalDesc = $settings
            ? ($isEn ? ($settings->seo_default_description_en ?: '') : ($settings->seo_default_description_zh ?: ''))
            : '';

        $description = $record->seo_description
            ?: $descFallback
            ?: $globalDesc
            ?: '';

        // 关键词
        $keywords = $record->seo_keywords ?? '';

        return [
            'title'         => $title,
            'description'   => $description,
            'keywords'      => $keywords,
            'ogTitle'       => $title,
            'ogDescription' => $description,
        ];
    }

    /**
     * 构建首页 SEO 数据（无具体记录，直接读全局设置）
     *
     * @param mixed $settings SiteSettings 实例或 null
     * @param string $locale 当前语言环境
     * @return array{title: string, description: string, keywords: string, ogTitle: string, ogDescription: string}
     */
    protected function buildHomeSeo(mixed $settings, string $locale): array
    {
        $isEn = $locale === 'en';

        $title = $settings
            ? ($isEn ? ($settings->seo_default_title_en ?: '') : ($settings->seo_default_title_zh ?: ''))
            : '';
        $title = $title ?: config('app.name', '');

        $description = $settings
            ? ($isEn ? ($settings->seo_default_description_en ?: '') : ($settings->seo_default_description_zh ?: ''))
            : '';

        return [
            'title'         => $title,
            'description'   => $description,
            'keywords'      => '',
            'ogTitle'       => $title,
            'ogDescription' => $description,
        ];
    }

    /**
     * 构建列表页 SEO 数据（使用全局默认 + 列表类型名称）
     *
     * @param string $labelZh 中文列表名称（如 '案例'）
     * @param string $labelEn 英文列表名称（如 'Cases'）
     * @param mixed $settings SiteSettings 实例或 null
     * @param string $locale 当前语言环境
     * @return array{title: string, description: string, keywords: string, ogTitle: string, ogDescription: string}
     */
    protected function buildListSeo(string $labelZh, string $labelEn, mixed $settings, string $locale): array
    {
        $isEn      = $locale === 'en';
        $appName   = config('app.name', '');
        $label     = $isEn ? $labelEn : $labelZh;

        $globalTitle = $settings
            ? ($isEn ? ($settings->seo_default_title_en ?: '') : ($settings->seo_default_title_zh ?: ''))
            : '';

        $title = ($globalTitle ?: $appName) ? "{$label} - " . ($globalTitle ?: $appName) : $label;

        $description = $settings
            ? ($isEn ? ($settings->seo_default_description_en ?: '') : ($settings->seo_default_description_zh ?: ''))
            : '';

        return [
            'title'         => $title,
            'description'   => $description,
            'keywords'      => '',
            'ogTitle'       => $title,
            'ogDescription' => $description,
        ];
    }

    /**
     * 解析 SiteSettings 实例（try/catch 降级防 settings 表未迁移崩溃，Pitfall 2）
     *
     * @return SiteSettings|null
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
