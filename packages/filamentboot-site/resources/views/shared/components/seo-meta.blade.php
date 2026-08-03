{{--
 * SEO meta 组件（跨主题共享）
 *
 * 直出 title/description/keywords/OG 标签/canonical（UI-SPEC §SEO Contract，Pattern 5）。
 * 变量由 SiteFrontController 通过 $seoData 传入，站点设置由
 * SiteServiceProvider::shareSiteSettings() 以 $siteSettings 注入。
 *
 * CMS v1 为中文单语言，不再输出 hreflang 备用链接。
 *
 * og:image 仅在内容封面或站点默认 OG 图存在时输出。此前硬编码
 * asset('img/og-default.jpg')，该文件并不存在，线上 og:image 恒为 404。
 *
 * 期望变量：
 *   $seoData['title'] / ['description'] / ['keywords']
 *   $seoData['ogTitle'] / ['ogDescription'] / ['ogImage'] / ['ogType']
 *   $seoData['jsonLd'] — 可选，结构化数据数组（详情页才有，列表页不输出）
 *   $siteSettings — SiteSettings 实例（可为 null）
 --}}
@php
    $seoData = $seoData ?? [];

    $siteName = ($siteSettings?->company_name_zh ?: '') ?: config('app.name', '');

    $seoTitle    = $seoData['title'] ?? $siteName;
    $seoDesc     = ($seoData['description'] ?? '') ?: config('filamentboot-site.seo.fallback_description', '');
    $seoKeywords = $seoData['keywords'] ?? '';
    $ogTitle     = $seoData['ogTitle'] ?? $seoTitle;
    $ogDesc      = $seoData['ogDescription'] ?? $seoDesc;
    $ogImage     = $seoData['ogImage'] ?? ($siteSettings?->og_default_image ?: null);
    $ogType      = $seoData['ogType'] ?? 'website';

    // 标题已在控制器拼好站点名的场景不再重复追加
    $fullTitle = ($siteName !== '' && ! str_contains($seoTitle, $siteName))
        ? $seoTitle . ' — ' . $siteName
        : $seoTitle;

    // canonical 保留 page 等区分内容的参数，只剥广告/统计追踪参数。
    // 直接用 url()->current() 会丢掉整个查询串，使 /solutions?page=2 的 canonical
    // 指向 /solutions，搜索引擎据此判定列表页深层全是首页副本，不再索引。
    // 参数按键排序，保证同一组参数不同顺序产生同一个 canonical。
    $canonicalQuery = array_diff_key(
        request()->query(),
        array_flip((array) config('filamentboot-site.seo.canonical_ignored_params', []))
    );
    ksort($canonicalQuery);

    $canonical = url()->current()
        . ($canonicalQuery === [] ? '' : '?' . http_build_query($canonicalQuery));
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $seoDesc }}">
@if($seoKeywords !== '')
<meta name="keywords" content="{{ $seoKeywords }}">
@endif

{{-- Open Graph 基础标签（D-10-17） --}}
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDesc }}">
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:url" content="{{ $canonical }}">
@if($siteName !== '')
<meta property="og:site_name" content="{{ $siteName }}">
@endif

{{-- Canonical URL（防重复内容） --}}
<link rel="canonical" href="{{ $canonical }}">

{{-- 结构化数据（详情页由控制器构建：资讯与案例为 Article，产品为 Product）

     JSON_HEX_TAG 不可省：内容里出现 </script> 字面量时，不转义 < > 会提前闭合
     script 标签，把后续正文当 HTML 执行。转义后仍是合法 JSON-LD，解析不受影响。 --}}
@if(! empty($seoData['jsonLd']))
<script type="application/ld+json">{!! json_encode($seoData['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endif
