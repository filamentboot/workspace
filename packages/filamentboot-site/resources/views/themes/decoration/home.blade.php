{{--
 * 首页（UI-SPEC §Specific Ideas 首页结构）
 *
 * Hero → 服务亮点 3 列 → 精选案例网格 → 精选方案 → 智能产品展示 → 联系 CTA
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')

    {{-- Hero 组件 --}}
    @include('filamentboot-site::components.hero')

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
                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
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
                       class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
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
                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                    查看全部
                </a>
            </div>

            @if(isset($featuredProducts) && $featuredProducts->isNotEmpty())
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($featuredProducts as $product)
                        @include('filamentboot-site::components.product-card', ['product' => $product])
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无产品展示，敬请期待</p>
                </div>
            @endif
        </div>
    </section>

    {{-- 联系 CTA（Section 6，UI-SPEC §Component 7） --}}
    <section class="py-20 bg-site-base" aria-labelledby="contact-cta-heading">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 text-center">
            <h2 id="contact-cta-heading" class="text-site-primary text-3xl font-bold mb-6">联系我们</h2>
            <p class="text-site-secondary text-lg mb-10">
                想了解更多方案？欢迎随时联系我们，专业团队为您提供免费咨询。
            </p>

            <button
                type="button"
                data-contact-trigger="home-cta"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-10 py-4 rounded-full font-bold text-lg mb-10
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
                @click="$store.contactPanel.show('home-cta')"
                aria-controls="contact-panel"
                aria-label="立即预约咨询">
                立即预约咨询
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
