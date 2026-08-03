{{--
 * 智能产品详情页（UI-SPEC §Component 9 风格）
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title = $record->title_zh ?? '';
    $desc  = $record->description_zh ?? '';
    $cover = $record->coverUrl('card');
    $brand = $record->brand ?? '';
    $price = $record->price ?? null;
@endphp

@section('content')
    <div class="max-w-5xl mx-auto py-16 px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">

            {{-- 产品封面图 --}}
            <div class="aspect-square overflow-hidden rounded-2xl bg-site-elevated">
                @if($cover)
                    <img src="{{ $cover }}"
                         alt="{{ $title }} — 产品图"
                         class="w-full h-full object-cover"
                         loading="eager"
                         fetchpriority="high"
                         decoding="sync"
                         width="800"
                         height="800">
                @else
                    @include('filamentboot-site::components.image-placeholder', ['label' => '智能产品'])
                @endif
            </div>

            {{-- 产品信息 --}}
            <div class="flex flex-col justify-start">
                @if($brand)
                    <p class="text-site-secondary text-sm uppercase tracking-wide mb-3">{{ $brand }}</p>
                @endif

                <h1 class="text-site-primary text-3xl font-bold mb-6 leading-tight">{{ $title }}</h1>

                @if($price)
                    <p class="text-site-accent font-bold text-2xl mb-6">¥{{ number_format((float) $price, 0) }}</p>
                @else
                    <p class="text-site-secondary text-base mb-6">价格以实际配置为准，欢迎咨询</p>
                @endif

                @if($desc)
                    <p class="text-site-secondary text-base leading-relaxed mb-8">{{ $desc }}</p>
                @endif

                {{-- 询盘 CTA（与导航、悬浮按钮共用同一面板） --}}
                <button
                    type="button"
                    data-contact-trigger="product-detail"
                    class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                           focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
                    @click="$store.contactPanel.show('product-detail')"
                    aria-controls="contact-panel"
                    aria-label="预约咨询">
                    预约咨询
                </button>
            </div>
        </div>

        <div class="mt-12">
            <a href="{{ route('site.products.index') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; 返回产品列表
            </a>
        </div>
    </div>
@endsection
