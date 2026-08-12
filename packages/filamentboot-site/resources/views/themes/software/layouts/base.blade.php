{{--
 * software 主题基础布局
 *
 * 包含 SEO meta 组件、询盘面板 Store、Google Fonts、vite 主题 CSS 注入。
 * skip-nav 无障碍快捷链接（UI-SPEC §Accessibility）。
 *
 * 二期浅色化后 <html> 不再加 class="dark"（与 decoration 一致）。
 * 那个 class 唯一服务的是 software.css 里的 @custom-variant dark (.dark &)，
 * 而全仓库没有任何视图用过 dark: 变体，指令与 class 已一并删除。
 --}}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    {{-- SEO meta 组件（title/description/keywords/OG/canonical，Pattern 5） --}}
    @include('filamentboot-site::components.seo-meta')

    {{-- 询盘面板全局 Store，须早于任何 CTA 渲染 --}}
    @include('filamentboot-site::components.contact-panel-store')

    {{-- 首触渠道归因（客户端 localStorage，#29 起不再走 session） --}}
    @include('filamentboot-site::components.attribution-store')

    {{-- Google Fonts：Inter（拉丁/数字）+ Noto Sans SC（中文） --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">

    {{-- software 主题 CSS（Tailwind v4 CSS-first，包含 @theme token + 语义工具类）
         入口路径由 ThemeAsset 按 Vite manifest 实际命中的候选解析，
         兼容真实安装、宿主发布资源与 monorepo 符号链接三种形态。
         宿主需在 vite.config.js 的 input 中声明该路径后执行 npm run build。 --}}
    @vite(\Filamentboot\FilamentbootSite\Cms\Themes\ThemeAsset::viteEntries('software'))

    {{-- 统计代码注入位（A3）：结构化 ID 生成的代码 + 自定义 head 代码块 --}}
    @include('filamentboot-site::components.analytics', ['position' => 'head'])

    @stack('head')
</head>
{{-- body 级 x-data：Alpine 只处理 x-data 根之内的指令。
     页面各处的咨询 CTA（hero、卡片、详情页 CTA）都不在自己的 x-data 里，
     统一由此建立顶层 Alpine 根，避免遗漏某个入口。 --}}
<body x-data class="bg-site-base text-site-primary font-sans antialiased" style="font-feature-settings: 'kern'">

    {{-- 跳到主内容（无障碍快捷链接，UI-SPEC §Accessibility skip-nav） --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[9999] focus:px-4 focus:py-2 focus:bg-site-surface focus:text-site-primary focus:rounded-lg focus:ring-2 focus:ring-(--color-primary) focus:outline-none">
        跳到主内容
    </a>

    @yield('body')

    {{-- 统计代码注入位（A3）：自定义 body 尾代码块 + 表单转化事件上报 --}}
    @include('filamentboot-site::components.analytics', ['position' => 'body'])
    @include('filamentboot-site::components.live-chat')

    @stack('scripts')
</body>
</html>
