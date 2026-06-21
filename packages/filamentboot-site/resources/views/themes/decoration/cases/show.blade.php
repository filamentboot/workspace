{{--
 * 案例详情页（UI-SPEC §Component 9）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 * 禁止裸 {!! $record->content_zh !!}。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $isZh    = app()->getLocale() !== 'en';
    $title   = $isZh ? ($record->title_zh ?? '') : ($record->title_en ?? $record->title_zh ?? '');
    $cover   = $record->cover_image ?? 'https://picsum.photos/seed/' . ($record->slug ?? 'case') . '/1200/675';
    $content = $isZh ? ($record->content_zh ?? $record->description_zh ?? '') : ($record->content_en ?? $record->content_zh ?? $record->description_zh ?? '');
    $style   = $record->style?->label() ?? ($record->style ? (string) $record->style : '');
    $houseType = $record->house_type?->label() ?? ($record->house_type ? (string) $record->house_type : '');
    $area    = isset($record->area) && $record->area ? $record->area . '㎡' : '';
    $budget  = $record->budget_range ?? '';
    $publishedAt = $record->published_at ? $record->published_at->format('Y-m-d') : '';
    $altText = $title . ($isZh ? ' — 装修案例' : ' — Case');
@endphp

@section('content')

    {{-- Hero 图（loading="eager"，首屏 LCP，UI-SPEC §Image Lazy Loading） --}}
    <div class="w-full relative overflow-hidden" style="max-height: 480px;">
        <img src="{{ $cover }}"
             alt="{{ $altText }}"
             class="w-full object-cover"
             loading="eager"
             fetchpriority="high"
             decoding="sync"
             style="aspect-ratio: 16/9; max-height: 480px;">
        {{-- 渐变遮罩 --}}
        <div class="absolute bottom-0 left-0 right-0 h-32"
             style="background: linear-gradient(to top, var(--color-bg-base), transparent);"
             aria-hidden="true">
        </div>
    </div>

    {{-- 正文区 --}}
    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6">

        {{-- 标题 --}}
        <h1 class="text-site-primary text-3xl font-bold mb-6 leading-tight">{{ $title }}</h1>

        {{-- Meta 行 --}}
        <div class="flex flex-wrap gap-2 py-4 border-b border-site mb-10">
            @if($style)
                <span class="bg-site-elevated text-site-muted text-xs px-3 py-1 rounded-full">{{ $style }}</span>
            @endif
            @if($houseType)
                <span class="bg-site-elevated text-site-muted text-xs px-3 py-1 rounded-full">{{ $houseType }}</span>
            @endif
            @if($area)
                <span class="bg-site-elevated text-site-muted text-xs px-3 py-1 rounded-full">{{ $area }}</span>
            @endif
            @if($budget)
                <span class="text-site-accent text-xs px-3 py-1">{{ $budget }}</span>
            @endif
            @if($publishedAt)
                <span class="flex items-center gap-1 text-site-muted text-xs">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                    </svg>
                    {{ $publishedAt }}
                </span>
            @endif
        </div>

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-all;">
                {!! app('purifier')->clean($content) !!}
            </div>
        @endif

        {{-- 返回链接 --}}
        <div class="mt-12">
            <a href="{{ $isZh ? url('/cases') : url('/en/cases') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; {{ $isZh ? '返回案例列表' : 'Back to Cases' }}
            </a>
        </div>
    </div>

@endsection
