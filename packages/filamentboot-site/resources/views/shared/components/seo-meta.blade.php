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

    // canonical 去掉查询串，避免分页/追踪参数产生重复内容
    $canonical = url()->current();
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
