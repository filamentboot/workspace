{{--
 * 顶部导航栏组件（UI-SPEC §Component 1）
 *
 * 固定顶部 glassmorphism 效果，移动端 hamburger 抽屉（Alpine.js）。
 * 44px 最小触达区，aria-expanded/aria-controls/focus trap 无障碍支持。
 --}}
@php
    $isZh         = app()->getLocale() !== 'en';
    $companyName  = $isZh
        ? (optional($siteSettings ?? null)->company_name_zh ?? '晴空妙享科技')
        : (optional($siteSettings ?? null)->company_name_en ?? 'QKZ Tech');
    $logoPath     = optional($siteSettings ?? null)->logo;

    $navLinks = $isZh ? [
        ['href' => url('/cases'),     'label' => '装修案例'],
        ['href' => url('/solutions'), 'label' => '智能方案'],
        ['href' => url('/products'),  'label' => '智能产品'],
        ['href' => url('/about'),     'label' => '关于我们'],
        ['href' => url('/contact'),   'label' => '联系我们'],
    ] : [
        ['href' => url('/en/cases'),     'label' => 'Cases'],
        ['href' => url('/en/solutions'), 'label' => 'Solutions'],
        ['href' => url('/en/products'),  'label' => 'Products'],
        ['href' => url('/en/about'),     'label' => 'About Us'],
        ['href' => url('/en/contact'),   'label' => 'Contact'],
    ];

    $ctaLabel = $isZh ? '预约咨询' : 'Book a Consultation';
@endphp

<header
    class="fixed top-0 left-0 right-0 z-50 h-16 bg-site-surface/80 backdrop-blur-md border-b border-site"
    x-data="{ mobileNavOpen: false }"
    @keydown.escape.window="mobileNavOpen = false">

    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">

        {{-- 品牌 Logo / 公司名 --}}
        <a href="{{ url($isZh ? '/' : '/en') }}"
           class="flex items-center gap-3 min-h-[44px] focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none rounded-sm">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="max-h-10 w-auto">
            @else
                <span class="text-site-primary font-bold text-xl">{{ $companyName }}</span>
            @endif
        </a>

        {{-- 桌面导航（md:+ 可见） --}}
        <nav class="hidden md:flex items-center gap-1" aria-label="{{ $isZh ? '主导航' : 'Main Navigation' }}">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="inline-flex items-center min-h-[44px] px-3 text-sm text-site-secondary hover:text-site-accent transition-colors duration-200
                          focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none rounded-sm
                          {{ request()->is(ltrim(parse_url($link['href'], PHP_URL_PATH), '/') . '*') ? 'text-site-accent border-b-2 border-[--color-primary]' : '' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach

            {{-- 语言切换 --}}
            @include('filamentboot-site::components.lang-switcher')

            {{-- CTA 按钮 --}}
            <button
                type="button"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm ml-2
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
                onclick="document.getElementById('contact-panel')?.classList.remove('hidden')"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
        </nav>

        {{-- 移动端 Hamburger 按钮 --}}
        <button
            type="button"
            class="md:hidden inline-flex items-center justify-center min-w-[44px] min-h-[44px] text-site-secondary hover:text-site-primary
                   focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none rounded-lg"
            @click="mobileNavOpen = !mobileNavOpen"
            :aria-expanded="mobileNavOpen.toString()"
            aria-controls="mobile-nav"
            :aria-label="mobileNavOpen ? '{{ $isZh ? '关闭导航菜单' : 'Close navigation menu' }}' : '{{ $isZh ? '打开导航菜单' : 'Open navigation menu' }}'">
            {{-- Heroicons bars-3 --}}
            <svg x-show="!mobileNavOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            {{-- Heroicons x-mark --}}
            <svg x-show="mobileNavOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- 移动端导航抽屉 --}}
    <div
        id="mobile-nav"
        role="navigation"
        :aria-label="'{{ $isZh ? '移动端导航' : 'Mobile Navigation' }}'"
        class="md:hidden fixed inset-y-0 left-0 z-40 w-72 max-w-xs bg-site-surface border-r border-site shadow-2xl"
        x-show="mobileNavOpen"
        x-trap="mobileNavOpen"
        style="display: none;"
        @click.stop>

        {{-- 抽屉内头部 --}}
        <div class="flex items-center justify-between px-4 py-4 border-b border-site h-16">
            <span class="text-site-primary font-bold text-lg">{{ $companyName }}</span>
            <button
                type="button"
                class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] text-site-muted hover:text-site-primary
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-lg"
                @click="mobileNavOpen = false"
                aria-label="{{ $isZh ? '关闭导航菜单' : 'Close navigation menu' }}">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- 导航链接列表 --}}
        <nav class="px-4 py-6 space-y-1">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="flex items-center min-h-[44px] px-3 rounded-xl text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
                   @click="mobileNavOpen = false">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- 底部：语言切换 + CTA --}}
        <div class="px-4 py-4 border-t border-site space-y-4">
            @include('filamentboot-site::components.lang-switcher')
            <button
                type="button"
                class="btn-site-primary w-full inline-flex items-center justify-center min-h-[44px] px-5 py-3 rounded-full font-bold text-sm"
                onclick="document.getElementById('contact-panel')?.classList.remove('hidden'); mobileNavOpen = false"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
        </div>
    </div>

    {{-- 移动端背景遮罩 --}}
    <div
        class="md:hidden fixed inset-0 z-30 bg-black/50"
        x-show="mobileNavOpen"
        @click="mobileNavOpen = false"
        style="display: none;"
        aria-hidden="true">
    </div>
</header>

{{-- 为 fixed nav 预留顶部空间 --}}
<div class="h-16" aria-hidden="true"></div>
