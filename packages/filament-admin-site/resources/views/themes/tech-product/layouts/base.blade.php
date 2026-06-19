{{--
 * tech-product 主题基础布局骨架
 *
 * 浅色主题，<html> 无 class="dark"（UI-SPEC §tech-product skeleton requirement）。
 * v0.5 为最小可视骨架；完整模板在 v1.x 完善。
 * @vite 指向 tech-product.css（浅色 token）。
 --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    {{-- SEO head（内联最小版，tech-product v0.5 骨架；v1.x 提取为组件） --}}
    @php
        $seoData       = $seoData ?? [];
        $companyNameZh = optional($siteSettings ?? null)->company_name_zh ?? config('app.name', '晴空妙享科技');
        $seoTitle      = ($seoData['title'] ?? null) ?: $companyNameZh;
        $seoDesc       = ($seoData['description'] ?? null) ?: (optional($siteSettings ?? null)->seo_default_description_zh ?? '');
        $ogTitle       = $seoData['ogTitle'] ?? $seoTitle;
        $ogDesc        = $seoData['ogDescription'] ?? $seoDesc;
    @endphp
    <title>{{ $seoTitle }} — {{ $companyNameZh }}</title>
    <meta name="description" content="{{ $seoDesc }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ $companyNameZh }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">

    {{-- tech-product 主题 CSS（浅色 token，Tailwind v4 CSS-first） --}}
    @vite('vendor/laravelstack/filament-admin-site/resources/css/themes/tech-product.css')

    @stack('head')
</head>
<body class="bg-site-base text-site-primary font-sans antialiased">

    {{-- 跳到主内容（无障碍） --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-site-surface focus:text-site-primary focus:rounded-lg focus:ring-2 focus:ring-[--color-primary] focus:outline-none">
        跳到主内容
    </a>

    @yield('body')

    @stack('scripts')
</body>
</html>
