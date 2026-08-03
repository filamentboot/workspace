{{--
 * 资讯卡片（tech-product 浅色主题）
 *
 * 接受 $article（NewsArticle 模型实例）。封面走 coverUrl('card')。
 *
 * 与 decoration 主题各自持有一份完整视图，刻意不抽公共层：
 * 宿主装机后常会只留一套主题并删掉另一套目录，共享层会让删除留下断链。
 --}}
@php
    $title       = $article->title_zh ?? '';
    $cover       = $article->coverUrl('card');
    $excerpt     = $article->excerpt_zh ?? '';
    $category    = $article->category?->name_zh ?? '';
    $publishedAt = $article->published_at;
    $detailUrl   = route('site.news.show', $article->slug ?? '');
@endphp

<article class="bg-site-base rounded-xl overflow-hidden border border-site card-hover flex flex-col">

    <a href="{{ $detailUrl }}"
       class="block aspect-[16/9] overflow-hidden relative bg-site-elevated focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none"
       tabindex="-1"
       aria-hidden="true">
        @if($cover)
            <img src="{{ $cover }}"
                 alt="{{ $title }} — 资讯配图"
                 class="w-full h-full object-cover img-blur-up"
                 loading="lazy" decoding="async" width="800" height="450"
                 x-on:load="$el.classList.add('loaded')">
        @else
            @include('filamentboot-site::components.image-placeholder', ['label' => '智能家居资讯'])
        @endif
    </a>

    <div class="p-5 flex flex-col flex-1">

        <div class="flex items-center gap-2 mb-3 text-xs">
            @if($category)
                <span class="bg-site-surface border border-site text-site-secondary px-2 py-1 rounded-md">{{ $category }}</span>
            @endif
            @if($publishedAt)
                <time datetime="{{ $publishedAt->toDateString() }}" class="text-site-muted">
                    {{ $publishedAt->format('Y-m-d') }}
                </time>
            @endif
        </div>

        <h3 class="text-site-primary font-semibold text-base line-clamp-2 leading-snug mb-2 tracking-tight">
            <a href="{{ $detailUrl }}"
               class="hover:text-site-accent transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                {{ $title }}
            </a>
        </h3>

        @if($excerpt)
            <p class="text-site-secondary text-sm leading-relaxed line-clamp-3 mb-4">{{ $excerpt }}</p>
        @endif

        {{-- mt-auto 让「阅读全文」贴底，摘要长短不一时卡片底栏仍对齐 --}}
        <a href="{{ $detailUrl }}"
           class="mt-auto text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm"
           aria-label="阅读全文：{{ $title }}">
            阅读全文 &rarr;
        </a>
    </div>
</article>
