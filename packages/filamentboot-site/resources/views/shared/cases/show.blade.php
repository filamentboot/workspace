{{--
 * 案例详情页（UI-SPEC §Component 9）
 *
 * 富文本输出必须经 mews/purifier 过滤（安全硬要求，T-10-05-01）。
 * 禁止裸 {!! $record->content_zh !!}。
 --}}
@extends('filamentboot-site::layouts.app')

@php
    $title   = $record->title_zh ?? '';
    $cover   = $record->coverUrl('og');
    $gallery = $record->galleryUrls('card');
    $content = $record->content_zh ?: ($record->description_zh ?? '');
    $style   = $record->style?->label() ?? '';
    $houseType = $record->house_type?->label() ?? '';
    $area    = ($record->area ?? '') !== '' ? $record->area . '㎡' : '';
    $budget  = $record->budget_range ?? '';
    $publishedAt = $record->published_at?->format('Y-m-d') ?? '';

    // 智能亮点：后台是一个 Textarea，运营用顿号 / 逗号 / 换行都写过，三种都收。
    // 空字符串会被 array_filter 滤掉，整段随之不渲染，不留空标题。
    $smartFeatures = array_values(array_filter(array_map(
        'trim',
        preg_split('/[、，,\r\n]+/u', (string) ($record->smart_features ?? '')) ?: []
    ), static fn (string $item): bool => $item !== ''));

    $customerAvatar = $record->customerAvatarUrl();
    $disclaimer = trim((string) config('filamentboot-site.cases.disclaimer', ''));
    $category = $record->category;
@endphp

