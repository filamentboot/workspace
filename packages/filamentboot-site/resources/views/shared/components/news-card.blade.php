{{--
 * 资讯卡片组件
 *
 * 接受 $article（NewsArticle 模型实例）。
 * 封面经 HasCoverImage::coverUrl('card') 读取 Media Library 的 cover 集合，
 * 未上传封面时渲染内联占位组件。aspect-[16/9] 防 CLS。
 *
 * 七期批次 1（2026-08-11）起，这份视图两套主题共用，落在 resources/views/shared/：
 * 与「把文件放进对方主题目录」不同，shared/ 是两套主题的平级共用目录，
 * 宿主删掉某一套主题的 themes/{theme}/ 目录不会牵连这份文件，不会留下断链。
 *
 * 标题用 h2 不用 h3：卡片直接铺在列表页 h1 底下，写 h3 就是 h1→h3 跳级——
 * 屏幕阅读器按层级导航、AI 抓取器按层级切分正文块，两边都会读错结构。
 * 首页里卡片挂在区块 h2 下面，h2→h2 是平级不是跳级，同样成立。
 --}}
@php
    $title       = $article->title_zh ?? '';
    $slug        = $article->slug ?? '';
    $cover       = $article->coverUrl('card');
    $excerpt     = $article->excerpt_zh ?? '';
    $category    = $article->category?->name_zh ?? '';
    $publishedAt = $article->published_at;
    $detailUrl   = route('site.news.show', $slug);
@endphp

<article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover flex flex-col">

    {{-- 封面图（aspect-[16/9] 防布局偏移） --}}
    <a href="{{ $detailUrl }}"
       class="block aspect-[16/9] overflow-hidden relative bg-site-elevated focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
       tabindex="-1"
       aria-hidden="true">
        @if($cover)
            <img src="{{ $cover }}"
                 alt="{{ $title }} — 资讯配图"
                 class="w-full h-full object-cover img-blur-up"
                 loading="lazy"
                 decoding="async"
                 width="800"
                 height="450"
                 x-on:load="$el.classList.add('loaded')">
        @else
            @include('filamentboot-site::components.image-placeholder', ['label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::news()])
        @endif
    </a>

    {{-- 卡片内容 --}}
    <div class="p-5 flex flex-col flex-1">

        {{-- 分类 + 发布日期 --}}
        <div class="flex items-center gap-2 mb-3 text-xs">
            @if($category)
                <span class="bg-site-elevated text-site-accent px-2 py-1 rounded-full">{{ $category }}</span>
            @endif
            @if($publishedAt)
                <time datetime="{{ $publishedAt->toDateString() }}" class="text-site-muted">
                    {{ $publishedAt->format('Y-m-d') }}
                </time>
            @endif
        </div>

        <h2 class="text-site-primary font-bold text-base line-clamp-2 leading-snug mb-2">
            <a href="{{ $detailUrl }}"
               class="hover:text-site-accent transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                {{ $title }}
            </a>
        </h2>

        @if($excerpt)
            <p class="text-site-secondary text-sm leading-relaxed line-clamp-3 mb-4">{{ $excerpt }}</p>
        @endif

        {{-- mt-auto 让「阅读全文」贴底，摘要长短不一时卡片底栏仍对齐 --}}
        <a href="{{ $detailUrl }}"
           class="mt-auto text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm"
           aria-label="阅读全文：{{ $title }}">
            阅读全文 &rarr;
        </a>
    </div>
</article>
