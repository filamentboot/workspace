{{--
 * 首页（UI-SPEC §Specific Ideas 首页结构）
 *
 * Hero → 服务亮点 3 列 → 精选案例网格 → 精选方案 → 智能产品展示 → 业主见证 → 联系 CTA
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')

    {{-- 首屏：有启用中的 HOME_TOP 幻灯片就用它，否则降级回单图 hero。
         降级分支不能删——没配幻灯片的下游站首页不能空一块。 --}}
    @php
        $heroBanners = app(\Filamentboot\FilamentbootSite\Modules\Corporate\Banners\BannerProvider::class)
            ->forPosition(\Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition::HOME_TOP);
    @endphp
    @if($heroBanners->isNotEmpty())
        @include('filamentboot-site::components.banner-hero', ['banners' => $heroBanners])
    @else
        @include('filamentboot-site::components.hero')
    @endif

    {{-- 服务亮点（Section 2） --}}
    <section class="py-20 bg-site-base" aria-labelledby="services-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-12">
                <h2 id="services-heading"
                    class="text-site-primary text-3xl font-bold inline-flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                    我们的服务
                </h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @include('filamentboot-site::components.service-card')
            </div>
        </div>
    </section>

    {{-- 精选案例（Section 3） --}}
    <section class="py-20 bg-site-subtle" aria-labelledby="featured-cases-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-12">
                <h2 id="featured-cases-heading"
                    class="text-site-primary text-3xl font-bold flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                    精选案例
                </h2>
                <a href="{{ route('site.cases.index') }}"
                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                    查看全部
                </a>
            </div>

            @if(isset($featuredCases) && $featuredCases->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($featuredCases as $case)
                        @include('filamentboot-site::components.case-card', ['case' => $case])
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无案例，敬请期待</p>
                </div>
            @endif
        </div>
    </section>

    {{-- 精选方案（Section 4） --}}
    @if(isset($featuredSolutions) && $featuredSolutions->isNotEmpty())
        <section class="py-20 bg-site-base" aria-labelledby="featured-solutions-heading">
            <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="flex items-center justify-between mb-12">
                    <h2 id="featured-solutions-heading"
                        class="text-site-primary text-3xl font-bold flex items-center gap-3">
                        <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                        智能方案
                    </h2>
                    <a href="{{ route('site.solutions.index') }}"
                       class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                        查看全部
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($featuredSolutions as $solution)
                        @php $sCover = $solution->coverUrl('card'); @endphp
                        <article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover">
                            <div class="aspect-[4/3] overflow-hidden bg-site-elevated">
                                @if($sCover)
                                    <img src="{{ $sCover }}"
                                         alt="{{ $solution->title_zh }} — 智能方案"
                                         class="w-full h-full object-cover img-blur-up"
                                         loading="lazy"
                                         decoding="async"
                                         width="800"
                                         height="600"
                                         x-on:load="$el.classList.add('loaded')">
                                @else
                                    @include('filamentboot-site::components.image-placeholder', ['label' => '智能方案'])
                                @endif
                            </div>
                            <div class="p-4">
                                <h3 class="text-site-primary font-bold text-base mb-2 line-clamp-2 leading-tight">
                                    <a href="{{ route('site.solutions.show', $solution->slug) }}"
                                       class="hover:text-site-accent transition-colors duration-200">
                                        {{ $solution->title_zh }}
                                    </a>
                                </h3>
                                @if($solution->price_range)
                                    <span class="text-site-accent text-sm">{{ $solution->price_range }}</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 智能产品（Section 5） --}}
    <section class="py-20 bg-site-subtle" aria-labelledby="products-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-12">
                <h2 id="products-heading"
                    class="text-site-primary text-3xl font-bold flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                    智能产品
                </h2>
                <a href="{{ route('site.products.index') }}"
                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                    查看全部
                </a>
            </div>

            @if(isset($featuredProducts) && $featuredProducts->isNotEmpty())
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($featuredProducts as $product)
                        @include('filamentboot-site::components.product-card', ['product' => $product])
                    @endforeach
                </div>

                {{-- 在售品牌行。表达的是业态：本站卖各品牌的智能产品，不是自有品牌。
                     **只出文字，不放第三方 logo** —— 商标授权是另一回事。
                     没有任何产品填了 brand 时整段不渲染。 --}}
                @if(! empty($productBrands))
                    <div class="mt-12 pt-8 border-t border-site">
                        <p class="text-site-secondary text-sm mb-4">在售品牌</p>
                        <ul class="flex flex-wrap gap-x-8 gap-y-3">
                            @foreach($productBrands as $brand)
                                <li class="text-site-primary text-base font-medium">{{ $brand }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无产品展示，敬请期待</p>
                </div>
            @endif
        </div>
    </section>

    {{-- 业主见证（Section 6）
         摆在 CTA 之前：先看到别人怎么说，再决定要不要留联系方式。
         无见证时整段不渲染，不留空标题。 --}}
    @if(isset($testimonials) && $testimonials->isNotEmpty())
        <section class="py-20 bg-site-base" aria-labelledby="testimonials-heading"
                 x-data="{ active: 0, total: {{ $testimonials->count() }} }">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">

                <div class="text-center mb-12">
                    <h2 id="testimonials-heading"
                        class="text-site-primary text-3xl font-bold inline-flex items-center gap-3">
                        <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                        业主说
                    </h2>
                </div>

                @foreach($testimonials as $index => $testimonial)
                    @php $tAvatar = $testimonial->customerAvatarUrl(); @endphp
                    <div x-show="active === {{ $index }}"
                         @if($index !== 0) style="display: none;" @endif
                         class="p-8 rounded-2xl bg-site-surface border border-site text-center">

                        <svg class="w-8 h-8 text-site-accent mx-auto mb-6 opacity-60" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9.983 3v7.391c0 5.704-3.731 9.57-8.983 10.609l-.995-2.151c2.432-.917 3.995-3.638 3.995-5.849h-4v-10h9.983zm14.017 0v7.391c0 5.704-3.748 9.571-9 10.609l-.996-2.151c2.433-.917 3.996-3.638 3.996-5.849h-3.983v-10h9.983z" />
                        </svg>

                        <blockquote class="text-site-primary text-lg leading-relaxed mb-8">{{ $testimonial->customer_quote }}</blockquote>

                        <div class="flex items-center justify-center gap-3">
                            @if($tAvatar)
                                <img src="{{ $tAvatar }}"
                                     alt="{{ $testimonial->customer_name }}"
                                     class="w-10 h-10 rounded-full object-cover"
                                     loading="lazy"
                                     decoding="async"
                                     width="40"
                                     height="40">
                            @else
                                <div class="w-10 h-10 rounded-full bg-site-elevated border border-site flex items-center justify-center"
                                     aria-hidden="true">
                                    <span class="text-site-accent font-bold">{{ mb_substr($testimonial->customer_name, 0, 1) }}</span>
                                </div>
                            @endif
                            <div class="text-left">
                                <p class="text-site-primary text-sm font-medium">{{ $testimonial->customer_name }}</p>
                                @if($testimonial->customer_meta)
                                    <p class="text-site-secondary text-xs">{{ $testimonial->customer_meta }}</p>
                                @endif
                            </div>
                        </div>

                        <a href="{{ route('site.cases.show', $testimonial->slug) }}"
                           class="inline-block mt-6 text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                            查看这个案例 &rarr;
                        </a>
                    </div>
                @endforeach

                {{-- 单条见证时不渲染切换控件 --}}
                @if($testimonials->count() > 1)
                    <div class="flex items-center justify-center gap-4 mt-8">
                        <button type="button"
                                class="w-10 h-10 rounded-full bg-site-elevated border border-site text-site-primary flex items-center justify-center
                                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                                @click="active = (active - 1 + total) % total"
                                aria-label="上一条见证">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </button>

                        {{-- 可视的点 10px、可点的按钮 24px（WCAG 2.5.8）。同 banner-hero。 --}}
                        <div class="flex">
                            @foreach($testimonials as $index => $testimonial)
                                <button type="button"
                                        class="w-6 h-6 flex items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                                        @click="active = {{ $index }}"
                                        aria-label="查看第 {{ $index + 1 }} 条见证">
                                    <span class="w-2.5 h-2.5 rounded-full transition-colors"
                                          :class="active === {{ $index }} ? 'bg-(--color-primary)' : 'bg-site-elevated'"></span>
                                </button>
                            @endforeach
                        </div>

                        <button type="button"
                                class="w-10 h-10 rounded-full bg-site-elevated border border-site text-site-primary flex items-center justify-center
                                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                                @click="active = (active + 1) % total"
                                aria-label="下一条见证">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- 联系 CTA（Section 7，UI-SPEC §Component 7）
         底色由 base 改为 subtle：见证段插在产品段之后占用了 base，
         两段同色相邻会失去分段感。 --}}
    <section class="py-20 bg-site-subtle" aria-labelledby="contact-cta-heading">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 text-center">
            {{-- 文案说的是访客能拿到什么，不是「联系我们」。
                 「免费咨询」对访客不构成理由——他不知道咨询完手上会多出什么东西；
                 「一版配置清单与预算」是个具体的、拿得走的产物。
                 措辞与 software 的同一段对齐（两套主题各存副本，但对外承诺得一致）。 --}}
            <h2 id="contact-cta-heading" class="text-site-primary text-3xl font-bold mb-6">先说户型，再谈方案</h2>
            <p class="text-site-secondary text-lg mb-10">
                说一下户型和现在的装修进度，我们按你家的实际情况出一版配置清单与预算，不收费。
            </p>

            <button
                type="button"
                data-contact-trigger="home-cta"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-10 py-4 rounded-full font-bold text-lg mb-10
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:ring-offset-2 focus-visible:ring-offset-(--color-bg-base) focus-visible:outline-none"
                @click="$store.contactPanel.show('home-cta')"
                aria-controls="contact-panel"
                aria-label="免费获取配置清单与预算">
                免费获取配置清单
            </button>

            {{-- 联系信息行（未配置时不渲染，避免空白区） --}}
            @if($siteSettings?->phone || $siteSettings?->address_zh)
                <div class="flex flex-wrap gap-8 justify-center">
                    @if($siteSettings?->phone)
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-site-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            <a href="tel:{{ preg_replace('/\s+/', '', $siteSettings->phone) }}"
                               class="text-site-secondary text-sm hover:text-site-accent transition-colors duration-200">{{ $siteSettings->phone }}</a>
                        </div>
                    @endif
                    @if($siteSettings?->address_zh)
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-site-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            <span class="text-site-secondary text-sm">{{ $siteSettings->address_zh }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>

@endsection
