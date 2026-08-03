{{--
 * 顶部导航栏（tech-product 浅色主题）
 *
 * 与 decoration 行为一致：固定顶部、移动端抽屉、44px 触达区、
 * 咨询 CTA 统一走 $store.contactPanel。视觉上采用浅色描边而非玻璃拟态。
 --}}
@php
    $companyName = ($siteSettings?->company_name_zh ?: '') ?: config('app.name', '');
    $logoPath    = $siteSettings?->logo;

    $navLinks = [
        ['href' => route('site.cases.index'),     'label' => '装修案例'],
        ['href' => route('site.solutions.index'), 'label' => '智能方案'],
        ['href' => route('site.products.index'),  'label' => '智能产品'],
        ['href' => route('site.news.index'),      'label' => '资讯中心'],
        ['href' => route('site.page', 'about'),   'label' => '关于我们'],
        ['href' => route('site.page', 'contact'), 'label' => '联系我们'],
    ];

    $ctaLabel = '预约咨询';
@endphp

<header
    class="fixed top-0 left-0 right-0 z-50 h-16 bg-site-base/90 backdrop-blur border-b border-site"
    x-data="{ mobileNavOpen: false }"
    @keydown.escape.window="mobileNavOpen = false">

    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">

        {{-- 品牌 --}}
        <a href="{{ route('site.home') }}"
           class="flex items-center gap-3 min-h-[44px] focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="max-h-9 w-auto">
            @else
                <span class="text-site-primary font-bold text-lg tracking-tight">{{ $companyName }}</span>
            @endif
        </a>

        {{-- 桌面导航 --}}
        <nav class="hidden md:flex items-center gap-1" aria-label="主导航">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="inline-flex items-center min-h-[44px] px-3 text-sm font-medium text-site-secondary hover:text-site-accent transition-colors duration-200
                          focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-md
                          {{ request()->is(ltrim(parse_url($link['href'], PHP_URL_PATH) ?? '', '/') . '*') ? 'text-site-accent' : '' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach

            <button
                type="button"
                data-contact-trigger="nav-desktop"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-lg text-sm ml-3
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:outline-none"
                @click="$store.contactPanel.show('nav-desktop')"
                aria-controls="contact-panel"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
        </nav>

        {{-- 移动端 Hamburger --}}
        <button
            type="button"
            class="md:hidden inline-flex items-center justify-center min-w-[44px] min-h-[44px] text-site-secondary hover:text-site-primary
                   focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-lg"
            @click="mobileNavOpen = ! mobileNavOpen"
            :aria-expanded="mobileNavOpen.toString()"
            aria-controls="mobile-nav"
            :aria-label="mobileNavOpen ? '关闭导航菜单' : '打开导航菜单'">
            <svg x-show="!mobileNavOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <svg x-show="mobileNavOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- 移动端抽屉 --}}
    <div
        id="mobile-nav"
        role="navigation"
        aria-label="移动端导航"
        class="md:hidden fixed inset-y-0 left-0 z-[60] w-72 max-w-xs bg-site-base border-r border-site shadow-xl"
        x-show="mobileNavOpen"
        x-trap="mobileNavOpen"
        style="display: none;"
        @click.stop>

        <div class="flex items-center justify-between px-4 py-4 border-b border-site h-16">
            <span class="text-site-primary font-bold">{{ $companyName }}</span>
            <button
                type="button"
                class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] text-site-secondary hover:text-site-primary
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-lg"
                @click="mobileNavOpen = false"
                aria-label="关闭导航菜单">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="px-4 py-6 space-y-1">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="flex items-center min-h-[44px] px-3 rounded-lg text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
                   @click="mobileNavOpen = false">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="px-4 py-4 border-t border-site">
            <button
                type="button"
                data-contact-trigger="nav-mobile"
                class="btn-site-primary w-full inline-flex items-center justify-center min-h-[44px] px-5 py-3 rounded-lg font-bold text-sm"
                @click="mobileNavOpen = false; $store.contactPanel.show('nav-mobile')"
                aria-controls="contact-panel"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
        </div>
    </div>

    {{-- 移动端遮罩 --}}
    <div
        class="md:hidden fixed inset-0 z-[55] bg-slate-900/40"
        x-show="mobileNavOpen"
        @click="mobileNavOpen = false"
        style="display: none;"
        aria-hidden="true">
    </div>
</header>

{{-- 为 fixed nav 预留顶部空间 --}}
<div class="h-16" aria-hidden="true"></div>
