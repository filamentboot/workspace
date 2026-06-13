{{--
 * SEO meta 组件
 *
 * 直出 title/description/keywords/OG 标签/canonical/hreflang（UI-SPEC §SEO Contract，Pattern 5）。
 * 变量由控制器通过 $seoData 数组传入，回退到 $siteSettings 全局默认值，
 * 最终回退到 config('app.name')。
 *
 * 期望变量（由 SiteFrontController 传入）：
 *   $seoData['title']       — 页面标题
 *   $seoData['description'] — 页面描述
 *   $seoData['keywords']    — 关键词
 *   $seoData['ogTitle']     — OG 标题
 *   $seoData['ogDescription']—OG 描述
 *   $seoData['ogImage']     — OG 图片 URL（可选）
 *   $seoData['ogType']      — OG 类型（默认 website）
 *   $seoData['urlZh']       — 中文页面 URL（hreflang）
 *   $seoData['urlEn']       — 英文页面 URL（hreflang）
 *   $siteSettings           — SiteSettings 实例（可选，降级用）
 --}}
@php
    $seoData       = $seoData ?? [];
    $companyNameZh = optional($siteSettings ?? null)->company_name_zh ?? config('app.name', '晴空妙享科技');
    $seoTitle      = $seoData['title'] ?? $companyNameZh;
    $seoDesc       = $seoData['description'] ?? (optional($siteSettings ?? null)->seo_default_description_zh ?? '');
    $seoKeywords   = $seoData['keywords'] ?? '';
    $ogTitle       = $seoData['ogTitle'] ?? $seoTitle;
    $ogDesc        = $seoData['ogDescription'] ?? $seoDesc;
    $ogImage       = $seoData['ogImage'] ?? asset('img/og-default.jpg');
    $ogType        = $seoData['ogType'] ?? 'website';
    $urlZh         = $seoData['urlZh'] ?? url()->current();
    $urlEn         = $seoData['urlEn'] ?? '';
@endphp

<title>{{ $seoTitle }} — {{ $companyNameZh }}</title>
<meta name="description" content="{{ $seoDesc }}">
<meta name="keywords" content="{{ $seoKeywords }}">

{{-- Open Graph 基础标签（D-10-17） --}}
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDesc }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:site_name" content="{{ $companyNameZh }}">

{{-- Canonical URL（防重复内容） --}}
<link rel="canonical" href="{{ url()->current() }}">

{{-- hreflang 双语备用链接（D-10-07，SEO 国际化） --}}
<link rel="alternate" hreflang="zh" href="{{ $urlZh }}">
@if($urlEn)
<link rel="alternate" hreflang="en" href="{{ $urlEn }}">
@endif
