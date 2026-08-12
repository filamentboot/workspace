{{--
 * 幻灯片 · 全屏主视觉（两套主题共用，二期 B1）
 *
 * 首页第一屏。有启用中的 HOME_TOP 幻灯片时替代 components/hero.blade.php，
 * 没有时由 home.blade.php 降级回那个单图 hero——下游没配幻灯片的站首页不能空一块。
 *
 * 入参：$banners（Collection<SiteBanner>，调用方保证非空）
 *
 * 文案全部来自 site_banners 表
 * ------------------------------
 * 刻意不学 components/hero.blade.php 把「智能家居 · 设计驱动改造」这类 slogan
 * 写死在包里——那是把某一家公司的定位固化进开源包，下游改不动。
 *
 * 文字压在实底面板上，不压在照片上
 * ------------------------------
 * 本主题的正文色是深灰 #212121，直接压在任意照片上无法保证对比度（照片亮度
 * 不可控，而蒙版要压到 80% 以上才安全，那时照片已经洗白了）。改成照片全幅铺底 +
 * 一块近实色面板承载文字：面板内对比度恒定 13.88:1，照片也还看得出是照片。
 * 这同时正是两套主题共用的用色纪律——大面积平涂 + 大量留白。
 *
 * 半透明背景走 bg-site-*-NN 这批语义类
 * ------------------------------------
 * 两条都不能走：
 * 1. `.bg-site-surface` 是 shared.css 里 @utility 声明的语义类，**不是
 *    Tailwind 主题色**，在它后面接斜杠透明度修饰符（`/94` 之类）无效——不报错、
 *    编译不出任何规则、元素背景完全透明。
 * 2. 内联 `color-mix()` 要 Chrome 111 / Safari 16.2，微信 Android 的 XWeb
 *    长期停在 Chromium 107 一档，不支持时整条声明被丢弃，落回同一个透明状态。
 * 所以 shared.css 里专门声明了 bg-site-base-35/85/94 这批档位，走的是
 * `rgb(<通道> / <alpha>)`——Chrome 65 起就有。门槛表见那个文件的头注释。
 *
 * （这段注释刻意不写出那个错误类名的完整形态：Tailwind v4 的内容探测扫的是
 * 原始文本，注释里的示例同样会被当成候选类。）
 *
 * Alpine 走内联 x-data，不引 Livewire
 * ----------------------------------
 * 公开页零 session 是硬约束：一个 Livewire 组件就会把带 data-csrf 的脚本注入
 * 页面 → 起 session → 整页缓存静默失效。形状照 products/show.blade.php:37 的
 * 图集轮播（{ active, total } + % 环绕翻页 + :class 高亮），resources/js/site.js
 * 只负责 Alpine.start()，不动它。
 --}}
@php
    use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerCtaAction;

    $total = $banners->count();
@endphp

{{-- 高度是 100dvh 减掉导航条，不是整屏
     ------------------------------------
     nav 组件末尾会输出一个 `h-16` 占位 div（各主题自己的 nav.blade.php:237 附近），
     所以 main 里的第一个区块起点在 y=64。整屏高度会让这一段比视口高 64px，
     结果是底部那排翻页控件落到首屏之外——用户看不到、也点不到。
     components/hero.blade.php 那个用的是整屏档，有同样的偏移，但它 bottom-8 放的
     只是装饰性的向下箭头，看不见不影响可用性，所以没改它。

     用 `min-h-site-screen-nav` 而不是 dvh 的 arbitrary value：`dvh` 要
     Chrome 108 / Safari 15.4，低于门槛整条 min-height 被丢弃、首屏塌成内容高度。
     那个类在同一条规则里先写 vh 回退再写 dvh，见 shared.css。 --}}
