{{--
 * 首屏横幅区块（tech-product 浅色主题，#13）
 *
 * 与 decoration 那份的差异是刻意的：本主题不用径向渐变光圈，标题左对齐、
 * tracking-tight，圆角收到 rounded-xl，高度更矮（浅色背景上大块留白显空）。
 * 两套主题各存一份完整副本，不抽公共（§0.3 第 1 条）。
 *
 * 除富文本区块外所有字段一律 {{ }} 转义（#13 安全要点）。
 * cta_url 过 SafeUrl scheme 白名单，被拦下则不渲染按钮而非渲染成 #。
 --}}
@php
    $title    = (string) ($data['title'] ?? '');
    $subtitle = (string) ($data['subtitle'] ?? '');
    $imagePath = (string) ($data['image'] ?? '');
    $imageAlt  = (string) ($data['image_alt'] ?? '');
    $ctaLabel  = (string) ($data['cta_label'] ?? '');
    $ctaUrl    = \Filamentboot\FilamentbootSite\Support\SafeUrl::sanitize($data['cta_url'] ?? null);

    $imageUrl = $imagePath !== ''
        ? \Illuminate\Support\Facades\Storage::disk($block->disk())->url($imagePath)
        : null;

    $headingId = 'block-hero-' . $index;
@endphp

<section class="relative overflow-hidden {{ $imageUrl ? '' : 'bg-site-surface' }} py-20 md:py-28"
         @if($imageUrl)
         style="background-image: url('{{ $imageUrl }}'); background-size: cover; background-position: center;"
         @endif
         aria-labelledby="{{ $headingId }}">

    {{-- 浅色主题用白色半透明蒙版而非暗色：深色文字压在照片上同样读不清，
         但压暗背景会让这一段与上下文的明度断裂 --}}
    @if($imageUrl)
        <div class="absolute inset-0" style="background-color: color-mix(in srgb, var(--color-bg-surface) 82%, transparent);" aria-hidden="true"></div>
        {{-- 背景图以 CSS 呈现，读屏软件取不到，补一个可访问名 --}}
        <span class="sr-only">{{ $imageAlt }}</span>
    @endif

    <div class="relative z-10 max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 id="{{ $headingId }}"
                class="text-site-primary text-3xl md:text-4xl lg:text-5xl font-bold tracking-tight leading-tight mb-5">
                {{ $title }}
            </h2>

            @if($subtitle !== '')
                <p class="text-site-secondary text-base md:text-lg leading-relaxed mb-8">
                    {{ $subtitle }}
                </p>
            @endif

            @if($ctaLabel !== '' && $ctaUrl !== null)
                <a href="{{ $ctaUrl }}"
                   class="btn-site-primary inline-flex items-center justify-center min-h-[44px] px-7 py-3 rounded-lg font-semibold text-base
                          focus-visible:ring-2 focus-visible:ring-[--color-primary] focus-visible:ring-offset-2 focus-visible:ring-offset-[--color-bg-base] focus-visible:outline-none">
                    {{ $ctaLabel }}
                </a>
            @endif
        </div>
    </div>
</section>
