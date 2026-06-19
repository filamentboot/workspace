{{--
 * decoration 主题基础布局
 *
 * <html class="dark"> 固定深色模式（UI-SPEC §Dark Mode）。
 * 包含 SEO meta 组件、Google Fonts CDN、vite 主题 CSS 注入。
 * skip-nav 无障碍快捷链接（UI-SPEC §Accessibility）。
 --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    {{-- SEO meta 组件（title/description/keywords/OG/canonical/hreflang，Pattern 5） --}}
    @include('filament-admin-site::components.seo-meta')

    {{-- Google Fonts：Inter（拉丁/数字）+ Noto Sans SC（中文） --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">

    {{-- decoration 主题 CSS（Tailwind v4 CSS-first，包含 @theme token + 语义工具类）
         Vite 入口路径通过 vendor/ 访问，与宿主项目 vite.config.js 输入声明一致。
         须在宿主项目 vite.config.js 的 input 中添加此路径后执行 npm run build。 --}}
    @vite('vendor/laravelstack/filament-admin-site/resources/css/themes/decoration.css')

    @stack('head')
</head>
<body class="bg-site-base text-site-primary font-sans antialiased" style="font-feature-settings: 'kern'">

    {{-- 跳到主内容（无障碍快捷链接，UI-SPEC §Accessibility skip-nav） --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-site-surface focus:text-site-primary focus:rounded-lg focus:ring-2 focus:ring-[--color-primary] focus:outline-none">
        跳到主内容
    </a>

    @yield('body')

    @stack('scripts')
</body>
</html>
