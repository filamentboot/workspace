{{--
 * 案例卡片组件（UI-SPEC §Component 4）
 *
 * 接受 $case（SiteCase 模型实例），按当前 locale 显示中/英文内容。
 * 图片 loading="lazy"，有 alt 文本，aspect-[4/3] 防 CLS。
 --}}
@php
    $isZh  = app()->getLocale() !== 'en';
    $title = $isZh ? ($case->title_zh ?? '') : ($case->title_en ?? $case->title_zh ?? '');
    $desc  = $isZh ? ($case->description_zh ?? '') : ($case->description_en ?? $case->description_zh ?? '');
    $slug  = $case->slug ?? '';
    $cover = $case->cover_image ?? 'https://picsum.photos/seed/' . $slug . '/800/600';
    $style = $case->style?->label() ?? ($case->style ? (string) $case->style : '');
    $houseType = $case->house_type?->label() ?? ($case->house_type ? (string) $case->house_type : '');
    $area   = isset($case->area) && $case->area ? $case->area . '㎡' : '';
    $budget = $case->budget_range ?? '';
    $detailUrl = $isZh ? url('/cases/' . $slug) : url('/en/cases/' . $slug);
    $ctaLabel  = $isZh ? '查看详情' : 'View Details';
    $altText   = $title . ($isZh ? ' — 装修案例封面图' : ' — Case Cover Image');
@endphp

<article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover" role="article">

    {{-- 封面图容器（aspect-[4/3] 防布局偏移） --}}
    <div class="aspect-[4/3] overflow-hidden relative bg-site-elevated">
        <img src="{{ $cover }}"
             alt="{{ $altText }}"
             class="w-full h-full object-cover img-blur-up"
             loading="lazy"
             decoding="async"
             width="800"
             height="600"
             x-on:load="$el.classList.add('loaded')">
    </div>

    {{-- 卡片内容 --}}
    <div class="p-4 space-y-2">

        {{-- 标签行 --}}
        <div class="flex flex-wrap gap-1">
            @if($style)
                <span class="bg-site-elevated text-site-muted text-xs px-2 py-1 rounded-full">
                    {{ $style }}
                </span>
            @endif
            @if($houseType)
                <span class="bg-site-elevated text-site-muted text-xs px-2 py-1 rounded-full">
                    {{ $houseType }}
                </span>
            @endif
            @if($area)
                <span class="bg-site-elevated text-site-muted text-xs px-2 py-1 rounded-full">
                    {{ $area }}
                </span>
            @endif
        </div>

        {{-- 标题 --}}
        <h3 class="text-site-primary font-bold text-base line-clamp-2 leading-tight">
            {{ $title }}
        </h3>

        {{-- 描述 --}}
        @if($desc)
            <p class="text-site-secondary text-sm leading-relaxed line-clamp-3">
                {{ $desc }}
            </p>
        @endif

        {{-- 底栏：预算 + CTA --}}
        <div class="flex items-center justify-between pt-2">
            @if($budget)
                <span class="text-site-accent text-sm">{{ $budget }}</span>
            @else
                <span></span>
            @endif
            <a href="{{ $detailUrl }}"
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm"
               aria-label="{{ $ctaLabel }}：{{ $title }}">
                {{ $ctaLabel }}
            </a>
        </div>
    </div>
</article>
