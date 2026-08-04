{{--
 * 智能产品详情页（tech-product 浅色主题）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 * 图集轮播用 Alpine，不引额外 JS 依赖。
 *
 * 与 decoration 主题各自持有一份完整视图，刻意不抽公共层：
 * 宿主装机后常会只留一套主题并删掉另一套目录，共享层会让删除留下断链。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title   = $record->title_zh ?? '';
    $desc    = $record->description_zh ?? '';
    $cover   = $record->coverUrl('card');
    $brand   = $record->brand ?? '';
    $price   = $record->price ?? null;
    $content = $record->content_zh ?? '';

    // 图集优先，无图集时退回封面单图，两者皆空时走占位组件
    $gallery = $record->galleryUrls('card');
    $slides  = $gallery !== [] ? $gallery : array_values(array_filter([$cover]));
@endphp

@section('content')
    <div class="max-w-5xl mx-auto py-16 px-4 sm:px-6 lg:px-8">

        @include('filamentboot-site::components.breadcrumb')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">

            @if($slides !== [])
                <div x-data="{ active: 0, total: {{ count($slides) }} }" class="flex flex-col gap-3">

                    <div class="relative aspect-square overflow-hidden rounded-xl border border-site bg-site-elevated">
                        @foreach($slides as $index => $imageUrl)
                            <img src="{{ $imageUrl }}"
                                 alt="{{ $title }} — 产品图 {{ $index + 1 }}"
                                 class="absolute inset-0 w-full h-full object-cover"
                                 x-show="active === {{ $index }}"
                                 @if($index === 0)
                                     loading="eager" fetchpriority="high" decoding="sync"
                                 @else
                                     loading="lazy" decoding="async" style="display: none;"
                                 @endif
                                 width="800" height="800">
                        @endforeach

                        @if(count($slides) > 1)
                            <button type="button"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-lg bg-site-surface border border-site
                                           text-site-primary flex items-center justify-center shadow-sm
                                           focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                                    @click="active = (active - 1 + total) % total"
                                    aria-label="上一张">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            <button type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-lg bg-site-surface border border-site
                                           text-site-primary flex items-center justify-center shadow-sm
                                           focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                                    @click="active = (active + 1) % total"
                                    aria-label="下一张">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    @if(count($slides) > 1)
                        <div class="flex gap-2 overflow-x-auto pb-1">
                            @foreach($slides as $index => $imageUrl)
                                <button type="button"
                                        class="shrink-0 w-16 h-16 overflow-hidden rounded-lg bg-site-elevated border-2 transition-colors
                                               focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                                        :class="active === {{ $index }} ? 'border-[--color-primary]' : 'border-site'"
                                        @click="active = {{ $index }}"
                                        aria-label="查看第 {{ $index + 1 }} 张产品图">
                                    <img src="{{ $imageUrl }}"
                                         alt=""
                                         class="w-full h-full object-cover"
                                         loading="lazy" decoding="async"
                                         width="64" height="64">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="aspect-square overflow-hidden rounded-xl border border-site bg-site-elevated">
                    @include('filamentboot-site::components.image-placeholder', ['label' => '智能产品'])
                </div>
            @endif

            <div class="flex flex-col justify-start">
                @if($brand)
                    <p class="text-site-secondary text-sm uppercase tracking-wide mb-2">{{ $brand }}</p>
                @endif

                <h1 class="text-site-primary text-3xl font-bold tracking-tight mb-4 leading-tight">{{ $title }}</h1>

                @if($price)
                    <p class="text-site-primary font-bold text-2xl mb-6">¥{{ number_format((float) $price, 0) }}</p>
                @else
                    <p class="text-site-secondary text-base mb-6">价格以实际配置为准，欢迎咨询</p>
                @endif

                @if($desc)
                    <p class="text-site-secondary text-base leading-relaxed mb-8">{{ $desc }}</p>
                @endif

                <button
                    type="button"
                    data-contact-trigger="product-detail"
                    class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-8 py-3 rounded-lg font-bold text-base self-start
                           focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:outline-none"
                    @click="$store.contactPanel.show('product-detail')"
                    aria-controls="contact-panel"
                    aria-label="预约咨询">
                    预约咨询
                </button>
            </div>
        </div>

        {{-- 详情正文（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="mt-16 pt-12 border-t border-site">
                <h2 class="text-site-primary text-xl font-bold tracking-tight mb-6">产品详情</h2>
                <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                    {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
                </div>
            </div>
        @endif

        <div class="mt-12">
            <a href="{{ route('site.products.index') }}"
               class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; 返回产品列表
            </a>
        </div>
    </div>
@endsection
