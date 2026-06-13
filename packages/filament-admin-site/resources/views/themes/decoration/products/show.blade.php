{{--
 * 智能产品详情页（UI-SPEC §Component 9 风格）
 --}}
@extends('filament-admin-site::layouts.app')

@php
    $isZh  = app()->getLocale() !== 'en';
    $title = $isZh ? ($record->title_zh ?? '') : ($record->title_en ?? $record->title_zh ?? '');
    $desc  = $isZh ? ($record->description_zh ?? '') : ($record->description_en ?? $record->description_zh ?? '');
    $cover = $record->cover_image ?? 'https://picsum.photos/seed/product-' . ($record->slug ?? 'product') . '/800/800';
    $brand = $record->brand ?? '';
    $price = $record->price ?? null;
    $altText = $title;
    $inquireLabel = $isZh ? '咨询价格' : 'Inquire about Pricing';
@endphp

@section('content')
    <div class="max-w-5xl mx-auto py-16 px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">

            {{-- 产品封面图 --}}
            <div class="aspect-square overflow-hidden rounded-2xl bg-site-elevated">
                <img src="{{ $cover }}"
                     alt="{{ $altText }}"
                     class="w-full h-full object-cover"
                     loading="eager"
                     fetchpriority="high"
                     decoding="sync"
                     width="800"
                     height="800">
            </div>

            {{-- 产品信息 --}}
            <div class="flex flex-col justify-start">
                @if($brand)
                    <p class="text-site-muted text-sm uppercase tracking-wide mb-3">{{ $brand }}</p>
                @endif

                <h1 class="text-site-primary text-3xl font-bold mb-6 leading-tight">{{ $title }}</h1>

                @if($price)
                    <p class="text-site-accent font-bold text-2xl mb-6">¥{{ number_format($price, 0) }}</p>
                @else
                    <p class="text-site-muted text-base mb-6">{{ $inquireLabel }}</p>
                @endif

                @if($desc)
                    <p class="text-site-secondary text-base leading-relaxed mb-8">{{ $desc }}</p>
                @endif

                {{-- 询盘 CTA --}}
                <button
                    type="button"
                    class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-4 rounded-full font-bold text-base
                           focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none"
                    onclick="document.getElementById('floating-contact-btn')?.click()"
                    aria-label="{{ $isZh ? '预约咨询' : 'Book a Consultation' }}">
                    {{ $isZh ? '预约咨询' : 'Book a Consultation' }}
                </button>
            </div>
        </div>

        <div class="mt-12">
            <a href="{{ $isZh ? url('/products') : url('/en/products') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; {{ $isZh ? '返回产品列表' : 'Back to Products' }}
            </a>
        </div>
    </div>
@endsection
