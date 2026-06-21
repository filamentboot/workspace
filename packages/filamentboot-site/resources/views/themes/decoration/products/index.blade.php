{{--
 * 智能产品列表页
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $isZh = app()->getLocale() !== 'en';
@endphp

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="products-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-12">
                <h1 id="products-heading"
                    class="text-site-primary text-4xl font-bold mb-4">
                    {{ $isZh ? '智能产品' : 'Smart Products' }}
                </h1>
                <p class="text-site-secondary text-lg">
                    {{ $isZh ? '精选顶级智能家居产品，为您的智慧生活赋能。' : 'Curated premium smart home products to empower your intelligent living.' }}
                </p>
            </div>

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
                    <p class="text-site-muted text-base">
                        {{ $isZh ? '暂无产品展示，敬请期待' : 'No products listed yet' }}
                    </p>
                </div>
            @endif

        </div>
    </section>
@endsection
