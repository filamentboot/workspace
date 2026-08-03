{{--
 * 案例卡片（tech-product 浅色主题）
 *
 * 接受 $case（SiteCase 模型实例）。封面走 coverUrl('card')，
 * 无封面时渲染 shared 层内联占位组件。
 --}}
@php
    $title = $case->title_zh ?? '';
    $desc  = $case->description_zh ?? '';
    $cover = $case->coverUrl('card');
    $style = $case->style?->label() ?? '';
    $houseType = $case->house_type?->label() ?? '';
    $area   = ($case->area ?? '') !== '' ? $case->area . '㎡' : '';
    $budget = $case->budget_range ?? '';
    $detailUrl = route('site.cases.show', $case->slug ?? '');
@endphp

<article class="bg-site-base rounded-xl overflow-hidden border border-site card-hover" role="article">

    <div class="aspect-[4/3] overflow-hidden relative bg-site-elevated">
        @if($cover)
            <img src="{{ $cover }}"
                 alt="{{ $title }} — 装修案例封面图"
                 class="w-full h-full object-cover img-blur-up"
                 loading="lazy" decoding="async" width="800" height="600"
                 x-on:load="$el.classList.add('loaded')">
        @else
            @include('filamentboot-site::components.image-placeholder', ['label' => '装修案例'])
        @endif
    </div>

    <div class="p-5 space-y-3">
        <div class="flex flex-wrap gap-1.5">
            @foreach(array_filter([$style, $houseType, $area]) as $tag)
                <span class="bg-site-elevated text-site-secondary text-xs px-2 py-0.5 rounded-md">{{ $tag }}</span>
            @endforeach
        </div>

        <h3 class="text-site-primary font-semibold text-base line-clamp-2 leading-snug">{{ $title }}</h3>

        @if($desc)
            <p class="text-site-secondary text-sm leading-relaxed line-clamp-2">{{ $desc }}</p>
        @endif

        <div class="flex items-center justify-between pt-1">
            @if($budget)
                <span class="text-site-accent text-sm font-medium">{{ $budget }}</span>
            @else
                <span></span>
            @endif
            <a href="{{ $detailUrl }}"
               class="text-site-accent text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm"
               aria-label="查看详情：{{ $title }}">
                查看详情 &rarr;
            </a>
        </div>
    </div>
</article>