@section('content')

    {{-- Hero 图（loading="eager"，首屏 LCP，UI-SPEC §Image Lazy Loading） --}}
    @if($cover)
        <div class="w-full relative overflow-hidden" style="max-height: 480px;">
            <img src="{{ $cover }}"
                 alt="{{ $title }} — {{ \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::case() }}"
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
    @endif

    {{-- 正文区 --}}
    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6">

        @include('filamentboot-site::components.breadcrumb')

        {{-- 标题 --}}
        <h1 class="text-site-primary text-3xl font-bold mb-6 leading-tight">{{ $title }}</h1>

        {{-- Meta 行 --}}
        <div class="flex flex-wrap items-center gap-2 py-4 border-b border-site mb-10">
            @if($category)
                <a href="{{ route('site.cases.index', ['category' => $category->slug]) }}"
                   class="bg-site-elevated text-site-accent text-xs px-3 py-1 rounded-full hover:underline
                          focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none">
                    {{ $category->name_zh }}
                </a>
            @endif
            @if($style)
                <span class="bg-site-elevated text-site-secondary text-xs px-3 py-1 rounded-full">{{ $style }}</span>
            @endif
            @if($houseType)
                <span class="bg-site-elevated text-site-secondary text-xs px-3 py-1 rounded-full">{{ $houseType }}</span>
            @endif
            @if($area)
                <span class="bg-site-elevated text-site-secondary text-xs px-3 py-1 rounded-full">{{ $area }}</span>
            @endif
            @if($budget)
                <span class="text-site-accent text-xs px-3 py-1">{{ $budget }}</span>
            @endif
            @if($publishedAt)
                <span class="flex items-center gap-1 text-site-secondary text-xs">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                    </svg>
                    {{ $publishedAt }}
                </span>
            @endif
        </div>

        {{-- 智能亮点（后台「智能亮点」字段，留空则整块不渲染）
             摆在正文之前：访客点进案例最想知道的就是「这套到底做了什么」，
             而正文是叙述性的，扫不出这个清单。 --}}
        @if($smartFeatures !== [])
            <div class="mb-10">
                <h2 class="text-site-primary text-base font-bold mb-3">这套做了什么</h2>
                <ul class="flex flex-wrap gap-2">
                    @foreach($smartFeatures as $feature)
                        <li class="bg-site-surface text-site-primary text-sm px-3 py-1.5 rounded-lg border border-site">
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 案例页声明（filamentboot-site.cases.disclaimer，默认空则整块不渲染）
             位置在正文之前而不是图集旁边：封面是首屏大图，看到图集时人已经默认
             「这是他们的施工现场」了，那时候再说就晚了。 --}}
        @if($disclaimer !== '')
            <p class="text-site-secondary text-xs leading-relaxed mb-8 pl-3 border-l-2 border-site">
                {{ $disclaimer }}
            </p>
        @endif

        {{-- 富文本内容（必须经 purifier 过滤，T-10-05-01 安全硬要求） --}}
        @if($content)
            <div class="prose text-site-primary leading-relaxed" style="word-break: break-word;">
                {!! \Filamentboot\FilamentbootSite\Support\RichText::purify($content) !!}
            </div>
        @endif

        {{-- 案例图集 --}}
        @if($gallery !== [])
            <div class="mt-12">
                <h2 class="text-site-primary text-xl font-bold mb-4">实景图集</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($gallery as $index => $imageUrl)
                        <div class="aspect-[4/3] overflow-hidden rounded-xl bg-site-elevated">
                            <img src="{{ $imageUrl }}"
                                 alt="{{ $title }} — 实景图 {{ $index + 1 }}"
                                 class="w-full h-full object-cover"
                                 loading="lazy"
                                 decoding="async">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 业主见证（姓名与引言缺一不可，避免只有头像的空壳卡片） --}}
        @if($record->hasCustomerTestimonial())
            <div class="mt-12 p-6 rounded-2xl bg-site-elevated border border-site">
                <div class="flex items-start gap-4">
                    @if($customerAvatar)
                        <img src="{{ $customerAvatar }}"
                             alt="{{ $record->customer_name }}"
                             class="shrink-0 w-12 h-12 rounded-full object-cover"
                             loading="lazy"
                             decoding="async"
                             width="48"
                             height="48">
                    @else
                        {{-- 无头像时用称呼首字生成占位圆标，比灰色人形图标更像真人 --}}
                        <div class="shrink-0 w-12 h-12 rounded-full bg-site-surface border border-site flex items-center justify-center"
                             aria-hidden="true">
                            <span class="text-site-accent font-bold text-lg">{{ mb_substr($record->customer_name, 0, 1) }}</span>
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <svg class="w-6 h-6 text-site-accent mb-2 opacity-60" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9.983 3v7.391c0 5.704-3.731 9.57-8.983 10.609l-.995-2.151c2.432-.917 3.995-3.638 3.995-5.849h-4v-10h9.983zm14.017 0v7.391c0 5.704-3.748 9.571-9 10.609l-.996-2.151c2.433-.917 3.996-3.638 3.996-5.849h-3.983v-10h9.983z" />
                        </svg>
                        <blockquote class="text-site-primary text-base leading-relaxed mb-3">{{ $record->customer_quote }}</blockquote>
                        <p class="text-site-secondary text-sm">
                            <span class="text-site-primary font-medium">{{ $record->customer_name }}</span>
                            @if($record->customer_meta)
                                <span class="mx-1">·</span>{{ $record->customer_meta }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- 标签 --}}
        @include('filamentboot-site::components.tag-list', ['tags' => $record->tags])

        {{-- 针对本案例的咨询 CTA
             按钮文案说的是访客脑子里的那句话，不是「预约咨询」——看完一个案例的人
             想问的从来都是「我家能不能也这样」。询盘面板会接着问户型 / 面积 /
             装修阶段（filamentboot-site.contact.panel_fields），所以这里不必再解释
             要提供什么。source 保持页面类型级 case-detail，不做记录级：
             案例只有几条，拆成记录级会让后台的来源列变成一堆用不上的值。 --}}
        <div class="mt-12 p-6 rounded-2xl bg-site-surface border border-site flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-site-primary font-bold text-base mb-1">我家户型也想这样，能做吗？</p>
                <p class="text-site-secondary text-sm">说一下户型和现在的装修进度，我们按你家的实际情况出一版配置清单与预算。</p>
            </div>
            <button
                type="button"
                data-contact-trigger="case-detail"
                class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-6 py-2 rounded-full text-sm whitespace-nowrap
                       focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
                @click="$store.contactPanel.show('case-detail')"
                aria-controls="contact-panel">
                帮我看看我家户型
            </button>
        </div>

        {{-- 相关案例（同风格 / 同户型 / 同分类优先，不足由最新补齐） --}}
        @if($related->isNotEmpty())
            <section class="mt-16 pt-12 border-t border-site" aria-labelledby="related-cases-heading">
                <h2 id="related-cases-heading" class="text-site-primary text-xl font-bold mb-6">相关案例</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($related as $relatedCase)
                        @include('filamentboot-site::components.case-card', ['case' => $relatedCase])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 返回链接 --}}
        <div class="mt-8">
            <a href="{{ route('site.cases.index') }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                &larr; 返回案例列表
            </a>
        </div>
    </div>

@endsection
