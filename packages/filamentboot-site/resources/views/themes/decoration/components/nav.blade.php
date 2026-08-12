{{--
 * 顶部导航栏组件（UI-SPEC §Component 1）
 *
 * 固定顶部 glassmorphism 效果，移动端 hamburger 抽屉（Alpine.js）。
 * 44px 最小触达区，aria-expanded/aria-controls/focus trap 无障碍支持。
 *
 * 咨询 CTA 统一调用 $store.contactPanel.show()，与悬浮按钮共用同一面板。
 * $siteSettings 由 SiteServiceProvider::shareSiteSettings() 注入。
 --}}
@php
    $companyName = ($siteSettings?->company_name_zh ?: '') ?: config('app.name', '');
    $logoPath    = $siteSettings?->logoUrl();

    // 后台配了 main 菜单就用它，没配则回退下面这份硬编码列表（#17）。
    // 兜底数组留在各主题的 blade 里而不是抽进 PHP：抽出去会把两个主题的
    // 导航结构焊死。删光菜单必须回退而不是白屏，这是升级安全的硬要求。
    // 内容类型条目的文案取自 ContentTypeLabels（七期批次 2），与
    // SiteFrontMenuSeeder::decorationMenus()['main']、面包屑、SEO 标题共用同一份词，
    // 非内容类型的项（关于/联系）不属于这套词表，仍然留字面量。
    $navLinks = app(\Filamentboot\FilamentbootSite\Cms\Services\MenuResolver::class)->resolve('main') ?? [
        ['href' => route('site.cases.index'),     'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::case()],
        ['href' => route('site.solutions.index'), 'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::solution()],
        ['href' => route('site.packages.index'),  'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::package()],
        ['href' => route('site.products.index'),  'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::product()],
        ['href' => route('site.news.index'),      'label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::news()],
        ['href' => route('site.page', 'about'),   'label' => '关于我们'],
        ['href' => route('site.page', 'contact'), 'label' => '联系我们'],
    ];

    $ctaLabel = '预约咨询';
@endphp

{{-- 半透明底色用 `bg-site-surface-80`，不能给 `bg-site-surface` 加斜杠透明度
     ---------------------------------------------------------------------
     `.bg-site-surface` 是 decoration.css 里 @utility 声明的语义类，**不是
     Tailwind 主题色**，斜杠透明度修饰符对它无效：不报错、编译结果里一条规则
     都没有、元素背景完全透明。2026-08-05 实测这里的 computed background-color
     是 `rgba(0, 0, 0, 0)` —— 固定导航条一直只有 backdrop-blur 和下边框，
     页面内容滚上来会直接透过去。
     （注释里刻意不写出错误类名的完整形态：v4 的内容探测扫原始文本，注释也算。）

     2026-08-08 由内联 color-mix() 改成这个类：那个函数要 Chrome 111 /
     Safari 16.2，微信 Android 的 XWeb 长期停在 Chromium 107 一档，不支持时
     **整条声明被丢弃**，落回的正是上面那个完全透明的状态。
     档位表与门槛见 decoration.css 文件头「浏览器基线」。 --}}
<header
    class="fixed top-0 left-0 right-0 z-50 h-16 backdrop-blur-md border-b border-site bg-site-surface-80"
    x-data="{ mobileNavOpen: false }"
    @keydown.escape.window="mobileNavOpen = false">

    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">

        {{-- 品牌 Logo / 公司名 --}}
        <a href="{{ route('site.home') }}"
           class="flex items-center gap-3 min-h-[44px] focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none rounded-sm">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="max-h-10 w-auto">
            @else
                <span class="text-site-primary font-bold text-xl">{{ $companyName }}</span>
            @endif
        </a>

        {{-- 桌面导航（md:+ 可见） --}}
        <nav class="hidden md:flex items-center gap-1" aria-label="主导航">
            @foreach($navLinks as $link)
                @php($children = $link['children'] ?? [])

                @if($children === [])
                    <a href="{{ $link['href'] }}"
                       @if($link['target'] ?? null) target="{{ $link['target'] }}" rel="noopener noreferrer" @endif
                       class="inline-flex items-center min-h-[44px] px-3 text-sm text-site-secondary hover:text-site-accent transition-colors duration-200
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none rounded-sm
                              {{ request()->is(ltrim(parse_url($link['href'], PHP_URL_PATH) ?? '', '/') . '*') ? 'text-site-accent border-b-2 border-(--color-primary)' : '' }}">
                        {{ $link['label'] }}
                    </a>
                @else
                    {{-- 二级下拉（#28）。父项自己也是可点的链接，另给一个箭头按钮开合：
                         把整个父项做成只开合不跳转，会让「关于我们」这类既是栏目页
                         又有子项的入口在桌面端点不进去。 --}}
                    <div class="relative"
                         x-data="{ open: false }"
                         @mouseenter="open = true"
                         @mouseleave="open = false"
                         @keydown.escape="open = false">
                        <div class="inline-flex items-center">
                            <a href="{{ $link['href'] }}"
                               @if($link['target'] ?? null) target="{{ $link['target'] }}" rel="noopener noreferrer" @endif
                               class="inline-flex items-center min-h-[44px] pl-3 text-sm text-site-secondary hover:text-site-accent transition-colors duration-200
                                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none rounded-sm
                                      {{ request()->is(ltrim(parse_url($link['href'], PHP_URL_PATH) ?? '', '/') . '*') ? 'text-site-accent' : '' }}">
                                {{ $link['label'] }}
                            </a>
                            <button type="button"
                                    class="inline-flex items-center min-h-[44px] pl-1 pr-3 text-site-secondary hover:text-site-accent
                                           focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm"
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
                             class="absolute left-0 top-full min-w-[11rem] py-2 bg-site-surface border border-site rounded-xl shadow-xl">
                            @foreach($children as $child)
                                <a href="{{ $child['href'] }}"
                                   @if($child['target'] ?? null) target="{{ $child['target'] }}" rel="noopener noreferrer" @endif
                                   class="block min-h-[44px] px-4 py-2 text-sm text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200
                                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none">
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
                      focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none rounded-lg"
               aria-label="站内搜索">
                {{-- Heroicons magnifying-glass --}}
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </a>

            {{-- CTA 按钮（与悬浮询盘按钮共用 $store.contactPanel） --}}
            <button
                type="button"
                data-contact-trigger="nav-desktop"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-full text-sm ml-2
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                @click="$store.contactPanel.show('nav-desktop')"
                aria-controls="contact-panel"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
        </nav>

        {{-- 移动端 Hamburger 按钮 --}}
        <button
            type="button"
            class="md:hidden inline-flex items-center justify-center min-w-[44px] min-h-[44px] text-site-secondary hover:text-site-primary
                   focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none rounded-lg"
            @click="mobileNavOpen = ! mobileNavOpen"
            :aria-expanded="mobileNavOpen.toString()"
            aria-controls="mobile-nav"
            :aria-label="mobileNavOpen ? '关闭导航菜单' : '打开导航菜单'">
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
        aria-label="移动端导航"
        class="md:hidden fixed inset-y-0 left-0 z-[60] w-72 max-w-xs bg-site-surface border-r border-site shadow-2xl"
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
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-lg"
                @click="mobileNavOpen = false"
                aria-label="关闭导航菜单">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- 导航链接列表 --}}
        <nav class="px-4 py-6 space-y-1">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}"
                   @if($link['target'] ?? null) target="{{ $link['target'] }}" rel="noopener noreferrer" @endif
                   class="flex items-center min-h-[44px] px-3 rounded-xl text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
                   @click="mobileNavOpen = false">
                    {{ $link['label'] }}
                </a>

                {{-- 二级项在移动端直接缩进平铺（#28）：抽屉里再套一层折叠会多一次点击，
                     而导航层级最多两层，全部展开更省事 --}}
                @foreach($link['children'] ?? [] as $child)
                    <a href="{{ $child['href'] }}"
                       @if($child['target'] ?? null) target="{{ $child['target'] }}" rel="noopener noreferrer" @endif
                       class="flex items-center min-h-[44px] pl-7 pr-3 rounded-xl text-sm text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
                       @click="mobileNavOpen = false">
                        {{ $child['label'] }}
                    </a>
                @endforeach
            @endforeach

            {{-- 站内搜索（抽屉里放在栏目之后：它不是栏目，是工具） --}}
            <a href="{{ route('site.search') }}"
               class="flex items-center gap-2 min-h-[44px] px-3 rounded-xl text-site-secondary hover:text-site-accent hover:bg-site-elevated transition-colors duration-200"
               @click="mobileNavOpen = false">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                站内搜索
            </a>
        </nav>

        {{-- 底部 CTA --}}
        <div class="px-4 py-4 border-t border-site">
            <button
                type="button"
                data-contact-trigger="nav-mobile"
                class="btn-site-primary w-full inline-flex items-center justify-center min-h-[44px] px-5 py-3 rounded-full font-bold text-sm"
                @click="mobileNavOpen = false; $store.contactPanel.show('nav-mobile')"
                aria-controls="contact-panel"
                aria-label="{{ $ctaLabel }}">
                {{ $ctaLabel }}
            </button>
        </div>
    </div>

    {{-- 移动端背景遮罩
         `bg-site-scrim` 是专门的遮罩档，不跟主题背景色走：抽屉背后的页面需要被
         压暗才能让抽屉浮起来，换成 bg-site-base/surface 的任何一档都会让白色抽屉
         和白色背板糊在一起。浅色化时只把透明度从 50% 收到 40%——白底页面上
         50% 纯黑的明度落差过陡。取值在 decoration.css 的 --site-scrim-rgb。
         （原先写的是 Tailwind 调色板的纯黑加斜杠透明度，那会把默认调色板拖进
         产物，见 decoration.css 文件头「浏览器基线」。这里刻意不写出原类名的
         完整形态：v4 的内容探测扫原始文本，注释里的示例也会被编译进产物。） --}}
    <div
        class="md:hidden fixed inset-0 z-[55] bg-site-scrim"
        x-show="mobileNavOpen"
        @click="mobileNavOpen = false"
        style="display: none;"
        aria-hidden="true">
    </div>
</header>

{{-- 为 fixed nav 预留顶部空间 --}}
<div class="h-16" aria-hidden="true"></div>
