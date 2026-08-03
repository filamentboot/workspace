{{--
 * tech-product 主题基础布局
 *
 * 浅色 SaaS/科技产品风格，<html> 不加 class="dark"（UI-SPEC §tech-product）。
 * SEO meta 与询盘面板 Store 复用 shared 层组件，与 decoration 行为一致：
 * 此前本布局内联了一份简化 SEO head，缺 keywords/og:image/canonical 逻辑，
 * 两套主题的 SEO 输出并不等价。
 --}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    {{-- SEO meta 组件（title/description/keywords/OG/canonical） --}}
    @include('filamentboot-site::components.seo-meta')

    {{-- 询盘面板全局 Store，须早于任何 CTA 渲染 --}}
    @include('filamentboot-site::components.contact-panel-store')

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">

    {{-- tech-product 主题 CSS（入口路径由 ThemeAsset 按 Vite manifest 解析） --}}
    @vite(\Filamentboot\FilamentbootSite\Support\ThemeAsset::viteEntry('tech-product'))

    {{-- 统计代码注入位（A3）：结构化 ID 生成的代码 + 自定义 head 代码块 --}}
    @include('filamentboot-site::components.analytics', ['position' => 'head'])

    @stack('head')
</head>
{{-- body 级 x-data：Alpine 只处理 x-data 根之内的指令，统一建立顶层 Alpine 根 --}}
<body x-data class="bg-site-base text-site-primary font-sans antialiased">

    {{-- 跳到主内容（无障碍快捷链接） --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-site-surface focus:text-site-primary focus:rounded-lg focus:ring-2 focus:ring-[--color-primary] focus:outline-none">
        跳到主内容
    </a>

    @yield('body')

    {{-- 统计代码注入位（A3）：自定义 body 尾代码块 + 表单转化事件上报 --}}
    @include('filamentboot-site::components.analytics', ['position' => 'body'])

    @stack('scripts')
</body>
</html>
