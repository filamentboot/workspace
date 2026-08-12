{{--
 * 智能产品列表页
 --}}
@extends('filamentboot-site::layouts.app')

@php
    // 0 件已发布产品的分类不出筛选按钮，与资讯分类同一套判据（articles_count）
    $pills = $categories->filter(fn ($category): bool => (int) ($category->products_count ?? 0) > 0);
@endphp

@section('content')
    {{-- 投放位幻灯片。没有生效中的就整段不渲染，所以这里无条件 include。 --}}
    @include('filamentboot-site::components.banner-strip', [
        'position' => \Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition::PRODUCT_INDEX_TOP,
    ])

    <section class="py-16 bg-site-base" aria-labelledby="products-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10">
                <h1 id="products-heading" class="text-site-primary text-4xl font-bold mb-4">
                    {{ $activeCategory?->name_zh ?? '智能产品' }}
                </h1>
                @if($siteSettings?->list_intro_products_zh)
                    <p class="text-site-secondary text-lg">
                        {{ $siteSettings->list_intro_products_zh }}
                    </p>
                @endif
            </div>

            {{-- 分类筛选，与资讯列表页同一套版式 --}}
            @if($pills->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-10" role="navigation" aria-label="产品分类筛选">
                    <a href="{{ route('site.products.index') }}"
                       class="inline-flex items-center min-h-[36px] px-4 rounded-full text-sm transition-colors duration-200
                              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none
                              {{ $activeCategory === null
                                  ? 'bg-(--color-primary) text-(--color-bg-base)'
                                  : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}"
                       @if($activeCategory === null) aria-current="page" @endif>
                        全部
                    </a>

                    @foreach($pills as $category)
                        <a href="{{ route('site.products.index', ['category' => $category->slug]) }}"
                           class="inline-flex items-center min-h-[36px] px-4 rounded-full text-sm transition-colors duration-200
                                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none
                                  {{ $activeCategory?->id === $category->id
                                      ? 'bg-(--color-primary) text-(--color-bg-base)'
                                      : 'bg-site-surface border border-site text-site-secondary hover:text-site-accent' }}"
                           @if($activeCategory?->id === $category->id) aria-current="page" @endif>
                            {{ $category->name_zh }}
                            <span class="ml-1 opacity-60">{{ $category->products_count }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if(isset($records) && $records->isNotEmpty())
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($records as $product)
                        @include('filamentboot-site::components.product-card', ['product' => $product])
                    @endforeach
                </div>

                {{-- 分页 --}}
                @if($records->hasPages())
                    <div class="mt-12">
                        {{ $records->links() }}
                    </div>
                @endif

            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无产品展示，敬请期待</p>
                </div>
            @endif

        </div>
    </section>
@endsection