<section class="relative min-h-site-screen-nav overflow-hidden bg-site-base"
         aria-label="首页幻灯片"
         x-data="{
             active: 0,
             total: {{ $total }},
             timer: null,
             start() {
                 if (this.total < 2) return;
                 if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                 this.stop();
                 this.timer = setInterval(() => { this.active = (this.active + 1) % this.total }, 5000);
             },
             stop() { if (this.timer) { clearInterval(this.timer); this.timer = null } },
         }"
         x-init="start()"
         @mouseenter="stop()"
         @mouseleave="start()"
         @focusin="stop()"
         @focusout="start()">

    @foreach($banners as $index => $banner)
        @php $bCover = $banner->coverUrl('hero'); @endphp

        <div x-show="active === {{ $index }}"
             @if($index !== 0) style="display: none;" @endif
             class="absolute inset-0">

            {{-- 底图。用 <img> 而非 CSS 背景图：首屏这张是 LCP 元素，
                 需要 fetchpriority / decoding 这些只有 img 才有的属性。 --}}
            @if($bCover)
                <img src="{{ $bCover }}"
                     alt=""
                     class="absolute inset-0 w-full h-full object-cover"
                     @if($index === 0)
                         loading="eager"
                         fetchpriority="high"
                         decoding="sync"
                     @else
                         loading="lazy"
                         decoding="async"
                     @endif
                     width="1920"
                     height="1080"
                     aria-hidden="true">
            @endif

            {{-- 整体提亮一层，让照片与浅色页面明度接续。
                 与 hero.blade.php:36 同一取向：**提亮不压暗**——白底页面里
                 突然出现一块暗区会让明度断裂。 --}}
            <div class="absolute inset-0 bg-site-base-35" aria-hidden="true"></div>
        </div>
    @endforeach

    {{-- 文案面板。桌面左对齐、移动端居中靠下，都留足边距。 --}}
    <div class="relative z-10 min-h-site-screen-nav flex items-end md:items-center">
        <div class="w-full max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 pb-28 pt-32 md:py-32">

            @foreach($banners as $index => $banner)
                <div x-show="active === {{ $index }}"
                     @if($index !== 0) style="display: none;" @endif
                     class="max-w-2xl rounded-2xl border border-site p-8 md:p-12 bg-site-base-94">

                    {{-- 只有第一张是 h1。轮播三张全在首字节 HTML 里（靠 x-show 切换，
                         不是异步加载的），全写 h1 就是一页三个 h1——爬虫读到的是
                         整个 DOM，不是当前可见的那一张。第二张起降成 h2。 --}}
                    <{{ $index === 0 ? 'h1' : 'h2' }} class="text-3xl md:text-5xl lg:text-6xl font-bold text-site-primary leading-tight lg:leading-[1.1]">
                        {{ $banner->title }}
                    </{{ $index === 0 ? 'h1' : 'h2' }}>

                    @if($banner->subtitle)
                        <p class="mt-6 text-site-secondary text-base md:text-lg leading-relaxed">
                            {{ $banner->subtitle }}
                        </p>
                    @endif

                    {{-- 行动按钮。三种 cta_action 各一支，NONE 与配置不全时整行不渲染
                         （判断收在模型的 hasCallToAction()，视图不重复那套条件）。 --}}
                    @if($banner->hasCallToAction())
                        <div class="mt-10">
                            @if($banner->cta_action === BannerCtaAction::INQUIRY)
                                <button
                                    type="button"
                                    data-contact-trigger="banner"
                                    class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                                           focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                                    @click="$store.contactPanel.show('banner')"
                                    aria-controls="contact-panel"
                                    aria-label="{{ $banner->cta_label }}">
                                    {{ $banner->cta_label }}
                                </button>
                            @else
                                <a href="{{ $banner->cta_url }}"
                                   class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                                    {{ $banner->cta_label }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- 翻页控件。单张时不渲染。
         小屏抬到 bottom-24：`components/mobile-action-bar` 是 fixed bottom-0、
         z-[45]、只在 sm 以下渲染，翻页圆点放 bottom-8 会被它盖掉一半——点不到，
         而且它 z 值比本段高，靠 z-index 抢不回来。 --}}
    @if($total > 1)
        <div class="absolute bottom-24 sm:bottom-8 left-0 right-0 z-20">
            <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-4">
                <button type="button"
                        class="w-10 h-10 rounded-full border border-site text-site-primary flex items-center justify-center backdrop-blur-sm bg-site-base-85
                               focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                        @click="active = (active - 1 + total) % total"
                        aria-label="上一张幻灯片">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                {{-- 圆点：**可视的点 10px，可点的按钮 24px**。
                     WCAG 2.5.8（AA）要求触摸目标不小于 24×24 CSS px，原来点多大
                     按钮就多大，实测被 Lighthouse 判失败。放大按钮、把颜色绑定
                     移进内层 span，观感一点没变，手指和辅助技术拿到的是 24px。
                     容器的 gap 同步去掉：按钮撑到 24px 之后再留 8px 间距，
                     点与点之间会散开一截。 --}}
                <div class="flex">
                    @foreach($banners as $index => $banner)
                        <button type="button"
                                class="w-6 h-6 flex items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                                @click="active = {{ $index }}"
                                aria-label="查看第 {{ $index + 1 }} 张幻灯片">
                            <span class="w-2.5 h-2.5 rounded-full transition-colors"
                                  :class="active === {{ $index }} ? 'bg-(--color-primary)' : 'bg-site-elevated'"></span>
                        </button>
                    @endforeach
                </div>

                <button type="button"
                        class="w-10 h-10 rounded-full border border-site text-site-primary flex items-center justify-center backdrop-blur-sm bg-site-base-85
                               focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                        @click="active = (active + 1) % total"
                        aria-label="下一张幻灯片">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
    @endif
</section>
