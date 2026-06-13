{{--
 * 首页（UI-SPEC §Specific Ideas 首页结构）
 *
 * Hero → 服务亮点 3 列 → 精选案例网格 → 智能产品展示 → 联系 CTA → 页脚（含 app 布局）
 --}}
@extends('filament-admin-site::layouts.app')

@php
    $isZh = app()->getLocale() !== 'en';
@endphp

@section('content')

    {{-- Hero 组件 --}}
    @include('filament-admin-site::components.hero')

    {{-- 服务亮点（Section 2） --}}
    <section class="py-20 bg-site-base" aria-labelledby="services-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-12">
                <h2 id="services-heading"
                    class="text-site-primary text-3xl font-bold inline-flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                    {{ $isZh ? '我们的服务' : 'Our Services' }}
                </h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @include('filament-admin-site::components.service-card')
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
                    {{ $isZh ? '精选案例' : 'Featured Cases' }}
                </h2>
                <a href="{{ $isZh ? url('/cases') : url('/en/cases') }}"
                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                    {{ $isZh ? '查看全部' : 'View All' }}
                </a>
            </div>

            @if(isset($featuredCases) && $featuredCases->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($featuredCases as $case)
                        @include('filament-admin-site::components.case-card', ['case' => $case])
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center">
                    <p class="text-site-muted text-base">
                        {{ $isZh ? '暂无案例，敬请期待' : 'No cases yet — check back soon' }}
                    </p>
                </div>
            @endif
        </div>
    </section>

    {{-- 智能产品（Section 4） --}}
    <section class="py-20 bg-site-base" aria-labelledby="products-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-12">
                <h2 id="products-heading"
                    class="text-site-primary text-3xl font-bold flex items-center gap-3">
                    <span class="inline-block w-1 h-8 rounded-full" style="background: var(--color-primary);"></span>
                    {{ $isZh ? '智能产品' : 'Smart Products' }}
                </h2>
                <a href="{{ $isZh ? url('/products') : url('/en/products') }}"
                   class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                    {{ $isZh ? '查看全部' : 'View All' }}
                </a>
            </div>

            @if(isset($featuredProducts) && $featuredProducts->isNotEmpty())
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($featuredProducts as $product)
                        @include('filament-admin-site::components.product-card', ['product' => $product])
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center">
                    <p class="text-site-muted text-base">
                        {{ $isZh ? '暂无产品展示，敬请期待' : 'No products listed yet' }}
                    </p>
                </div>
            @endif
        </div>
    </section>

    {{-- 联系 CTA（Section 5，UI-SPEC §Component 7） --}}
    <section class="py-20 bg-site-subtle" aria-labelledby="contact-cta-heading">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 text-center">
            <h2 id="contact-cta-heading" class="text-site-primary text-3xl font-bold mb-6">
                {{ $isZh ? '联系我们' : 'Contact Us' }}
            </h2>
            <p class="text-site-secondary text-lg mb-10">
                {{ $isZh ? '想了解更多方案？欢迎随时联系我们，专业团队为您提供免费咨询。' : 'Want to learn more? Contact us for a free consultation with our professional team.' }}
            </p>

            <button
                type="button"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-10 py-4 rounded-full font-bold text-lg mb-10
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-subtle] focus-visible:outline-none"
                onclick="document.querySelector('[x-data]')?._x_dataStack?.[0]?.contactPanelOpen ? null : document.getElementById('floating-contact-btn')?.click()"
                aria-label="{{ $isZh ? '立即预约咨询' : 'Book a Consultation' }}">
                {{ $isZh ? '立即预约咨询' : 'Book a Consultation' }}
            </button>

            {{-- 联系信息行 --}}
            @if(isset($settings))
                <div class="flex flex-wrap gap-8 justify-center">
                    @if(optional($settings)->phone)
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-site-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            <span class="text-site-secondary text-sm">{{ $settings->phone }}</span>
                        </div>
                    @endif
                    @if(optional($settings)->address_zh && $isZh)
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-site-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            <span class="text-site-secondary text-sm">{{ $settings->address_zh }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>

@endsection
