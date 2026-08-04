{{--
 * 顶部导航栏（tech-product 浅色主题）
 *
 * 与 decoration 行为一致：固定顶部、移动端抽屉、44px 触达区、
 * 咨询 CTA 统一走 $store.contactPanel。视觉上采用浅色描边而非玻璃拟态。
 --}}
@php
    $companyName = ($siteSettings?->company_name_zh ?: '') ?: config('app.name', '');
    $logoPath    = $siteSettings?->logo;

    // 后台配了 main 菜单就用它，没配则回退下面这份硬编码列表（#17）。
    // 兜底数组留在各主题的 blade 里而不是抽进 PHP：抽出去会把两个主题的
    // 导航结构焊死。删光菜单必须回退而不是白屏，这是升级安全的硬要求。
    $navLinks = app(\Filamentboot\FilamentbootSite\Cms\Services\MenuResolver::class)->resolve('main') ?? [
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
                @php($children = $link['children'] ?? [])

                @if($children === [])
                    <a href="{{ $link['href'] }}"
                       @if($link['target'] ?? null) target="{{ $link['target'] }}" rel="noopener noreferrer" @endif
                       class="inline-flex items-center min-h-[44px] px-3 text-sm font-medium text-site-secondary hover:text-site-accent transition-colors duration-200
                              focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-md
                              {{ request()->is(ltrim(parse_url($link['href'], PHP_URL_PATH) ?? '', '/') . '*') ? 'text-site-accent' : '' }}">
                        {{ $link['label'] }}
                    </a>
                @else
                    {{-- 二级下拉（#28）。父项本身仍可点：既是栏目页又有子项的入口
                         若只能开合不能跳转，桌面端就进不去那一页了。 --}}
                    <div class="relative"
                         x-data="{ open: false }"
                         @mouseenter="open = true"
                         @mouseleave="open = false"
                         @keydown.escape="open = false">
                        <div class="inline-flex items-center">
                            <a href="{{ $link['href'] }}"
                               @if($link['target'] ?? null) target="{{ $link['target'] }}" rel="noopener noreferrer" @endif
                               class="inline-flex items-center min-h-[44px] pl-3 text-sm font-medium text-site-secondary hover:text-site-accent transition-colors duration-200
                                      focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-md
                                      {{ request()->is(ltrim(parse_url($link['href'], PHP_URL_PATH) ?? '', '/') . '*') ? 'text-site-accent' : '' }}">
                                {{ $link['label'] }}
                            </a>
                            <button type="button"
                                    class="inline-flex items-center min-h-[44px] pl-1 pr-3 text-site-secondary hover:text-site-accent
                                           focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-md"
                                    :aria-expanded="open.toString()"
                                    aria-label="展开「{{ $link['label'] }}」子菜单"
                                    @click="open = ! open">
                                <svg class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-180'"
                                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>

                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute left-0 top-full min-w-[11rem] py-2 bg-site-surface border border-site rounded-lg shadow-lg">
                            @foreach($children as $child)
                                <a href="{{ $child['href'] }}"
                                   @if($child['target'] ?? null) target="{{ $child['target'] }}" rel="noopener noreferrer" @endif
                                   class="block min-h-[44px] px-4 py-2 text-sm text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200
                                          focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none">
                                    {{ $child['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- 站内搜索入口：给图标链接而不是内嵌输入框，导航栏塞一个输入框会挤掉栏目位，
                 而搜索页本身就有输入框，少一次布局妥协 --}}
            <a href="{{ route('site.search') }}"
               class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] text-site-secondary hover:text-site-accent transition-colors duration-200
                      focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:outline-none rounded-lg"
               aria-label="站内搜索">
                {{-- Heroicons magnifying-glass --}}
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </a>

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
                   @if($link['target'] ?? null) target="{{ $link['target'] }}" rel="noopener noreferrer" @endif
                   class="flex items-center min-h-[44px] px-3 rounded-lg text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
                   @click="mobileNavOpen = false">
                    {{ $link['label'] }}
                </a>

                {{-- 二级项在移动端缩进平铺（#28）：抽屉里再套折叠会多一次点击，
                     而层级最多两层，全展开更省事 --}}
                @foreach($link['children'] ?? [] as $child)
                    <a href="{{ $child['href'] }}"
                       @if($child['target'] ?? null) target="{{ $child['target'] }}" rel="noopener noreferrer" @endif
                       class="flex items-center min-h-[44px] pl-7 pr-3 rounded-lg text-sm text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
                       @click="mobileNavOpen = false">
                        {{ $child['label'] }}
                    </a>
                @endforeach
            @endforeach

            {{-- 站内搜索（抽屉里放在栏目之后：它不是栏目，是工具） --}}
            <a href="{{ route('site.search') }}"
               class="flex items-center gap-2 min-h-[44px] px-3 rounded-lg text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
               @click="mobileNavOpen = false">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                站内搜索
            </a>
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
