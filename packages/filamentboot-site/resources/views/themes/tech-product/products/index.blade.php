{{--
 * 智能产品列表页（tech-product 浅色主题）
 --}}
@extends('filamentboot-site::layouts.app')

@section('content')
    <section class="py-16 bg-site-base" aria-labelledby="products-heading">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-10 max-w-2xl">
                <h1 id="products-heading" class="text-site-primary text-4xl font-bold tracking-tight mb-3">智能产品</h1>
                <p class="text-site-secondary text-base leading-relaxed">
                    经过实际项目验证的设备选型，兼容性与售后都有保障。
                </p>
            </div>

            @if(isset($records) && $records->isNotEmpty())
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($records as $product)
                        @include('filamentboot-site::components.product-card', ['product' => $product])
                    @endforeach
                </div>

                @if($records->hasPages())
                    <div class="mt-12">{{ $records->links() }}</div>
                @endif
            @else
                <div class="py-16 text-center">
                    <p class="text-site-secondary text-base">暂无产品展示，敬请期待</p>
                </div>
            @endif

        </div>
    </section>
@endsection
