{{--
 * 产品卡片组件（UI-SPEC §Component 5）
 *
 * 接受 $product（SiteProduct 模型实例），按当前 locale 显示中/英文内容。
 * 图片 loading="lazy"，aspect-square 防 CLS。
 --}}
@php
    $isZh  = app()->getLocale() !== 'en';
    $title = $isZh ? ($product->title_zh ?? '') : ($product->title_en ?? $product->title_zh ?? '');
    $slug  = $product->slug ?? '';
    $cover = $product->cover_image ?? 'https://picsum.photos/seed/product-' . $slug . '/400/400';
    $brand = $product->brand ?? '';
    $price = $product->price ?? null;
    $detailUrl = $isZh ? url('/products/' . $slug) : url('/en/products/' . $slug);
    $inquireLabel = $isZh ? '咨询价格' : 'Inquire';
@endphp

<article class="bg-site-surface rounded-xl overflow-hidden border border-site card-hover">

    {{-- 封面图（aspect-square 防布局偏移） --}}
    <div class="aspect-square overflow-hidden relative bg-site-elevated">
        <img src="{{ $cover }}"
             alt="{{ $title }}"
             class="w-full h-full object-cover img-blur-up"
             loading="lazy"
             decoding="async"
             width="400"
             height="400"
             x-on:load="$el.classList.add('loaded')">
    </div>

    {{-- 卡片内容 --}}
    <div class="p-4">
        @if($brand)
            <p class="text-site-muted text-xs uppercase tracking-wide mb-1">{{ $brand }}</p>
        @endif

        <h3 class="text-site-primary font-normal text-sm line-clamp-2 leading-tight mb-2">
            <a href="{{ $detailUrl }}"
               class="hover:text-site-accent transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                {{ $title }}
            </a>
        </h3>

        @if($price)
            <p class="text-site-accent font-bold text-base">
                ¥{{ number_format($price, 0) }}
            </p>
        @else
            <p class="text-site-muted text-sm">{{ $inquireLabel }}</p>
        @endif
    </div>
</article>
