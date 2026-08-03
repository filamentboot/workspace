{{--
 * 案例详情页（tech-product 浅色主题）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title   = $record->title_zh ?? '';
    $cover   = $record->coverUrl('og');
    $gallery = $record->galleryUrls('card');
    $content = $record->content_zh ?: ($record->description_zh ?? '');
    $meta    = array_filter([
        '风格' => $record->style?->label() ?? '',
        '户型' => $record->house_type?->label() ?? '',
        '面积' => ($record->area ?? '') !== '' ? $record->area . '㎡' : '',
        '预算' => $record->budget_range ?? '',
    ]);
    $customerAvatar = $record->customerAvatarUrl();
@endphp

@section('content')
    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6">

        <h1 class="text-site-primary text-3xl md:text-4xl font-bold tracking-tight mb-6 leading-tight">{{ $title }}</h1>

        {{-- 参数表 --}}
        @if($meta !== [])
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-5 rounded-xl bg-site-surface border border-site mb-10">
                @foreach($meta as $label => $value)
                    <div>
                        <dt class="text-site-secondary text-xs mb-1">{{ $label }}</dt>
                        <dd class="text-site-primary text-sm font-semibold">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        {{-- 封面 --}}
        @if($cover)
            <div class="rounded-xl overflow-hidden border border-site mb-10">
                <img src="{{ $cover }}"
                     alt="{{ $title }} — 装修案例"
                     class="w-full object-cover"
                     loading="eager" fetchpriority="high" decoding="sync"
                     style="aspect-ratio: 16/9;">
            </div>
        @endif

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
            </div>
        @endif

        {{-- 图集 --}}
        @if($gallery !== [])
            <div class="mt-10">
                <h2 class="text-site-primary text-xl font-bold mb-4">实景图集</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($gallery as $index => $imageUrl)
                        <div class="aspect-[4/3] overflow-hidden rounded-xl border border-site bg-site-elevated">
                            <img src="{{ $imageUrl }}"
                                 alt="{{ $title }} — 实景图 {{ $index + 1 }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy" decoding="async">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 业主见证（姓名与引言缺一不可，避免只有头像的空壳卡片） --}}
        @if($record->hasCustomerTestimonial())
            <div class="mt-10 p-6 rounded-xl bg-site-elevated border border-site">
                <div class="flex items-start gap-4">
                    @if($customerAvatar)
                        <img src="{{ $customerAvatar }}"
                             alt="{{ $record->customer_name }}"
                             class="shrink-0 w-12 h-12 rounded-full object-cover"
                             loading="lazy" decoding="async"
                             width="48" height="48">
                    @else
                        {{-- 无头像时用称呼首字生成占位圆标 --}}
                        <div class="shrink-0 w-12 h-12 rounded-full bg-site-surface border border-site flex items-center justify-center"
                             aria-hidden="true">
                            <span class="text-site-accent font-bold text-lg">{{ mb_substr($record->customer_name, 0, 1) }}</span>
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <svg class="w-6 h-6 text-site-accent mb-2 opacity-50" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9.983 3v7.391c0 5.704-3.731 9.57-8.983 10.609l-.995-2.151c2.432-.917 3.995-3.638 3.995-5.849h-4v-10h9.983zm14.017 0v7.391c0 5.704-3.748 9.571-9 10.609l-.996-2.151c2.433-.917 3.996-3.638 3.996-5.849h-3.983v-10h9.983z" />
                        </svg>
                        <blockquote class="text-site-primary text-base leading-relaxed mb-3">{{ $record->customer_quote }}</blockquote>
                        <p class="text-site-secondary text-sm">
                            <span class="text-site-primary font-semibold">{{ $record->customer_name }}</span>
                            @if($record->customer_meta)
                                <span class="mx-1">·</span>{{ $record->customer_meta }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- 咨询 CTA --}}
        <div class="mt-12 p-6 rounded-xl bg-site-surface border border-site flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-site-primary font-semibold text-base mb-1">想要同款效果？</p>
                <p class="text-site-secondary text-sm">留下联系方式，我们按你的户型与预算出一版方案。</p>
            </div>
            <button
                type="button"
                data-contact-trigger="case-detail"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-lg text-sm whitespace-nowrap
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                @click="$store.contactPanel.show('case-detail')"
                aria-controls="contact-panel">
                预约咨询
            </button>
        </div>

        <div class="mt-8">
            <a href="{{ route('site.cases.index') }}"
               class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; 返回案例列表
            </a>
        </div>
    </div>
@endsection
