{{--
 * 资讯详情页
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 * 禁止裸 {!! $record->content_zh !!}。
 *
 * 七期批次 1（2026-08-11）起，这份视图两套主题共用，落在 resources/views/shared/：
 * 与「把文件放进对方主题目录」不同，shared/ 是两套主题的平级共用目录，
 * 宿主删掉某一套主题的 themes/{theme}/ 目录不会牵连这份文件，不会留下断链。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title       = $record->title_zh ?? '';
    $excerpt     = $record->description_zh ?? '';
    $cover       = $record->coverUrl('og');
    $content     = $record->content_zh ?? '';
    $category    = $record->category;
    $publishedAt = $record->published_at;

    /*
     * EEAT：署名与更新时间要**可见**，不能只活在 JSON-LD 里。
     * `author` 与 `dateModified` 早就写进 Article 节点了，但页面上看不到——
     * 对读者等于没有，而百度 2026 起的 EEAT 评级看的是页面本身。
     *
     * 署名取站点主体名（站点设置里的公司名），不引入「作者」这个概念：
     * 这套内容确实是公司出的，凭空造一个作者名反而是假的。
     * 更新时间只在**跨天**晚于发布时间时才显示——同一天的 updated_at
     * 只是种子或后台顺手保存了一次，把它当「更新过」是虚假的新鲜度信号。
     */
    $byline    = ($siteSettings?->company_name_zh ?: '') ?: '';
    $updatedAt = $record->updated_at;
    $showUpdated = $publishedAt && $updatedAt
        && $updatedAt->toDateString() > $publishedAt->toDateString();
@endphp

@section('content')
    <article class="max-w-3xl mx-auto py-12 px-4 sm:px-6">

        @include('filamentboot-site::components.breadcrumb')

        {{-- 分类 + 发布日期 --}}
        <div class="flex flex-wrap items-center gap-3 mb-4 text-sm">
            @if($category)
                <a href="{{ route('site.news.index', ['category' => $category->slug]) }}"
                   class="bg-site-elevated text-site-accent px-3 py-1 rounded-full text-xs hover:underline
                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none">
                    {{ $category->name_zh }}
                </a>
            @endif
            @if($byline !== '')
                <span class="text-site-muted">{{ $byline }}</span>
            @endif
            @if($publishedAt)
                <time datetime="{{ $publishedAt->toDateString() }}" class="text-site-muted">
                    {{ $publishedAt->format('Y 年 n 月 j 日') }}
                </time>
            @endif
            @if($showUpdated)
                <time datetime="{{ $updatedAt->toDateString() }}" class="text-site-muted">
                    更新于 {{ $updatedAt->format('Y 年 n 月 j 日') }}
                </time>
            @endif
        </div>

        <h1 class="text-site-primary text-3xl md:text-4xl font-bold mb-6 leading-tight">{{ $title }}</h1>

        @if($excerpt)
            <p class="text-site-secondary text-lg leading-relaxed mb-8">{{ $excerpt }}</p>
        @endif

        {{-- 封面 --}}
        @if($cover)
            <div class="rounded-2xl overflow-hidden mb-10">
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

        {{-- 标签 --}}
        @include('filamentboot-site::components.tag-list', ['tags' => $record->tags])

        {{-- 咨询 CTA --}}
        <div class="mt-12 p-6 rounded-2xl bg-site-surface border border-site flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-site-primary font-bold text-base mb-1">有具体的户型要问？</p>
                <p class="text-site-secondary text-sm">留下联系方式，我们按你的实际情况给一版可执行的建议。</p>
            </div>
            <button
                type="button"
                data-contact-trigger="news-detail"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-full text-sm whitespace-nowrap
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                @click="$store.contactPanel.show('news-detail')"
                aria-controls="contact-panel">
                预约咨询
            </button>
        </div>

        {{-- 相关阅读 --}}
        @if($related->isNotEmpty())
            <section class="mt-16 pt-12 border-t border-site" aria-labelledby="related-news-heading">
                <h2 id="related-news-heading" class="text-site-primary text-xl font-bold mb-6">相关阅读</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($related as $article)
                        @include('filamentboot-site::components.news-card', ['article' => $article])
                    @endforeach
                </div>
            </section>
        @endif

        <div class="mt-12">
            <a href="{{ route('site.news.index') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                &larr; 返回资讯列表
            </a>
        </div>
    </article>
@endsection
