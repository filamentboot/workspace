{{--
 * 首页（tech-product 浅色主题）
 *
 * Hero → 服务亮点 → 精选方案 → 精选案例 → 智能产品 → 联系 CTA
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')

    @include('filamentboot-site::components.hero')

    {{-- 服务亮点 --}}
    <section class="py-20 bg-site-surface" aria-labelledby="services-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mb-12">
                <h2 id="services-heading" class="text-site-primary text-3xl font-bold tracking-tight mb-3">
                    我们怎么交付
                </h2>
                <p class="text-site-secondary text-base leading-relaxed">
                    智能家居最大的成本不在设备，而在返工。我们把可控的部分先做成标准。
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @include('filamentboot-site::components.service-card')
            </div>
        </div>
    </section>

    {{-- 精选方案 --}}
    @if(isset($featuredSolutions) && $featuredSolutions->isNotEmpty())
        <section class="py-20 bg-site-base" aria-labelledby="featured-solutions-heading">
            <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between mb-10">
                    <h2 id="featured-solutions-heading" class="text-site-primary text-3xl font-bold tracking-tight">智能方案</h2>
                    <a href="{{ route('site.solutions.index') }}"
                       class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                        查看全部 &rarr;
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($featuredSolutions as $solution)
                        @php $sCover = $solution->coverUrl('card'); @endphp
                        <article class="bg-site-base rounded-xl overflow-hidden border border-site card-hover">
                            <div class="aspect-[4/3] overflow-hidden bg-site-elevated">
                                @if($sCover)
                                    <img src="{{ $sCover }}" alt="{{ $solution->title_zh }} — 智能方案"
                                         class="w-full h-full object-cover img-blur-up"
                                         loading="lazy" decoding="async" width="800" height="600"
                                         x-on:load="$el.classList.add('loaded')">
                                @else
                                    @include('filamentboot-site::components.image-placeholder', ['label' => '智能方案'])
                                @endif
                            </div>
                            <div class="p-4">
                                <h3 class="text-site-primary font-semibold text-base mb-2 line-clamp-2 leading-snug">
                                    <a href="{{ route('site.solutions.show', $solution->slug) }}"
                                       class="hover:text-site-accent transition-colors duration-200">
                                        {{ $solution->title_zh }}
                                    </a>
                                </h3>
                                @if($solution->price_range)
                                    <span class="text-site-accent text-sm font-medium">{{ $solution->price_range }}</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 精选案例 --}}
    <section class="py-20 bg-site-surface" aria-labelledby="featured-cases-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-10">
                <h2 id="featured-cases-heading" class="text-site-primary text-3xl font-bold tracking-tight">精选案例</h2>
                <a href="{{ route('site.cases.index') }}"
                   class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                    查看全部 &rarr;
                </a>
            </div>

            @if(isset($featuredCases) && $featuredCases->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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

    {{-- 智能产品 --}}
    <section class="py-20 bg-site-base" aria-labelledby="products-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-10">
                <h2 id="products-heading" class="text-site-primary text-3xl font-bold tracking-tight">智能产品</h2>
                <a href="{{ route('site.products.index') }}"
                   class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                    查看全部 &rarr;
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

    {{-- 联系 CTA --}}
    <section class="py-20 bg-site-surface border-t border-site" aria-labelledby="contact-cta-heading">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 text-center">
            <h2 id="contact-cta-heading" class="text-site-primary text-3xl font-bold tracking-tight mb-4">
                先聊需求，再谈方案
            </h2>
            <p class="text-site-secondary text-base mb-8">
                留下联系方式，我们按你的户型和预算出一版可执行的清单，不收费。
            </p>

            <button
                type="button"
                data-contact-trigger="home-cta"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-10 py-3 rounded-lg font-bold text-base
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:outline-none"
                @click="$store.contactPanel.show('home-cta')"
                aria-controls="contact-panel"
                aria-label="立即预约咨询">
                立即预约咨询
            </button>

            @if($siteSettings?->phone)
                <p class="text-site-secondary text-sm mt-6">
                    或直接致电
                    <a href="tel:{{ preg_replace('/\s+/', '', $siteSettings->phone) }}"
                       class="text-site-accent font-medium hover:underline">{{ $siteSettings->phone }}</a>
                </p>
            @endif
        </div>
    </section>

@endsection
