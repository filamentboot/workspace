{{--
 * 产品卡片组件（UI-SPEC §Component 5）
 *
 * 接受 $product（SiteProduct 模型实例）。
 * 封面图经 HasCoverImage::coverUrl('thumb') 读取 Media Library 的 cover 集合，
 * 未上传封面时渲染内联占位组件，不再请求外部图片服务。
 * 图片 loading="lazy"，aspect-square 防 CLS。
 --}}
@php
    $title = $product->title_zh ?? '';
    $slug  = $product->slug ?? '';
    $cover = $product->coverUrl('thumb');
    $brand = $product->brand ?? '';
    $price = $product->price ?? null;
    $detailUrl = route('site.products.show', $slug);
@endphp

<article class="bg-site-surface rounded-xl overflow-hidden border border-site card-hover">

    {{-- 封面图（aspect-square 防布局偏移） --}}
    <div class="aspect-square overflow-hidden relative bg-site-elevated">
        @if($cover)
            <img src="{{ $cover }}"
                 alt="{{ $title }} — 产品图"
                 class="w-full h-full object-cover img-blur-up"
                 loading="lazy"
                 decoding="async"
                 width="400"
                 height="400"
                 x-on:load="$el.classList.add('loaded')">
        @else
            @include('filamentboot-site::components.image-placeholder', ['label' => '智能产品'])
        @endif
    </div>

    {{-- 卡片内容 --}}
    <div class="p-4">
        @if($brand)
            <p class="text-site-secondary text-xs uppercase tracking-wide mb-1">{{ $brand }}</p>
        @endif

        <h3 class="text-site-primary font-normal text-sm line-clamp-2 leading-tight mb-2">
            <a href="{{ $detailUrl }}"
               class="hover:text-site-accent transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm">
                {{ $title }}
            </a>
        </h3>

        @if($price)
            <p class="text-site-accent font-bold text-base">
                ¥{{ number_format((float) $price, 0) }}
            </p>
        @else
            <button
                type="button"
                data-contact-trigger="product-card"
                class="text-site-accent text-sm hover:underline focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:outline-none rounded-sm"
                @click="$store.contactPanel.show('product-card')"
                aria-controls="contact-panel">
                咨询价格
            </button>
        @endif
    </div>
</article>
