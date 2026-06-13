{{--
 * 智能方案详情页（UI-SPEC §Component 9）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 --}}
@extends('filament-admin-site::layouts.app')

@php
    $isZh    = app()->getLocale() !== 'en';
    $title   = $isZh ? ($record->title_zh ?? '') : ($record->title_en ?? $record->title_zh ?? '');
    $cover   = $record->cover_image ?? 'https://picsum.photos/seed/solution-' . ($record->slug ?? 'solution') . '/1200/675';
    $content = $isZh ? ($record->content_zh ?? $record->description_zh ?? '') : ($record->content_en ?? $record->content_zh ?? $record->description_zh ?? '');
    $altText = $title . ($isZh ? ' — 智能方案' : ' — Solution');
@endphp

@section('content')

    {{-- Hero 图 --}}
    <div class="w-full relative overflow-hidden" style="max-height: 480px;">
        <img src="{{ $cover }}"
             alt="{{ $altText }}"
             class="w-full object-cover"
             loading="eager"
             fetchpriority="high"
             decoding="sync"
             style="aspect-ratio: 16/9; max-height: 480px;">
        <div class="absolute bottom-0 left-0 right-0 h-32"
             style="background: linear-gradient(to top, var(--color-bg-base), transparent);"
             aria-hidden="true">
        </div>
    </div>

    {{-- 正文区 --}}
    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6">

        <h1 class="text-site-primary text-3xl font-bold mb-6 leading-tight">{{ $title }}</h1>

        <div class="flex flex-wrap gap-2 py-4 border-b border-site mb-10">
            @if($record->price_range)
                <span class="bg-site-elevated text-site-muted text-xs px-3 py-1 rounded-full">{{ $record->price_range }}</span>
            @endif
        </div>

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-all;">
                {!! app('purifier')->clean($content) !!}
            </div>
        @endif

        <div class="mt-12">
            <a href="{{ $isZh ? url('/solutions') : url('/en/solutions') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; {{ $isZh ? '返回方案列表' : 'Back to Solutions' }}
            </a>
        </div>
    </div>

@endsection
