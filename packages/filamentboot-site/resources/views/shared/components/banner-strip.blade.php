{{--
 * 幻灯片 · 矮横幅（两套主题共用，二期 B1）
 *
 * 四个列表页顶部。与 banner-hero 的区别只是版式——版式由视图决定，
 * 不由 BannerPosition 携带：同一个位置将来换版式不该动数据。
 *
 * 入参：$position（BannerPosition 枚举）
 *
 * 数据自己取，不由控制器传
 * ----------------------
 * 与 components/nav.blade.php:17 取 MenuResolver 同一条路子。走控制器要改
 * 四个 index 方法、给每个视图约定变量名，首页那份还得挂到 HomeSectionProvider
 * 上，等于同一件事两套机制。视图侧解析让「哪个位置放幻灯片」纯粹是视图的事。
 *
 * 没有生效中的幻灯片时**整段不渲染**（不留空盒子、不占垂直间距），
 * 所以列表页可以无条件 @include，不必各自写一遍 @if。
 *
 * 半透明背景走 bg-site-*-NN 语义类的理由、Alpine 不引 Livewire 的理由，
 * 见 banner-hero.blade.php 的文件头。
 --}}
@php
    use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerCtaAction;

    $stripBanners = app(\Filamentboot\FilamentbootSite\Modules\Corporate\Banners\BannerProvider::class)
        ->forPosition($position);

    $stripTotal = $stripBanners->count();
@endphp

@if($stripTotal > 0)
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <section class="relative overflow-hidden rounded-2xl border border-site bg-site-elevated"
                 aria-label="{{ $position->label() }}幻灯片"
                 x-data="{
                     active: 0,
                     total: {{ $stripTotal }},
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

            @foreach($stripBanners as $index => $banner)
                @php $bCover = $banner->coverUrl('hero'); @endphp

                <div x-show="active === {{ $index }}"
                     @if($index !== 0) style="display: none;" @endif
                     class="relative">

                    @if($bCover)
                        <img src="{{ $bCover }}"
                             alt=""
                             class="absolute inset-0 w-full h-full object-cover"
                             loading="lazy"
                             decoding="async"
                             width="1920"
                             height="1080"
                             aria-hidden="true">
                        <div class="absolute inset-0 bg-site-base-45" aria-hidden="true"></div>
                    @endif

                    {{-- 文字面板。矮横幅高度有限，只出主标题 + 一行副标题 + 文字按钮。 --}}
                    <div class="relative px-6 py-10 md:px-12 md:py-14">
                        <div class="max-w-xl rounded-xl p-6 md:p-8 bg-site-base-92">
                            <h2 class="text-site-primary text-xl md:text-2xl font-bold leading-snug">
                                {{ $banner->title }}
                            </h2>

                            @if($banner->subtitle)
                                <p class="mt-3 text-site-secondary text-sm md:text-base leading-relaxed line-clamp-2">
                                    {{ $banner->subtitle }}
                                </p>
                            @endif

                            @if($banner->hasCallToAction())
                                <div class="mt-6">
                                    @if($banner->cta_action === BannerCtaAction::INQUIRY)
                                        <button
                                            type="button"
                                            data-contact-trigger="banner-strip"
                                            class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-full font-bold text-sm
                                                   focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                                            @click="$store.contactPanel.show('banner-strip')"
                                            aria-controls="contact-panel"
                                            aria-label="{{ $banner->cta_label }}">
                                            {{ $banner->cta_label }}
                                        </button>
                                    @else
                                        <a href="{{ $banner->cta_url }}"
                                           class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-full font-bold text-sm
                                                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none">
                                            {{ $banner->cta_label }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- 圆点。矮横幅里不放左右箭头：横幅本身高度不够，箭头会压到文字面板上。 --}}
            @if($stripTotal > 1)
                {{-- 可视的点 10px、可点的按钮 24px（WCAG 2.5.8）。同 banner-hero。 --}}
                <div class="absolute bottom-2 right-4 z-10 flex">
                    @foreach($stripBanners as $index => $banner)
                        <button type="button"
                                class="w-6 h-6 flex items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                                @click="active = {{ $index }}"
                                aria-label="查看第 {{ $index + 1 }} 张幻灯片">
                            <span class="w-2.5 h-2.5 rounded-full transition-colors"
                                  :class="active === {{ $index }} ? 'bg-(--color-primary)' : 'bg-site-base'"></span>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endif
