{{--
 * 城市卡片（两套主题共用）
 *
 * 接受 $page（SiteCityPage 实例，region 必须已加载）。
 *
 * **卡片标题用区划简称而不是页面标题。** 一屏二十张卡片，每张都写
 * 「XX 全屋智能装修」的话，八个字里有六个是一样的，扫视时等于没有标题。
 * 完整标题留给详情页的 h1 与 <title>。
 *
 * 没有封面图：城市页不挂媒体库——三百多张「城市封面」要么全是图库通图
 * （等于没有信息），要么就是编的。缺图不如不放。
 *
 * ⚠️ 七期批次 1（2026-08-11）起两套主题共用这份视图，落在 resources/views/shared/。
 * theme.php 清单表达不了 component，主题切换预检查抓不到「另一套缺这个组件」，
 * 只能两边都写。
 *
 * 标题用 h2 不用 h3：卡片直接铺在列表页 h1 底下，写 h3 就是 h1→h3 跳级——
 * 屏幕阅读器按层级导航、AI 抓取器按层级切分正文块，两边都会读错结构。
 * 首页里卡片挂在区块 h2 下面，h2→h2 是平级不是跳级，同样成立。
 --}}
@php
    $cityRegion = $page->region;
    $cityName   = $cityRegion?->displayName() ?? '';
@endphp

<article class="bg-site-surface rounded-2xl border border-site p-5 card-hover flex flex-col">
    <h2 class="text-site-primary font-bold text-lg leading-snug mb-2">
        <a href="{{ $page->url() }}"
           class="hover:text-site-accent transition-colors duration-200
                  focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm">
            {{ $cityName }}
        </a>
    </h2>

    @if($page->description_zh)
        <p class="text-site-secondary text-sm leading-relaxed line-clamp-2 mb-4">{{ $page->description_zh }}</p>
    @endif

    {{-- mt-auto 让入口贴底，简介长短不一时卡片底栏仍对齐 --}}
    <a href="{{ $page->url() }}"
       class="mt-auto text-site-accent text-sm hover:underline
              focus-visible:ring-2 focus-visible:ring-(--color-primary) focus-visible:outline-none rounded-sm"
       aria-label="查看{{ $cityName }}全屋智能装修">
        看本地情况 &rarr;
    </a>
</article>
