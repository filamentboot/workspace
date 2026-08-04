{{--
 * 智能方案详情页（UI-SPEC §Component 9）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title   = $record->title_zh ?? '';
    $cover   = $record->coverUrl('og');
    $content = $record->content_zh ?: ($record->description_zh ?? '');
@endphp

@section('content')

    {{-- Hero 图（无封面时不渲染整块，避免大片空白占位） --}}
    @if($cover)
        <div class="w-full relative overflow-hidden" style="max-height: 480px;">
            <img src="{{ $cover }}"
                 alt="{{ $title }} — 智能方案"
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
    @endif

    {{-- 正文区 --}}
    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6">

        @include('filamentboot-site::components.breadcrumb')

        <h1 class="text-site-primary text-3xl font-bold mb-6 leading-tight">{{ $title }}</h1>

        @if($record->price_range)
            <div class="flex flex-wrap gap-2 py-4 border-b border-site mb-10">
                <span class="bg-site-elevated text-site-secondary text-xs px-3 py-1 rounded-full">{{ $record->price_range }}</span>
            </div>
        @endif

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
            </div>
        @endif

        {{-- 针对本方案的咨询 CTA --}}
        <div class="mt-12 p-6 rounded-2xl bg-site-surface border border-site flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-site-primary font-bold text-base mb-1">对这套方案感兴趣？</p>
                <p class="text-site-secondary text-sm">留下联系方式，我们会安排顾问与您沟通具体落地细节。</p>
            </div>
            <button
                type="button"
                data-contact-trigger="solution-detail"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-full text-sm whitespace-nowrap
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                @click="$store.contactPanel.show('solution-detail')"
                aria-controls="contact-panel">
                预约咨询
            </button>
        </div>

        {{-- 相关方案（同标签优先，不足由最新补齐）
             方案没有卡片组件（列表页也是内联的），这里照本主题列表页的卡片版式压缩一版 --}}
        @if($related->isNotEmpty())
            <section class="mt-16 pt-12 border-t border-site" aria-labelledby="related-solutions-heading">
                <h2 id="related-solutions-heading" class="text-site-primary text-xl font-bold mb-6">相关方案</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($related as $relatedSolution)
                        @php
                            $rTitle = $relatedSolution->title_zh ?? '';
                            $rCover = $relatedSolution->coverUrl('card');
                        @endphp
                        <article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover">
                            <a href="{{ route('site.solutions.show', $relatedSolution->slug) }}"
                               class="block focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-2xl"
                               aria-label="查看方案：{{ $rTitle }}">
                                <div class="aspect-[4/3] overflow-hidden bg-site-elevated">
                                    @if($rCover)
                                        <img src="{{ $rCover }}" alt="{{ $rTitle }} — 智能方案"
                                             class="w-full h-full object-cover img-blur-up"
                                             loading="lazy" decoding="async" width="800" height="600"
                                             x-on:load="$el.classList.add('loaded')">
                                    @else
                                        @include('filamentboot-site::components.image-placeholder', ['label' => '智能方案'])
                                    @endif
                                </div>
                                <div class="p-5">
                                    <h3 class="text-site-primary font-bold text-base leading-snug">{{ $rTitle }}</h3>
                                    @if($relatedSolution->price_range)
                                        <p class="text-site-secondary text-xs mt-2">{{ $relatedSolution->price_range }}</p>
                                    @endif
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-8">
            <a href="{{ route('site.solutions.index') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; 返回方案列表
            </a>
        </div>
    </div>

@endsection
