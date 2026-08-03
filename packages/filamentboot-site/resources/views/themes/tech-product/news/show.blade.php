{{--
 * 资讯详情页（tech-product 浅色主题）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 *
 * 与 decoration 主题各自持有一份完整视图，刻意不抽公共层：
 * 宿主装机后常会只留一套主题并删掉另一套目录，共享层会让删除留下断链。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title       = $record->title_zh ?? '';
    $excerpt     = $record->excerpt_zh ?? '';
    $cover       = $record->coverUrl('og');
    $content     = $record->content_zh ?? '';
    $category    = $record->category;
    $publishedAt = $record->published_at;
@endphp

@section('content')
    <article class="max-w-3xl mx-auto py-12 px-4 sm:px-6">

        <div class="flex flex-wrap items-center gap-3 mb-4 text-sm">
            @if($category)
                <a href="{{ route('site.news.index', ['category' => $category->slug]) }}"
                   class="bg-site-surface border border-site text-site-secondary px-3 py-1 rounded-md text-xs hover:text-site-accent
                          focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none">
                    {{ $category->name_zh }}
                </a>
            @endif
            @if($publishedAt)
                <time datetime="{{ $publishedAt->toDateString() }}" class="text-site-muted">
                    {{ $publishedAt->format('Y 年 n 月 j 日') }}
                </time>
            @endif
        </div>

        <h1 class="text-site-primary text-3xl md:text-4xl font-bold tracking-tight mb-6 leading-tight">{{ $title }}</h1>

        @if($excerpt)
            <p class="text-site-secondary text-lg leading-relaxed mb-8">{{ $excerpt }}</p>
        @endif

        @if($cover)
            <div class="rounded-xl overflow-hidden border border-site mb-10">
                <img src="{{ $cover }}"
                     alt="{{ $title }} — 资讯配图"
                     class="w-full object-cover"
                     loading="eager" fetchpriority="high" decoding="sync"
                     style="aspect-ratio: 16/9;">
            </div>
        @endif

        {{-- 正文（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
            </div>
        @endif

        @if($record->tags->isNotEmpty())
            <div class="flex flex-wrap gap-2 mt-10 pt-8 border-t border-site">
                @foreach($record->tags as $tag)
                    <span class="bg-site-surface border border-site text-site-secondary text-xs px-3 py-1 rounded-md">
                        {{ $tag->name_zh }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- 咨询 CTA --}}
        <div class="mt-12 p-6 rounded-xl bg-site-surface border border-site flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-site-primary font-semibold text-base mb-1">有具体的户型要问？</p>
                <p class="text-site-secondary text-sm">留下联系方式，我们按你的实际情况给一版可执行的建议。</p>
            </div>
            <button
                type="button"
                data-contact-trigger="news-detail"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-lg text-sm whitespace-nowrap
                       focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
                @click="$store.contactPanel.show('news-detail')"
                aria-controls="contact-panel">
                预约咨询
            </button>
        </div>

        {{-- 相关阅读 --}}
        @if($related->isNotEmpty())
            <section class="mt-16 pt-12 border-t border-site" aria-labelledby="related-news-heading">
                <h2 id="related-news-heading" class="text-site-primary text-xl font-bold tracking-tight mb-6">相关阅读</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($related as $article)
                        @include('filamentboot-site::components.news-card', ['article' => $article])
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-12">
            <a href="{{ route('site.news.index') }}"
               class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                &larr; 返回资讯列表
            </a>
        </div>
    </article>
@endsection
