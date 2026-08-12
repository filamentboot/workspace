{{--
 * 全屋套餐卡片（两套主题共用）
 *
 * 接受 $package（SitePackage 模型实例）。
 *
 * 卡片上的信息按「装修者决策顺序」排：先看是不是我家户型 → 再看什么档位 →
 * 再看多少钱 → 最后才看包含几项。标题反而排在户型徽标之后，因为标题本身
 * （「全屋智能家居 · 三室两厅豪华款」）在同一批卡片里高度雷同，扫视时没有区分度。
 *
 * **价格为空时显示「咨询价格」而不是隐藏整行**。方案那边是隐藏（8 处渲染点全是
 * 裸 @if），结果是卡片高度参差、也看不出「这条到底有没有价」。套餐是拿来横向比的，
 * 价格行必须每张卡都在同一个位置。
 *
 * 七期批次 1（2026-08-11）起两套主题共用这份视图，落在 resources/views/shared/：
 * 宿主装机后常会只留一套主题并删掉另一套目录，共享层会让删除留下断链。
 --}}
@php
    $title    = $package->title_zh ?? '';
    $cover    = $package->coverUrl('card');
    $detailUrl = route('site.packages.show', $package->slug);
    $layout   = $package->house_layout?->label();
    $tier     = $package->tier?->label();
    $itemCount = count($package->normalizedItems());
    $hasPrice = $package->price !== null && (float) $package->price > 0;
@endphp

<article class="bg-site-surface rounded-2xl overflow-hidden border border-site card-hover flex flex-col">

    {{-- 容器是 aspect-square 而不是 4:3：套餐封面是「户型剖面 + 环绕设备图标」的
         方图，标题就压在画面顶部，套进 4:3 会被 object-cover 从上下各切一刀，
         第一个被切掉的就是标题。 --}}
    <a href="{{ $detailUrl }}"
       class="block aspect-square overflow-hidden bg-site-elevated focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none"
       tabindex="-1"
       aria-hidden="true">
        @if($cover)
            <img src="{{ $cover }}"
                 alt="{{ $title }} — {{ \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::package() }}"
                 class="w-full h-full object-cover img-blur-up"
                 loading="lazy" decoding="async" width="800" height="800"
                 x-on:load="$el.classList.add('loaded')">
        @else
            @include('filamentboot-site::components.image-placeholder', ['label' => \Filamentboot\FilamentbootSite\Support\ContentTypeLabels::package()])
        @endif
    </a>

    <div class="p-6 flex flex-col flex-1">

        {{-- 户型 + 档位：卡片上最先被扫到的两个字段 --}}
        <div class="flex flex-wrap items-center gap-2 mb-3">
            @if($layout)
                <span class="bg-site-elevated text-site-accent text-xs px-2 py-1 rounded-full">{{ $layout }}</span>
            @endif
            @if($tier)
                <span class="text-site-secondary text-xs px-2 py-1 rounded-full border border-site">{{ $tier }}</span>
            @endif
            @if($package->area_range)
                <span class="text-site-muted text-xs">{{ $package->area_range }}</span>
            @endif
        </div>

        <h2 class="text-site-primary font-bold text-lg leading-snug mb-3">
            <a href="{{ $detailUrl }}"
               class="hover:text-site-accent transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
                {{ $title }}
            </a>
        </h2>

        {{-- 价格行：有价显示数字，没价显示「咨询价格」，位置永远在同一处 --}}
        <div class="mb-4">
            @if($hasPrice)
                <span class="text-site-primary font-bold text-2xl">¥{{ number_format((float) $package->price, 0) }}</span>
                <span class="text-site-muted text-xs ml-1">起</span>
            @else
                <span class="text-site-secondary text-base font-bold">咨询价格</span>
            @endif
        </div>

        @if($itemCount > 0)
            <p class="text-site-secondary text-sm mb-4">包含 {{ $itemCount }} 类设备</p>
        @endif

        {{-- mt-auto 让入口贴底，简介长短不一时卡片底栏仍对齐 --}}
        <a href="{{ $detailUrl }}"
           class="mt-auto text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm"
           aria-label="查看套餐详情：{{ $title }}">
            看包含什么 &rarr;
        </a>
    </div>
</article>
