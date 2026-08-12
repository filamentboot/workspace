{{--
 * 案例卡片组件（UI-SPEC §Component 4）
 *
 * 接受 $case（SiteCase 模型实例）。
 * 封面图经 HasCoverImage::coverUrl('card') 读取 Media Library 的 cover 集合，
 * 未上传封面时渲染内联占位组件，不再请求外部图片服务。
 * 图片 loading="lazy"，有 alt 文本，aspect-[4/3] 防 CLS。
 *
 * 标题用 h2 不用 h3：卡片直接铺在列表页 h1 底下，写 h3 就是 h1→h3 跳级——
 * 屏幕阅读器按层级导航、AI 抓取器按层级切分正文块，两边都会读错结构。
 * 首页里卡片挂在区块 h2 下面，h2→h2 是平级不是跳级，同样成立。
 --}}
@php
    $title = $case->title_zh ?? '';
    $desc  = $case->description_zh ?? '';
    $slug  = $case->slug ?? '';
    $cover = $case->coverUrl('card');
    $style = $case->style?->label() ?? '';
    $houseType = $case->house_type?->label() ?? '';
    $area   = ($case->area ?? '') !== '' ? $case->area . '㎡' : '';
    $budget = $case->budget_range ?? '';
    $detailUrl = route('site.cases.show', $slug);
@endphp

<article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover" role="article">

    {{-- 封面图容器（aspect-[4/3] 防布局偏移） --}}
    <div class="aspect-[4/3] overflow-hidden relative bg-site-elevated">
        @if($cover)
            <img src="{{ $cover }}"
                 alt="{{ $title }} — {{ \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::case() }}封面图"
                 class="w-full h-full object-cover img-blur-up"
                 loading="lazy"
                 decoding="async"
                 width="800"
                 height="600"
                 x-on:load="$el.classList.add('loaded')">
        @else
            @include('filamentboot-site::components.image-placeholder', ['label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::case()])
        @endif
    </div>

    {{-- 卡片内容 --}}
    <div class="p-4 space-y-2">

        {{-- 标签行 --}}
        <div class="flex flex-wrap gap-1">
            @if($style)
                <span class="bg-site-elevated text-site-secondary text-xs px-2 py-1 rounded-full">
                    {{ $style }}
                </span>
            @endif
            @if($houseType)
                <span class="bg-site-elevated text-site-secondary text-xs px-2 py-1 rounded-full">
                    {{ $houseType }}
                </span>
            @endif
            @if($area)
                <span class="bg-site-elevated text-site-secondary text-xs px-2 py-1 rounded-full">
                    {{ $area }}
                </span>
            @endif
        </div>

        {{-- 标题 --}}
        <h2 class="text-site-primary font-bold text-base line-clamp-2 leading-tight">
            {{ $title }}
        </h2>

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
               class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm"
               aria-label="查看详情：{{ $title }}">
                查看详情
            </a>
        </div>
    </div>
</article>
